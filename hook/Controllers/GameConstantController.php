<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\GameConstant;
use Bastivan\UniversalApi\Hook\Models\User;
use Bastivan\UniversalApi\Hook\Helpers\RedisHelper;
use MongoDB\BSON\ObjectId;

class GameConstantController
{
    private GameConstant $gameConstantModel;
    private User $userModel;

    public function __construct()
    {
        $this->gameConstantModel = new GameConstant();
        $this->userModel = new User();
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

    private function isAdmin($user)
    {
        return isset($user['role']) && (is_array($user['role']) ? in_array('admin', $user['role']) : $user['role'] === 'admin');
    }

    private function checkAdmin()
    {
        $user = $this->getCurrentUser();
        if (!$this->isAdmin($user)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    /**
     * GET /gameConstants/
     * List all constants (Admin only)
     */
    public function list()
    {
        $this->checkAdmin();
        $constants = $this->gameConstantModel->find([]);

        $result = [];
        foreach ($constants as $c) {
            $c['_id'] = (string)$c['_id'];
            $result[] = $c;
        }

        echo json_encode($result);
    }

    /**
     * GET /gameConstants/public/
     * List public constants
     */
    public function listPublic()
    {
        $client = RedisHelper::getClient();
        $cacheKey = 'game_constants_public';
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $constants = $this->gameConstantModel->find(['is_public' => true]);

        $result = [];
        foreach ($constants as $c) {
            $c['_id'] = (string)$c['_id'];
            $result[] = $c;
        }

        $json = json_encode($result);
        $client->setex($cacheKey, 3600, $json); // Cache for 1 hour
        echo $json;
    }

    /**
     * POST /gameConstants/public/:key
     * Get a public constant by key
     */
    public function getPublicByKey($key)
    {
        $client = RedisHelper::getClient();
        $cacheKey = 'game_constant_public_' . $key;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        // Decode key if needed, usually passed as string
        $constant = $this->gameConstantModel->findOne(['key' => $key, 'is_public' => true]);

        if (!$constant) {
            http_response_code(404);
            echo json_encode(['error' => 'Constant not found']);
            return;
        }

        $constant['_id'] = (string)$constant['_id'];
        $json = json_encode($constant);
        $client->setex($cacheKey, 3600, $json); // Cache for 1 hour
        echo $json;
    }

    /**
     * PUT /gameConstants/:id
     * Update a constant (Admin only)
     */
    public function update($id)
    {
        $this->checkAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No data to update']);
            return;
        }

        try {
            $objectId = new ObjectId($id);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }

        $existing = $this->gameConstantModel->findById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Constant not found']);
            return;
        }

        // Allow updating value, is_public, key, description etc.
        // We will just pass the data to update (filtering potentially dangerous fields if needed, but for admin we can be lenient)
        // However, we should probably not allow changing _id.
        unset($data['_id']);

        $this->gameConstantModel->updateOne(
            ['_id' => $objectId],
            ['$set' => $data]
        );

        // Invalidate cache
        $client = RedisHelper::getClient();
        $client->del(['game_constants_public']);
        if (isset($existing['key'])) {
            $client->del(['game_constant_public_' . $existing['key']]);
        }
        if (isset($data['key']) && $data['key'] !== $existing['key']) {
             $client->del(['game_constant_public_' . $data['key']]);
        }

        echo json_encode(['message' => 'Constant updated']);
    }
}
