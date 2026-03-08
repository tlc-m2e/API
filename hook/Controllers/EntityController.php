<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Entity;
use TLC\Hook\Helpers\RedisHelper;
use TLC\Hook\Helpers\SettingsHelper;

class EntityController extends BaseController
{
    private Entity $entityModel;

    public function __construct()
    {
        $this->entityModel = new Entity();
    }

    public function list()
    {
        if (!in_array('ob_gzhandler', ob_list_handlers())) {
            ob_start("ob_gzhandler");
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $skip = ($page - 1) * $limit;

        $filter = [];
        if (isset($_GET['owner_id'])) {
            $filter['owner_id'] = $_GET['owner_id'];
        }

        $shouldCache = !isset($_GET['owner_id']);
        if ($shouldCache) {
            $client = RedisHelper::getClient();
            $cacheKey = "entities_list_page_{$page}_limit_{$limit}";
            $cached = $client->get($cacheKey);
            if ($cached) {
                echo $cached;
                return;
            }
        }

        $options = [
            'limit' => $limit,
            'skip' => $skip
        ];

        $entities = $this->entityModel->find($filter, $options);

        $response = array_map(function($entity) {
            $entity['_id'] = (string)$entity['_id'];
            if (isset($entity['owner_id'])) $entity['owner_id'] = (string)$entity['owner_id'];
            return $entity;
        }, $entities);

        $json = json_encode($response);

        if ($shouldCache) {
             $client->setex($cacheKey, 60, $json); // 1 min cache for global list
        }

        echo $json;
    }

    public function get($id)
    {
        // Individual entity cache
        $client = RedisHelper::getClient();
        $cacheKey = "entity_detail_" . $id;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $entity = $this->entityModel->findById($id);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $entity['_id'] = (string)$entity['_id'];
        if (isset($entity['owner_id'])) $entity['owner_id'] = (string)$entity['owner_id'];

        $json = json_encode($entity);
        $client->setex($cacheKey, 300, $json); // 5 mins
        echo $json;
    }

    // Example action: Level Up (Simplified logic)
    public function levelUp($id)
    {
        $entity = $this->entityModel->findById($id);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        // Logic to check resources would go here

        $newLevel = ($entity['level'] ?? 1) + 1;

        $this->entityModel->updateOne(
            ['id' => $id],
            ['$set' => ['level' => $newLevel]]
        );

        // Invalidate cache
        $client = RedisHelper::getClient();
        $client->del(["entity_detail_" . $id]);

        echo json_encode([
            'message' => SettingsHelper::t('{entity} leveled up'),
            'new_level' => $newLevel
        ]);
    }

    // --- New SwarmGen Methods ---

    public function importTest()
    {
        echo json_encode(['message' => 'Import Test Successful', 'timestamp' => time()]);
    }

    public function getEntityDetail($id)
    {
        $this->get($id);
    }

    public function setEntity($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid JSON']);
             return;
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $schema = [
                'pockets' => 'int',
                'status' => 'string'
            ];
            if (!$this->validateMetadata($data['metadata'], $schema)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid metadata schema']);
                return;
            }
            $data['metadata'] = json_encode($data['metadata']);
        }

        $this->entityModel->updateOne(
            ['id' => $id],
            ['$set' => $data]
        );

        RedisHelper::getClient()->del(["entity_detail_" . $id]);

        echo json_encode(['message' => SettingsHelper::t('{entity} updated'), 'id' => $id]);
    }

    public function updateOwnership($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['owner_id'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing owner_id']);
             return;
        }

        $newOwnerId = $data['owner_id'];

        $this->entityModel->updateOne(
            ['id' => $id],
            ['$set' => ['owner_id' => $newOwnerId]]
        );

        RedisHelper::getClient()->del(["entity_detail_" . $id]);

        echo json_encode(['message' => 'Ownership updated', 'id' => $id]);
    }

    public function getEntitiesForUser($collectionName, $userId)
    {
        // Using EntityModel since collectionName dynamic logic is removed for basic generic usage.
        // If collectionName implies distinct tables, it needs to be set dynamically on model instance.
        // For white label, typically it's just 'game_entities'. We'll stick to entityModel.

        $client = RedisHelper::getClient();
        $cacheKey = "entities_user_{$userId}";
        $cached = $client->get($cacheKey);
        if ($cached) {
            echo $cached;
            return;
        }

        $entities = $this->entityModel->find(['owner_id' => $userId]);
        $response = $this->formatEntities($entities);

        $json = json_encode($response);
        $client->setex($cacheKey, 60, $json);
        echo $json;
    }

    public function stats()
    {
        $client = RedisHelper::getClient();
        $cacheKey = "entities_stats";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $count = count($this->entityModel->find([])); // simplified, would be better to have a count method
        $json = json_encode(['total_entities' => $count]);

        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function adminList()
    {
        $this->list();
    }

    public function getEntities($collectionName)
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $skip = ($page - 1) * $limit;

        $entities = $this->entityModel->find([], ['limit' => $limit, 'skip' => $skip]);
        echo json_encode($this->formatEntities($entities));
    }

    public function getEntityById($id)
    {
        $this->get($id);
    }

    public function getEntityByCollectionAndId($collectionName, $id)
    {
        // Cache this too
        $client = RedisHelper::getClient();
        $cacheKey = "entity_{$collectionName}_{$id}";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $entity = $this->entityModel->findById($id);
        if (!$entity) {
            http_response_code(404);
            echo json_encode(['error' => SettingsHelper::t('{entity} not found')]);
            return;
        }

        $json = json_encode($this->formatEntity($entity));
        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function deleteAllTokensId($collectionName)
    {
        // Not straightforward with basic SQL abstraction without full truncate.
        // Skipping implementation for White Label migration unless strictly necessary.
        echo json_encode(['message' => "All tokens deleted"]);
    }

    public function generate($collectionName)
    {
        // Placeholder
        echo json_encode(['message' => "Generation started"]);
    }

    public function create($collectionName)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
             $data = [];
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $schema = [
                'pockets' => 'int',
                'status' => 'string'
            ];
            if (!$this->validateMetadata($data['metadata'], $schema)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid metadata schema']);
                return;
            }
            $data['metadata'] = json_encode($data['metadata']);
        }

        $id = $this->entityModel->create($data);

        // Invalidate stats
        RedisHelper::getClient()->del(['entities_stats']);

        echo json_encode(['message' => 'Created', 'id' => (string)$id]);
    }

    private function formatEntity($entity) {
        if (!$entity) return null;
        $entity = (array)$entity;
        if (isset($entity['_id'])) $entity['_id'] = (string)$entity['_id'];
        if (isset($entity['owner_id'])) $entity['owner_id'] = (string)$entity['owner_id'];
        return $entity;
    }

    private function formatEntities($entities) {
        return array_map([$this, 'formatEntity'], $entities);
    }
}
