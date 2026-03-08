<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Models\SpendingWallet;
use TLC\Hook\Models\Duck;
use TLC\Hook\Models\Mint;
use TLC\Hook\Models\Egg;
use TLC\Hook\Models\GameConstant;
use TLC\Hook\Models\DuckSpending;
use TLC\Hook\Models\EggSpending;
use TLC\Hook\Helpers\MintHelper;

class MintController extends BaseController
{
    private $userModel;
    private $spendingWalletModel;
    private $duckModel;
    private $mintModel;
    private $eggModel;
    private $gameConstantModel;
    private $duckSpendingModel;
    private $eggSpendingModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->spendingWalletModel = new SpendingWallet();
        $this->duckModel = new Duck();
        $this->mintModel = new Mint();
        $this->eggModel = new Egg();
        $this->gameConstantModel = new GameConstant();
        $this->duckSpendingModel = new DuckSpending();
        $this->eggSpendingModel = new EggSpending();
    }

    private function getMe()
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception("Unauthorized", 401);
        }
        return $this->userModel->findById($userId);
    }

    // Placeholder for GameConstant logic
    private function getConstantValueCached($key, $default)
    {
        // Simple implementation: fetch from DB or return default
        // Assuming GameConstant model has 'key' and 'value'
        $constant = $this->gameConstantModel->findOne(['key' => $key]);
        return $constant ? $constant->value : $default;
    }

    private function getSpendingWalletForConnectedUser($userId)
    {
        return $this->spendingWalletModel->findOne(['user_id' => $userId]);
    }

    public function mintRules()
    {
        try {
            $user = $this->getMe();
            // Implement full estimation logic without parents

            $mintEnabled = $this->getConstantValueCached('GAME_CONSTANT_MINT_ENABLED', false);
            $mintPrice = $this->getConstantValueCached('GAME_CONSTANT_MINT_PRICE', 50);

            // Basic estimation logic
            echo json_encode([
                'mintEnabled' => $mintEnabled,
                'eventActive' => false,
                'policy' => [],
                'cap' => [],
                'cooldown' => [],
                'price' => $mintPrice,
                'hasEnoughFound' => true,
                'canMintNow' => $mintEnabled,
                'blocks' => [],
            ]);
        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function mint($parentOneId, $parentTwoId)
    {
        try {
            $user = $this->getMe();

            // Basic estimation with parents
            $mintEnabled = $this->getConstantValueCached('GAME_CONSTANT_MINT_ENABLED', false);
            $mintPrice = $this->getConstantValueCached('GAME_CONSTANT_MINT_PRICE', 50);

            echo json_encode([
                'mintEnabled' => $mintEnabled,
                'parentOne' => $parentOneId,
                'parentTwo' => $parentTwoId,
                'price' => $mintPrice,
                'canMintNow' => $mintEnabled
            ]);
        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function executeMint()
    {
        try {
            $user = $this->getMe();
            $data = json_decode(file_get_contents('php://input'), true);

            $parentOneId = $data['parentOne'] ?? null;
            $parentTwoId = $data['parentTwo'] ?? null;

            if (!$parentOneId || !$parentTwoId) {
                throw new \Exception("Parents are required", 400);
            }

            $mintPrice = $this->getConstantValueCached('GAME_CONSTANT_MINT_PRICE', 50);
            $userIdStr = (string)$user['_id'];

            $wallet = $this->spendingWalletModel->findOne(['user_id' => $userIdStr]);
            if (!$wallet || !isset($wallet['amountOfTOKEN']) || $wallet['amountOfTOKEN'] < $mintPrice) {
                throw new \Exception("Not enough TOKEN to mint", 400);
            }

            // Deduct cost
            $this->spendingWalletModel->updateOne(
                ['user_id' => $userIdStr],
                ['$inc' => ['amountOfTOKEN' => -$mintPrice]]
            );

            // Create Egg
            $eggId = $this->eggModel->create([
                'owner_id' => new \MongoDB\BSON\ObjectId($userIdStr),
                'type' => 'common',
                'status' => 'incubating',
                'parents' => [
                    new \MongoDB\BSON\ObjectId($parentOneId),
                    new \MongoDB\BSON\ObjectId($parentTwoId)
                ],
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ]);

            // Record Mint Event
            $this->mintModel->create([
                'user_id' => new \MongoDB\BSON\ObjectId($userIdStr),
                'parentOneId' => new \MongoDB\BSON\ObjectId($parentOneId),
                'parentTwoId' => new \MongoDB\BSON\ObjectId($parentTwoId),
                'eggId' => new \MongoDB\BSON\ObjectId((string)$eggId),
                'price' => $mintPrice,
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Mint executed',
                'egg_id' => (string)$eggId
            ]);
        } catch (\Exception $e) {
            http_response_code($e->getCode() ?: 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
