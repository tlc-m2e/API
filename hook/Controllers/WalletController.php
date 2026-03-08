<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Models\BaseModel; // Assuming Wallet is simple or we use BaseModel directly
use TLC\Hook\Models\SpendingWallet;
use MongoDB\BSON\ObjectId;

class WalletController extends BaseController
{
    private User $userModel;
    private BaseModel $walletModel;

    public function __construct()
    {
        $this->userModel = new User();
        // Dynamic model for 'wallets' collection
        $this->walletModel = new class extends BaseModel {
            protected string $collectionName = 'wallets';
        };
    }

    private function getCurrentUser()
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $user = $this->userModel->findById($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        return $user;
    }

    // GET /wallet/
    public function getWallet()
    {
        $user = $this->getCurrentUser();
        $wallet = $this->walletModel->findOne(['user' => $user['_id']]);

        if (!$wallet) {
            echo json_encode(['item' => []]);
            return;
        }

        $wallet['_id'] = (string)$wallet['_id'];
        $wallet['user'] = (string)$wallet['user'];
        echo json_encode(['item' => $wallet]);
    }

    // GET /wallet/getSolBalance
    public function getSolBalance()
    {
        $user = $this->getCurrentUser();
        // We cannot actually fetch Solana balance without Web3 connection.
        // Returning mock data or 0 for now to prevent 404.
        echo json_encode(['balance' => 0]);
    }

    // GET /wallet/getBalance
    public function getBalance()
    {
        $user = $this->getCurrentUser();
        // Mock response structure
        echo json_encode([
            'publicKey' => null, // Would come from wallet
            'balanceSol' => 0,
            'balanceCoin' => 0,
            'balanceToken' => 0,
            'ducks' => [],
            'egge' => []
        ]);
    }

    // GET /wallet/ducksTokensId
    public function getDucksTokensId()
    {
        $user = $this->getCurrentUser();
        echo json_encode(['items' => []]);
    }

    // GET /wallet/nfts
    public function getNfts()
    {
        $user = $this->getCurrentUser();
        echo json_encode(['items' => []]);
    }

    // GET /wallet/egge
    public function getEgge()
    {
        $user = $this->getCurrentUser();
        echo json_encode(['items' => []]);
    }

    // GET /wallet/nonce
    public function getNonce()
    {
        $user = $this->getCurrentUser();
        $nonce = "Sign this message to link your wallet to TLC M2E: " . bin2hex(random_bytes(16));

        try {
            $redis = new \Predis\Client(['host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1']);
            $redis->setex("wallet_nonce:{$user['_id']}", 300, $nonce); // 5 minutes expiry
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate nonce']);
            return;
        }

        echo json_encode(['nonce' => $nonce]);
    }

    // POST /wallet/link
    public function linkWallet()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        $publicKey = $data['publicKey'] ?? null;
        $signature = $data['signature'] ?? null;

        if (!$publicKey || !$signature) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing publicKey or signature']);
            return;
        }

        try {
            $redis = new \Predis\Client(['host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1']);
            $nonce = $redis->get("wallet_nonce:{$user['_id']}");

            if (!$nonce) {
                http_response_code(400);
                echo json_encode(['error' => 'Nonce expired or not found. Please request a new one.']);
                return;
            }

            // --- Signature Verification Mock ---
            // We would verify the Ed25519 signature here
            // Example using nacl or solana-php-sdk:
            // $isValid = sodium_crypto_sign_verify_detached(base64_decode($signature), $nonce, base58_decode($publicKey));
            $isValid = true; // Simulating valid signature

            if (!$isValid) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid signature']);
                return;
            }

            // Remove nonce to prevent replay attacks
            $redis->del("wallet_nonce:{$user['_id']}");

            // Link the wallet
            $wallet = $this->walletModel->findOne(['user' => $user['_id']]);
            if ($wallet) {
                $this->walletModel->updateOne(
                    ['_id' => $wallet['_id']],
                    ['$set' => ['publicKey' => $publicKey]]
                );
            } else {
                $this->walletModel->create([
                    'user' => $user['_id'],
                    'publicKey' => $publicKey,
                    'createdAt' => new \MongoDB\BSON\UTCDateTime()
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Wallet successfully linked!']);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to link wallet']);
        }
    }

    /**
     * Example method demonstrating how to sign outbound reward transactions securely
     * without exposing the private key to the PHP backend.
     */
    public function signRewardTransaction()
    {
        // Require Admin or background worker role for internal processing

        /*
        $data = json_decode(file_get_contents('php://input'), true);
        $transactionHashToSign = $data['txHash'] ?? null;

        if (!$transactionHashToSign) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing transaction hash']);
            return;
        }

        // AWS KMS Sign implementation (non-custodial strategy for House Wallets)
        try {
            $kmsClient = new \Aws\Kms\KmsClient([
                'version' => 'latest',
                'region'  => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'credentials' => [
                    'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                    'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                ]
            ]);

            $result = $kmsClient->sign([
                'KeyId' => $_ENV['AWS_KMS_KEY_ARN'],
                'Message' => $transactionHashToSign,
                'MessageType' => 'DIGEST', // We're signing a pre-hashed transaction
                'SigningAlgorithm' => 'ECDSA_SHA_256', // Or applicable algorithm
            ]);

            $signature = base64_encode($result['Signature']);
            echo json_encode(['signature' => $signature]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'KMS Signing failed: ' . $e->getMessage()]);
        }
        */
        echo json_encode(['message' => 'Example KMS Signing endpoint. See code comments.']);
    }
}
