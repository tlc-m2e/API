<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\SpendingWallet;
use TLC\Hook\Models\User;
use TLC\Hook\Models\Entity;
use TLC\Hook\Models\Egg;
use TLC\Hook\Helpers\RedisHelper;
use TLC\Hook\Helpers\SettingsHelper;

class SpendingWalletController extends BaseController
{
    private SpendingWallet $spendingWalletModel;
    private User $userModel;
    private Entity $entityModel;
    private Egg $eggModel;

    public function __construct()
    {
        $this->spendingWalletModel = new SpendingWallet();
        $this->userModel = new User();
        $this->entityModel = new Entity();
        $this->eggModel = new Egg();
    }

    public function getTickets()
    {
        $userId = $_SERVER['user_id'];
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_tickets_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $spendingWallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);

        if (!$spendingWallet) {
            $spendingWallet = $this->createSpendingWallet($userId);
        }

        $response = json_encode(['items' => $spendingWallet['tickets'] ?? []]);
        $client->setex($cacheKey, 60, $response); // Short cache: 1 min
        echo $response;
    }

    public function getBalance()
    {
        $userId = $_SERVER['user_id'];
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_balance_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $spendingWallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);

        if (!$spendingWallet) {
            $spendingWallet = $this->createSpendingWallet($userId);
        }

        $currency1 = SettingsHelper::getConstant('CURRENCY_1_NAME', 'SOL');
        $currency2 = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');
        $currency3 = SettingsHelper::getConstant('CURRENCY_3_NAME', 'TOKEN');

        // Note: The database columns are still amountOfSOL, etc. based on schema.
        // We map them dynamically in the JSON response here.
        $response = [
            'balance' . ucfirst(strtolower($currency1)) => $spendingWallet['amountOfSOL'] ?? 0,
            'balance' . ucfirst(strtolower($currency2)) => $spendingWallet['amountOfCOIN'] ?? 0,
            'balance' . ucfirst(strtolower($currency3)) => $spendingWallet['amountOfTOKEN'] ?? 0,
            'balanceSeed' => $spendingWallet['amountOfSeed'] ?? 0,
            'energy' => $spendingWallet['energy'] ?? 0,
            'maxEnergy' => $spendingWallet['maxEnergy'] ?? 0,
            'maxEndurance' => $spendingWallet['maxEndurance'] ?? 0,
        ];

        $json = json_encode($response);
        $client->setex($cacheKey, 10, $json); // Very short cache: 10s (balances change often)
        echo $json;
    }

    public function entityTeam()
    {
        // Renamed from duckTeam
        $userId = $_SERVER['user_id'];
        $client = RedisHelper::getClient();
        $cacheKey = 'spending_entity_team_' . $userId;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $spendingWallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);

        if (!$spendingWallet) {
             $spendingWallet = $this->createSpendingWallet($userId);
        }

        $response = [
            'main' => null,
            'supportOne' => null,
            'supportTwo' => null,
        ];

        $json = json_encode($response);
        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function duckTeam()
    {
        // Legacy alias to not break routes immediately if called
        $this->entityTeam();
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

        echo json_encode(['success' => true, 'message' => "Burned $amount $tokenType (Simulated)"]);
    }

    public function listWallets()
    {
        // Admin route
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $skip = ($page - 1) * $limit;

        $wallets = $this->spendingWalletModel->find([], ['limit' => $limit, 'skip' => $skip]);
        $items = [];

        $currency1 = SettingsHelper::getConstant('CURRENCY_1_NAME', 'SOL');
        $currency2 = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');
        $currency3 = SettingsHelper::getConstant('CURRENCY_3_NAME', 'TOKEN');

        foreach ($wallets as $wallet) {
            $items[] = [
                'balance' . ucfirst(strtolower($currency1)) => $wallet['amountOfSOL'] ?? 0,
                'balance' . ucfirst(strtolower($currency2)) => $wallet['amountOfCOIN'] ?? 0,
                'balance' . ucfirst(strtolower($currency3)) => $wallet['amountOfTOKEN'] ?? 0,
                'userId' => (string)($wallet['user_id'] ?? ''),
                'spendingId' => (string)$wallet['id'],
            ];
        }

        echo json_encode(['items' => $items, 'page' => $page, 'limit' => $limit]);
    }

    public function getWalletBalance($id)
    {
         // Admin route
        $spendingWallet = $this->spendingWalletModel->findById($id);

        $currency1 = SettingsHelper::getConstant('CURRENCY_1_NAME', 'SOL');
        $currency2 = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');
        $currency3 = SettingsHelper::getConstant('CURRENCY_3_NAME', 'TOKEN');

        if (!$spendingWallet) {
             echo json_encode([
                'balance' . ucfirst(strtolower($currency1)) => 0,
                'balance' . ucfirst(strtolower($currency2)) => 0,
                'balance' . ucfirst(strtolower($currency3)) => 0,
                'entities' => [],
                'entitiesTeam' => [
                  'main' => null,
                  'supportOne' => null,
                  'supportTwo' => null,
                ],
             ]);
             return;
        }

        $response = [
            'balance' . ucfirst(strtolower($currency1)) => $spendingWallet['amountOfSOL'] ?? 0,
            'balance' . ucfirst(strtolower($currency2)) => $spendingWallet['amountOfCOIN'] ?? 0,
            'balance' . ucfirst(strtolower($currency3)) => $spendingWallet['amountOfTOKEN'] ?? 0,
            'balanceSeed' => $spendingWallet['amountOfSeed'] ?? 0,
            'energy' => $spendingWallet['energy'] ?? 0,
            'maxEnergy' => $spendingWallet['maxEnergy'] ?? 0,
            'entities' => [],
            'entitiesTeam' => [
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
            ['id' => $id],
            ['$set' => ['maxEndurance' => $maxEndurance]]
        );

        $wallet = $this->spendingWalletModel->findById($id);
        if ($wallet && isset($wallet['user_id'])) {
             $userId = (string)$wallet['user_id'];
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
            'user_id' => $userId,
            'amountOfSOL' => 0,
            'amountOfCOIN' => 0,
            'amountOfTOKEN' => 0,
            'amountOfSeed' => 0,
            'energy' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $id = $this->spendingWalletModel->create($data);
        $data['id'] = $id;
        return $data;
    }
}
