<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\User;
use TLC\Hook\Models\BaseModel;
use MongoDB\BSON\ObjectId;

class UserSummaryController
{
    private User $userModel;
    // We don't have a UserSummary model yet, so we'll use a generic BaseModel pointing to 'user_summaries' collection
    private BaseModel $userSummaryModel;

    public function __construct()
    {
        $this->userModel = new User();
        // Dynamic model for user_summaries
        $this->userSummaryModel = new class extends BaseModel {
            protected string $collectionName = 'user_summaries';
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

    /**
     * GET /users/summary/get
     */
    public function getAll()
    {
        $user = $this->getCurrentUser();
        $includeObjects = isset($_GET['includeObjects']) ? filter_var($_GET['includeObjects'], FILTER_VALIDATE_BOOLEAN) : false;

        $filter = ['user_id' => $user['_id']];
        // If we want to filter out objects, we might check if object_id is set or not.
        // If includeObjects is false, it excludes summaries where object_id is present?
        // Or specific keys?
        // if (!includeObjects) { query.where('object_id').exists(false); }

        if (!$includeObjects) {
            $filter['object_id'] = ['$exists' => false];
        }

        $summaries = $this->userSummaryModel->find($filter);

        $result = [];
        foreach ($summaries as $item) {
             // Filter out blacklisted keys if necessary (UserSummaryKeysBlacklistedInGeneralStats)
             // For now we return all found
             $result[] = [
                 'key' => $item['key'],
                 'value' => $item['value'],
                 'object_id' => isset($item['object_id']) ? (string)$item['object_id'] : null,
                 'object_type' => $item['object_type'] ?? null,
                 'updatedAt' => isset($item['updated_at']) ? $item['updated_at']->toDateTime()->format(\DateTime::ISO8601) : null,
                 'createdAt' => isset($item['created_at']) ? $item['created_at']->toDateTime()->format(\DateTime::ISO8601) : null,
             ];
        }

        echo json_encode(['items' => $result]);
    }

    /**
     * GET /users/summary/get/:key/:objectId?/:objectType?
     */
    public function get($key, $objectId = null, $objectType = null)
    {
        $user = $this->getCurrentUser();

        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Key is required']);
            return;
        }

        $filter = [
            'user_id' => $user['_id'],
            'key' => $key
        ];

        if ($objectId && $objectType) {
            try {
                $filter['object_id'] = new ObjectId($objectId);
                $filter['object_type'] = $objectType;
            } catch (\Exception $e) {
                 http_response_code(400);
                 echo json_encode(['error' => 'Invalid objectId']);
                 return;
            }
        } elseif ($objectId || $objectType) {
             http_response_code(400);
             echo json_encode(['error' => 'Both objectId and objectType are required when one is provided']);
             return;
        } else {
            // Ensure we don't fetch object specific summaries if not requested
            $filter['object_id'] = ['$exists' => false];
        }

        $item = $this->userSummaryModel->findOne($filter);

        if (!$item) {
            echo json_encode(['item' => null]);
            return;
        }

        $parsedItem = [
            'key' => $item['key'],
            'value' => $item['value'],
            'object_id' => isset($item['object_id']) ? (string)$item['object_id'] : null,
            'object_type' => $item['object_type'] ?? null,
            'updatedAt' => isset($item['updated_at']) ? $item['updated_at']->toDateTime()->format(\DateTime::ISO8601) : null,
            'createdAt' => isset($item['created_at']) ? $item['created_at']->toDateTime()->format(\DateTime::ISO8601) : null,
        ];

        echo json_encode(['item' => $parsedItem]);
    }
}
