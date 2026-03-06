<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\Duck;
use Bastivan\UniversalApi\Hook\Core\Mongo;
use Bastivan\UniversalApi\Hook\Helpers\RedisHelper;
use MongoDB\BSON\ObjectId;

class DuckController
{
    private Duck $duckModel;

    public function __construct()
    {
        $this->duckModel = new Duck();
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
            $filter['owner_id'] = new ObjectId($_GET['owner_id']);
        }

        $shouldCache = !isset($_GET['owner_id']);
        if ($shouldCache) {
            $client = RedisHelper::getClient();
            $cacheKey = "ducks_list_page_{$page}_limit_{$limit}";
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

        $ducks = $this->duckModel->find($filter, $options);

        // Convert ObjectIds to string for JSON output
        $response = array_map(function($duck) {
            $duck['_id'] = (string)$duck['_id'];
            if (isset($duck['owner_id'])) $duck['owner_id'] = (string)$duck['owner_id'];
            return $duck;
        }, $ducks);

        $json = json_encode($response);

        if ($shouldCache) {
             $client->setex($cacheKey, 60, $json); // 1 min cache for global list
        }

        echo $json;
    }

    public function get($id)
    {
        // Individual duck cache
        $client = RedisHelper::getClient();
        $cacheKey = "duck_detail_" . $id;
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $duck = $this->duckModel->findById($id);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $duck['_id'] = (string)$duck['_id'];
        if (isset($duck['owner_id'])) $duck['owner_id'] = (string)$duck['owner_id'];

        $json = json_encode($duck);
        $client->setex($cacheKey, 300, $json); // 5 mins
        echo $json;
    }

    // Example action: Level Up (Simplified logic)
    public function levelUp($id)
    {
        $duck = $this->duckModel->findById($id);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        // Logic to check resources would go here

        $newLevel = ($duck['level'] ?? 1) + 1;

        $this->duckModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => ['level' => $newLevel]]
        );

        // Invalidate cache
        $client = RedisHelper::getClient();
        $client->del(["duck_detail_" . $id]);

        echo json_encode(['message' => 'Duck leveled up', 'new_level' => $newLevel]);
    }

    // --- New SwarmGen Methods ---

    public function importTest()
    {
        echo json_encode(['message' => 'Import Test Successful', 'timestamp' => time()]);
    }

    public function getDuckDetail($id)
    {
        $this->get($id);
    }

    public function setDuck($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid JSON']);
             return;
        }

        $this->duckModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => $data]
        );

        RedisHelper::getClient()->del(["duck_detail_" . $id]);

        echo json_encode(['message' => 'Duck updated', 'id' => $id]);
    }

    public function updateOwnership($id)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['owner_id'])) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing owner_id']);
             return;
        }

        try {
            $newOwnerId = new ObjectId($data['owner_id']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid owner_id format']);
            return;
        }

        $this->duckModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => ['owner_id' => $newOwnerId]]
        );

        RedisHelper::getClient()->del(["duck_detail_" . $id]);

        echo json_encode(['message' => 'Ownership updated', 'id' => $id]);
    }

    public function getDucksForUser($collectionName, $userId)
    {
        try {
            $ownerId = new ObjectId($userId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            return;
        }

        $client = RedisHelper::getClient();
        $cacheKey = "ducks_user_{$collectionName}_{$userId}";
        $cached = $client->get($cacheKey);
        if ($cached) {
            echo $cached;
            return;
        }

        $collection = Mongo::getInstance()->getCollection($collectionName);
        $ducks = $collection->find(['owner_id' => $ownerId])->toArray();
        $response = $this->formatDucks($ducks);

        $json = json_encode($response);
        $client->setex($cacheKey, 60, $json);
        echo $json;
    }

    public function stats()
    {
        $client = RedisHelper::getClient();
        $cacheKey = "ducks_stats";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $collection = Mongo::getInstance()->getCollection('ducks');
        $count = $collection->countDocuments();
        $json = json_encode(['total_ducks' => $count]);

        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function adminList()
    {
        $this->list();
    }

    public function getDucks($collectionName)
    {
        $collection = Mongo::getInstance()->getCollection($collectionName);
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $skip = ($page - 1) * $limit;

        $ducks = $collection->find([], ['limit' => $limit, 'skip' => $skip])->toArray();
        echo json_encode($this->formatDucks($ducks));
    }

    public function getDuckById($id)
    {
        $this->get($id);
    }

    public function getDuckByCollectionAndId($collectionName, $id)
    {
        // Cache this too
        $client = RedisHelper::getClient();
        $cacheKey = "duck_{$collectionName}_{$id}";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $collection = Mongo::getInstance()->getCollection($collectionName);
        try {
            $oid = new ObjectId($id);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }

        $duck = $collection->findOne(['_id' => $oid]);
        if (!$duck) {
            http_response_code(404);
            echo json_encode(['error' => 'Duck not found']);
            return;
        }

        $json = json_encode($this->formatDuck($duck));
        $client->setex($cacheKey, 300, $json);
        echo $json;
    }

    public function deleteAllTokensId($collectionName)
    {
        // Admin only usually
        $collection = Mongo::getInstance()->getCollection($collectionName);
        $collection->deleteMany([]);

        // Targeted invalidation instead of flushdb
        // It's hard to find all keys without scan, but deleting all tokens is rare.
        // We can just accept that old caches might linger for 5 mins (max TTL used).

        echo json_encode(['message' => "All tokens in $collectionName deleted"]);
    }

    public function generate($collectionName)
    {
        // Placeholder
        echo json_encode(['message' => "Generation started for $collectionName"]);
    }

    public function create($collectionName)
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
             $data = [];
        }
        $collection = Mongo::getInstance()->getCollection($collectionName);
        if (!isset($data['created_at'])) {
            $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        }
        $result = $collection->insertOne($data);

        // Invalidate stats
        RedisHelper::getClient()->del(['ducks_stats']);

        echo json_encode(['message' => 'Created', 'id' => (string)$result->getInsertedId()]);
    }

    private function formatDuck($duck) {
        if (!$duck) return null;
        $duck = (array)$duck;
        if (isset($duck['_id'])) $duck['_id'] = (string)$duck['_id'];
        if (isset($duck['owner_id'])) $duck['owner_id'] = (string)$duck['owner_id'];
        return $duck;
    }

    private function formatDucks($ducks) {
        return array_map([$this, 'formatDuck'], $ducks);
    }
}
