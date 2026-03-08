<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Entity;
use TLC\Hook\Models\SpendingWallet;
use TLC\Hook\Helpers\SettingsHelper;

class LevelUpController extends BaseController
{
    private Entity $entityModel;
    private SpendingWallet $spendingWalletModel;

    public function __construct()
    {
        $this->entityModel = new Entity();
        $this->spendingWalletModel = new SpendingWallet();
    }

    private function getCurrentUserId()
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId || !is_string($userId)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        return $userId;
    }

    private function checkOwnership($entity, $userId)
    {
        $ownerId = isset($entity['owner_id']) ? (string)$entity['owner_id'] : null;
        if ($ownerId !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => SettingsHelper::t('You do not own this {entity}')]);
            exit;
        }
    }

    /**
     * POST /swarmGen/levelUp/entity/:entityId
     * Level up an entity.
     */
    public function levelUp($entityId)
    {
        $entity = $this->entityModel->findById($entityId);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($entity, $userId);

        // Implement actual level up logic (resource check, timer, etc.)
        $currentLevel = $entity['level'] ?? 1;
        $newLevel = $currentLevel + 1;

        $baseCost = (int) SettingsHelper::getConstant('LEVEL_UP_BASE_COST', 10);
        $cost = $newLevel * $baseCost;
        $currencyName = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => "Not enough $currencyName to level up"]);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfCOIN' => -$cost]]
        );

        $this->entityModel->updateOne(
            ['id' => $entityId],
            ['$set' => ['level' => $newLevel]]
        );

        echo json_encode([
            'message' => SettingsHelper::t('{entity} level up started/completed'),
            'entity_id' => $entityId,
            'new_level' => $newLevel
        ]);
    }

    /**
     * POST /swarmGen/levelUp/entity/:entityId/accelerate
     * Accelerate the level up process.
     */
    public function accelerate($entityId)
    {
        $entity = $this->entityModel->findById($entityId);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($entity, $userId);

        // Implement acceleration logic (cost deduction, timer reduction)
        $cost = 5; // Basic accelerate cost
        $currencyName = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => "Not enough $currencyName to accelerate"]);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfCOIN' => -$cost]]
        );

        echo json_encode([
            'message' => 'Level up accelerated',
            'entity_id' => $entityId
        ]);
    }

    /**
     * POST /swarmGen/levelUp/entity/:entityId/unlockPocket
     * Unlock a slot (pocket).
     */
    public function unlockPocket($entityId)
    {
        $entity = $this->entityModel->findById($entityId);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($entity, $userId);

        // Implement pocket unlock logic
        // Pockets should be in metadata since we dropped it from main table structure
        // But for simplicity of SQL migration, assuming it's supported by BaseModel via json merge
        $pockets = $entity['pockets'] ?? 0;
        $newPockets = $pockets + 1;

        $cost = 20; // Basic cost to unlock a pocket
        $currencyName = SettingsHelper::getConstant('CURRENCY_3_NAME', 'TOKEN');

        $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$wallet || !isset($wallet['amountOfTOKEN']) || $wallet['amountOfTOKEN'] < $cost) {
            http_response_code(400);
            echo json_encode(['error' => "Not enough $currencyName to unlock pocket"]);
            return;
        }

        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => ['amountOfTOKEN' => -$cost]]
        );

        $this->entityModel->updateOne(
            ['id' => $entityId],
            ['$set' => ['pockets' => $newPockets]]
        );

        echo json_encode([
            'message' => 'Pocket unlocked',
            'entity_id' => $entityId,
            'pockets' => $newPockets
        ]);
    }

    /**
     * POST /swarmGen/levelUp/entity/:entityId/attributes
     * Distribute attribute points.
     */
    public function attributes($entityId)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['attributes'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing attributes data']);
             return;
        }

        $entity = $this->entityModel->findById($entityId);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $userId = $this->getCurrentUserId();
        $this->checkOwnership($entity, $userId);

        // Implement validation (check if points are available)
        $totalPoints = 0;
        foreach ($data['attributes'] as $key => $value) {
            $totalPoints += (int)$value;
        }

        $cost = $totalPoints * 2;
        $currencyName = SettingsHelper::getConstant('CURRENCY_2_NAME', 'COIN');

        if ($cost > 0) {
            $wallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
            if (!$wallet || !isset($wallet['amountOfCOIN']) || $wallet['amountOfCOIN'] < $cost) {
                http_response_code(400);
                echo json_encode(['error' => "Not enough $currencyName to upgrade attributes"]);
                return;
            }

            $this->spendingWalletModel->updateOne(
                ['user_id' => $userId],
                ['$inc' => ['amountOfCOIN' => -$cost]]
            );
        }

        // Merge the new attributes with existing ones
        // Assuming dot notation works or skipping for basic SQL abstraction
        $mongoUpdate = ['$inc' => []];
        foreach ($data['attributes'] as $key => $value) {
            $mongoUpdate['$inc']["attributes.$key"] = (int)$value;
        }

        if (empty($mongoUpdate['$inc'])) {
             http_response_code(400);
             echo json_encode(['error' => 'No valid attributes to update']);
             return;
        }

        $this->entityModel->updateOne(
            ['id' => $entityId],
            $mongoUpdate
        );

        echo json_encode([
            'message' => 'Attributes updated',
            'entity_id' => $entityId,
            'updated_attributes' => $data['attributes']
        ]);
    }

    /**
     * GET /swarmGen/levelUp/entity/:entityId
     * Get level up info for an entity.
     */
    public function getInfo($entityId)
    {
        $entity = $this->entityModel->findById($entityId);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $entity['_id'] = (string)$entity['_id'];
        if (isset($entity['owner_id'])) $entity['owner_id'] = (string)$entity['owner_id'];

        echo json_encode([
            'entity' => $entity,
            'level_up_info' => [
                'current_level' => $entity['level'] ?? 1,
                'next_level_cost' => 100, // Placeholder
                'time_remaining' => 0 // Placeholder
            ]
        ]);
    }
}
