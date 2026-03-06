<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\Swap;
use Bastivan\UniversalApi\Hook\Models\SpendingWallet;
use Bastivan\UniversalApi\Hook\Middleware\AuthMiddleware;
use MongoDB\BSON\ObjectId;

class SwapController
{
    private Swap $swapModel;
    private SpendingWallet $spendingWalletModel;

    public function __construct()
    {
        $this->swapModel = new Swap();
        $this->spendingWalletModel = new SpendingWallet();
    }

    private function getCurrentUser()
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $userModel = new \Bastivan\UniversalApi\Hook\Models\User();
        $user = $userModel->findById($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        return $user;
    }

    private function checkAdmin()
    {
        $user = $this->getCurrentUser();
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    public function mintCoin()
    {
        $this->checkAdmin();
        // Trigger ecosystem minter service logic
        // Stub
        echo json_encode([
            'success' => 1,
            'message' => 'Coin minting process started'
        ]);
    }

    public function poolClaim($fromToken, $toToken)
    {
        $this->checkAdmin();
        // Logic to claim fee from liquidity pool
        // Stub
        echo json_encode(['message' => "Claiming pool fees for $fromToken -> $toToken"]);
    }

    public function getPoolDetails($fromToken, $toToken, $amount = 1, $slippage = 1)
    {
        // Logic to get pool details and quote
        // This requires complex interactions with Solana/Liquidity pools which are likely in services/helpers
        // that are not fully ported or available.
        // We will return a mock response structure based on the NestJS controller.

        $amount = (float)$amount;
        $slippage = (float)$slippage;

        // Mock data
        $response = [
            'inToken' => 'MockInTokenAddress',
            'outToken' => 'MockOutTokenAddress',
            'pool' => 'MockPoolAddress',
            'input' => [
                'amount' => $amount,
                'amountInLamports' => (string)($amount * 1000000000),
            ],
            'result' => [
                'estimatedOut' => (string)($amount * 0.95), // Mock rate
                'estimatedOutRaw' => (string)($amount * 0.95 * 1000000000),
                'minOutWithSlippage' => (string)($amount * 0.94),
                'fee' => '0.01',
                'protocolFee' => '0.001',
                'priceImpact' => '0.1000 %',
                'priceEnd' => 0.95
            ],
            'binsUsed' => ['MockBinArrayPubkey']
        ];

        echo json_encode($response);
    }

    public function getSwap($id)
    {
        $user = $this->getCurrentUser();
        $swap = $this->swapModel->findById($id);

        if (!$swap) {
            http_response_code(404);
            echo json_encode(['error' => 'Swap not found']);
            return;
        }

        // Check ownership
        // Note: $swap['user'] might be an object or id string depending on storage
        $swapUserId = isset($swap['user']['_id']) ? (string)$swap['user']['_id'] : (string)$swap['user'];

        if ($swapUserId !== (string)$user['_id']) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $swap['_id'] = (string)$swap['_id'];
        // Format dates if needed

        echo json_encode(['item' => $swap]);
    }

    public function swap()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        $fromToken = $data['fromToken'] ?? null;
        $toToken = $data['toToken'] ?? null;

        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid swap amount']);
            return;
        }

        $amount = (float)$data['amount'];

        if (!$fromToken || !$toToken) {
            http_response_code(404); // NestJS used NotFoundException
            echo json_encode(['error' => 'Swap tokens not found']);
            return;
        }

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Swap amount must be greater than zero']);
            return;
        }

        // Logic for specific token checks (e.g. COIN token limits)
        if ($fromToken === 'COIN') {
             // InventoryHelper check logic...
             if ($amount < 1) {
                 http_response_code(401);
                 echo json_encode(['error' => 'You need to swap at least 1 COIN']);
                 return;
             }
        }

        // Verify spending wallet
        // the field to query is user_id, but the codebase uses 'user._id' in the stub, we should check both or use 'user_id'
        $spendingWallet = $this->spendingWalletModel->findOne(['user_id' => (string)$user['_id']]);

        // fallback to original search if not found
        if (!$spendingWallet) {
             $spendingWallet = $this->spendingWalletModel->findOne(['user._id' => $user['_id']]);
        }

        if (!$spendingWallet) {
            http_response_code(404);
            echo json_encode(['error' => 'User does not have a spending wallet']);
            return;
        }

        // Basic swap logic implementation
        $fromField = "amountOf" . strtoupper($fromToken);
        $toField = "amountOf" . strtoupper($toToken);

        if (!isset($spendingWallet[$fromField]) || $spendingWallet[$fromField] < $amount) {
            http_response_code(400);
            echo json_encode(['error' => "Not enough {$fromToken} to swap"]);
            return;
        }

        // Mock rate: 1 to 0.95
        $rate = 0.95;
        $amountOut = $amount * $rate;

        // Deduct from token
        $this->spendingWalletModel->updateOne(
            ['_id' => $spendingWallet['_id']],
            ['$inc' => [$fromField => -$amount]]
        );

        // Add to token
        $this->spendingWalletModel->updateOne(
            ['_id' => $spendingWallet['_id']],
            ['$inc' => [$toField => $amountOut]]
        );

        $swapId = new ObjectId();
        $this->swapModel->insertOne([
            '_id' => $swapId,
            'user_id' => (string)$user['_id'],
            'fromToken' => $fromToken,
            'toToken' => $toToken,
            'amountIn' => $amount,
            'amountOut' => $amountOut,
            'status' => 'completed',
            'createdAt' => new \MongoDB\BSON\UTCDateTime()
        ]);

        echo json_encode([
            'success' => 1,
            'message' => 'Swap completed',
            'swapId' => (string)$swapId,
            'amountOut' => $amountOut
        ]);
    }
}
