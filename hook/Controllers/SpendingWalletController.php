<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\SpendingWallet;
use Bastivan\UniversalApi\Hook\Models\User;
use Bastivan\UniversalApi\Hook\Models\Duck;
use Bastivan\UniversalApi\Hook\Models\Egg;
use Bastivan\UniversalApi\Hook\Helpers\RedisHelper;
use MongoDB\BSON\ObjectId;

class SpendingWalletController
{
    private SpendingWallet $spendingWalletModel;
    private User $userModel;
    private Duck $duckModel;
    private Egg $eggModel;

    public function __construct()
    {
        $this->spendingWalletModel = new SpendingWallet();
        $this->userModel = new User();
        $this->duckModel = new Duck();
        $this->eggModel = new Egg();
    }

    public function getTickets()
    {
        $userId = $_REQUEST['user_id'];
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_tickets_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $userObjectId = new ObjectId($userId);
        $spendingWallet = $this->spendingWalletModel->findOne(['user' => $userObjectId]);

        if (!$spendingWallet) {
            $spendingWallet = $this->createSpendingWallet($userObjectId);
        }

        $response = json_encode(['items' => $spendingWallet['tickets'] ?? []]);
        $client->setex($cacheKey, 60, $response); // Short cache: 1 min
        echo $response;
    }

    public function getBalance()
    {
        $userId = $_REQUEST['user_id'];
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_balance_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $userObjectId = new ObjectId($userId);
        $spendingWallet = $this->spendingWalletModel->findOne(['user' => $userObjectId]);

        if (!$spendingWallet) {
            $spendingWallet = $this->createSpendingWallet($userObjectId);
        }

        $response = [
            'balanceSol' => $spendingWallet['amountOfSOL'] ?? 0,
            'balanceCoin' => $spendingWallet['amountOfCOIN'] ?? 0,
            'balanceToken' => $spendingWallet['amountOfTOKEN'] ?? 0,
            'balanceSeed' => $spendingWallet['amountOfSeed'] ?? 0,
            'energy' => $spendingWallet['energy'] ?? 0,
            'maxEnergy' => $spendingWallet['maxEnergy'] ?? 0,
            'maxEndurance' => $spendingWallet['maxEndurance'] ?? 0,
        ];

        $json = json_encode($response);
        $client->setex($cacheKey, 10, $json); // Very short cache: 10s (balances change often)
        echo $json;
    }

    public function duckTeam()
    {
        $userId = $_REQUEST['user_id'];
        // Team composition changes less frequently than balance, maybe 5 mins
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_duck_team_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $userObjectId = new ObjectId($userId);
        $spendingWallet = $this->spendingWalletModel->findOne(['user' => $userObjectId]);

        if (!$spendingWallet) {
             $spendingWallet = $this->createSpendingWallet($userObjectId);
        }

        // Implementation placeholder
        $response = [
            'main' => null,
            'supportOne' => null,
            'supportTwo' => null,
        ];

        $json = json_encode($response);
        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function burnWallet()
    {
        // Admin route
        $input = json_decode(file_get_contents('php://input'), true);
        $tokenType = $input['tokenType'] ?? null;
        $amount = $input['amount'] ?? null;

        if (!$tokenType || !$amount) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            return;
        }

        if (!preg_match('/^(COIN|TOKEN)$/', $tokenType)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid token type']);
            return;
        }

        // Invalidate cache for potentially affected users? This is a burning wallet, usually system wallet.
        // If it affects user balance, we'd need user_id.

        echo json_encode(['success' => true, 'message' => "Burned $amount $tokenType (Simulated)"]);
    }

    public function listWallets()
    {
        // Admin route - Pagination is CRITICAL for 3k users
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $skip = ($page - 1) * $limit;

        $wallets = $this->spendingWalletModel->find([], ['limit' => $limit, 'skip' => $skip]);
        $items = [];

        foreach ($wallets as $wallet) {
            $items[] = [
                'balanceSol' => $wallet['amountOfSOL'] ?? 0,
                'balanceCoin' => $wallet['amountOfCOIN'] ?? 0,
                'balanceToken' => $wallet['amountOfTOKEN'] ?? 0,
                'userId' => (string)($wallet['user'] ?? ''),
                'spendingId' => (string)$wallet['_id'],
            ];
        }

        // Count total for pagination meta (optional, can be expensive)
        // $total = $this->spendingWalletModel->count([]);

        echo json_encode(['items' => $items, 'page' => $page, 'limit' => $limit]);
    }

    public function getWalletBalance($id)
    {
         // Admin route
        $spendingWallet = $this->spendingWalletModel->findById($id);

        if (!$spendingWallet) {
             echo json_encode([
                'balanceSol' => 0,
                'balanceCoin' => 0,
                'balanceToken' => 0,
                'ducks' => [],
                'egge' => [],
                'ducksTeam' => [
                  'main' => null,
                  'supportOne' => null,
                  'supportTwo' => null,
                ],
             ]);
             return;
        }

        $response = [
            'balanceSol' => $spendingWallet['amountOfSOL'] ?? 0,
            'balanceCoin' => $spendingWallet['amountOfCOIN'] ?? 0,
            'balanceToken' => $spendingWallet['amountOfTOKEN'] ?? 0,
            'balanceSeed' => $spendingWallet['amountOfSeed'] ?? 0,
            'energy' => $spendingWallet['energy'] ?? 0,
            'maxEnergy' => $spendingWallet['maxEnergy'] ?? 0,
             'ducks' => [],
            'egge' => [],
            'ducksTeam' => [
              'main' => null,
              'supportOne' => null,
              'supportTwo' => null,
            ],
        ];

        echo json_encode($response);
    }

    public function stats()
    {
        echo json_encode(['stats' => []]);
    }

    public function setMaxEndurance($id)
    {
        // Admin route
        $input = json_decode(file_get_contents('php://input'), true);
        $maxEndurance = $input['maxEndurance'] ?? null;

        if ($maxEndurance === null || $maxEndurance < 0) {
             http_response_code(400);
             echo json_encode(['error' => 'maxEndurance must be a positive number']);
             return;
        }

        $maxThreshold = 2;
        if ($maxEndurance > $maxThreshold) {
             http_response_code(400);
             echo json_encode(['error' => "maxEndurance cannot exceed $maxThreshold"]);
             return;
        }

        $this->spendingWalletModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => ['maxEndurance' => $maxEndurance]]
        );

        // Invalidate cache for the user associated with this wallet
        // But we only have wallet ID here. We need to find the wallet first to get user ID.
        // It's an admin operation, slight overhead is fine.
        $wallet = $this->spendingWalletModel->findById($id);
        if ($wallet && isset($wallet['user'])) {
             $userId = (string)$wallet['user'];
             RedisHelper::getClient()->del(['spending_balance_' . $userId]);
        }

        $updatedWallet = $this->spendingWalletModel->findById($id);

        echo json_encode([
            'success' => true,
            'spendingWallet' => $updatedWallet
        ]);
    }

    private function createSpendingWallet($userId)
    {
        $data = [
            'user' => $userId,
            'amountOfSOL' => 0,
            'amountOfCOIN' => 0,
            'amountOfTOKEN' => 0,
            'amountOfSeed' => 0,
            'energy' => 0,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'updated_at' => new \MongoDB\BSON\UTCDateTime(),
        ];
        $id = $this->spendingWalletModel->create($data);
        $data['_id'] = $id;
        return $data;
    }
}
