<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Models\BaseModel; // Assuming Wallet is simple or we use BaseModel directly
use TLC\Hook\Models\SpendingWallet;
use MongoDB\BSON\ObjectId;

class WalletController
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
        $userId = $_REQUEST['user_id'] ?? null;
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

    // POST /wallet/import
    public function importWallet()
    {
        $user = $this->getCurrentUser();
        // Logic to import wallet (requires crypto lib).
        // Returning error or success stub.
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['mnemonic']) || empty($data['password'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing fields']);
             return;
        }

        // We can't implement real import without proper libs.
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented in this environment']);
    }
}
