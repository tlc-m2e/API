<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Duck;
use TLC\Hook\Models\SpendingWallet;
use MongoDB\BSON\ObjectId;

class LevelUpController
{
    private Duck $duckModel;
    private SpendingWallet $spendingWalletModel;

    public function __construct()
    {
        $this->duckModel = new Duck();
        $this->spendingWalletModel = new SpendingWallet();
    }

    private function getCurrentUserId()
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId || !is_string($userId)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        return $userId;
    }

    private function checkOwnership($duck, $userId)
    {
        $ownerId = isset($duck['owner_id']) ? (string)$duck['owner_id'] : null;
        if ($ownerId !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not own this duck']);
            exit;
        }
    }

    /**
     * POST /swarmGen/levelUp/duck/:duckId
     * Level up a duck.
     */
    public function levelUp($duckId)
    {
        try {
            $oid = new ObjectId($duckId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Duck ID']);
            return;
        }

        $duck = $this->duckModel->findById($duckId);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($duck, $userId);

        // Implement actual level up logic (resource check, timer, etc.)
        $currentLevel = $duck['level'] ?? 1;
        $newLevel = $currentLevel + 1;

        $cost = $newLevel * 10; // Basic cost

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => 'Not enough COIN to level up']);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfCOIN' => -$cost]]
        );

        $this->duckModel->updateOne(
            ['_id' => $oid],
            ['$set' => ['level' => $newLevel]]
        );

        echo json_encode([
            'message' => 'Duck level up started/completed',
            'duck_id' => $duckId,
            'new_level' => $newLevel
        ]);
    }

    /**
     * POST /swarmGen/levelUp/duck/:duckId/accelerate
     * Accelerate the level up process.
     */
    public function accelerate($duckId)
    {
        try {
            $oid = new ObjectId($duckId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Duck ID']);
            return;
        }

        $duck = $this->duckModel->findById($duckId);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($duck, $userId);

        // Implement acceleration logic (cost deduction, timer reduction)
        $cost = 5; // Basic accelerate cost

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => 'Not enough COIN to accelerate']);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfCOIN' => -$cost]]
        );

        echo json_encode([
            'message' => 'Level up accelerated',
            'duck_id' => $duckId
        ]);
    }

    /**
     * POST /swarmGen/levelUp/duck/:duckId/unlockPocket
     * Unlock a slot (pocket).
     */
    public function unlockPocket($duckId)
    {
        try {
            $oid = new ObjectId($duckId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Duck ID']);
            return;
        }

        $duck = $this->duckModel->findById($duckId);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($duck, $userId);

        // Implement pocket unlock logic
        $pockets = $duck['pockets'] ?? 0;
        $newPockets = $pockets + 1;

        $cost = 20; // Basic cost to unlock a pocket

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfTOKEN']) || $wallet['amountOfTOKEN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => 'Not enough TOKEN to unlock pocket']);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfTOKEN' => -$cost]]
        );

        $this->duckModel->updateOne(
            ['_id' => $oid],
            ['$set' => ['pockets' => $newPockets]]
        );

        echo json_encode([
            'message' => 'Pocket unlocked',
            'duck_id' => $duckId,
            'pockets' => $newPockets
        ]);
    }

    /**
     * POST /swarmGen/levelUp/duck/:duckId/attributes
     * Distribute attribute points.
     */
    public function attributes($duckId)
    {
        try {
            $oid = new ObjectId($duckId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Duck ID']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['attributes'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing attributes data']);
             return;
        }

        $duck = $this->duckModel->findById($duckId);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($duck, $userId);

        // Implement validation (check if points are available)
        // We calculate total points being added and deduct a generic cost
        $totalPoints = 0;
        foreach ($data['attributes'] as $key => $value) {
            $totalPoints += (int)$value;
        }

        $cost = $totalPoints * 2;

        if ($cost > 0) {
            $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
            if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
                http_response_code(400);
                echo json_encode(['error' => 'Not enough COIN to upgrade attributes']);
                return;
            }

            $this->spendingWalletModel->updateOne(
                ['user_id' => $userId],
                ['$inc' => ['amountOfCOIN' => -$cost]]
            );
        }

        // Merge the new attributes with existing ones

        // If attributes is a subdocument:
        // We probably want to increment existing values or set them.
        // Let's assume we are adding points to existing attributes.

        // Simplified approach: Just update the provided fields in attributes object
        // NOTE: $set with dot notation works for updating fields inside a document.

        $mongoUpdate = ['$inc' => []];
        foreach ($data['attributes'] as $key => $value) {
            $mongoUpdate['$inc']["attributes.$key"] = (int)$value;
        }

        if (empty($mongoUpdate['$inc'])) {
             http_response_code(400);
             echo json_encode(['error' => 'No valid attributes to update']);
             return;
        }

        $this->duckModel->updateOne(
            ['_id' => $oid],
            $mongoUpdate
        );

        echo json_encode([
            'message' => 'Attributes updated',
            'duck_id' => $duckId,
            'updated_attributes' => $data['attributes']
        ]);
    }

    /**
     * GET /swarmGen/levelUp/duck/:duckId
     * Get level up info for a duck.
     */
    public function getInfo($duckId)
    {
        try {
            $oid = new ObjectId($duckId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Duck ID']);
            return;
        }

        $duck = $this->duckModel->findById($duckId);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        // Clean up ObjectId for JSON
        $duck['_id'] = (string)$duck['_id'];
        if (isset($duck['owner_id'])) $duck['owner_id'] = (string)$duck['owner_id'];

        echo json_encode([
            'duck' => $duck,
            'level_up_info' => [
                'current_level' => $duck['level'] ?? 1,
                'next_level_cost' => 100, // Placeholder
                'time_remaining' => 0 // Placeholder
            ]
        ]);
    }
}
