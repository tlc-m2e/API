<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Egg;
use TLC\Hook\Core\Mongo;
use MongoDB\BSON\ObjectId;

class EggController extends BaseController
{
    private Egg $eggModel;

    public function __construct()
    {
        $this->eggModel = new Egg();
    }

    public function list()
    {
        $filter = [];
        if (isset($_GET['owner_id'])) {
            $filter['owner_id'] = new ObjectId($_GET['owner_id']);
        }

        $eggs = $this->eggModel->find($filter);
        echo json_encode($this->formatEggs($eggs));
    }

    public function get($id)
    {
        $egg = $this->eggModel->findById($id);
        if (!$egg) {
            http_response_code(404);
            echo json_encode(['error' => 'Egg not found']);
            return;
        }
        echo json_encode($this->formatEgg($egg));
    }

    // --- SwarmGen Methods ---

    public function importTest()
    {
        echo json_encode(['message' => 'Import Test Successful', 'timestamp' => time()]);
    }

    public function getEggDetail($id)
    {
        $this->get($id);
    }

    public function generate($collectionName)
    {
        // Placeholder for generation logic
        echo json_encode(['message' => "Generation started for $collectionName"]);
    }

    public function open($id)
    {
        // Hatch a egg (becomes Duck)
        // 1. Verify Egg
        $egg = $this->eggModel->findById($id);
        if (!$egg) {
            http_response_code(404);
            echo json_encode(['error' => 'Egg not found']);
            return;
        }

        // Check ownership if user is logged in (handled by middleware but good to double check context if available)
        // Assuming AuthMiddleware sets user_id in $_REQUEST or similar if we needed it, but middleware is applied.

        // 2. Create Duck (Logic depends on game rules, here simplified)
        $duckCollection = Mongo::getInstance()->getCollection('ducks');
        $newDuck = [
            'owner_id' => $egg['owner_id'] ?? null,
            'origin_egg_id' => new ObjectId($id),
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'level' => 1,
            // Add other default Duck attributes here
        ];

        $insertResult = $duckCollection->insertOne($newDuck);

        // 3. Delete Egg (or mark as hatched)
        $this->eggModel->deleteOne(['_id' => new ObjectId($id)]);

        echo json_encode([
            'message' => 'Egg hatched successfully',
            'duck_id' => (string)$insertResult->getInsertedId()
        ]);
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

        $this->eggModel->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => ['owner_id' => $newOwnerId]]
        );
        echo json_encode(['message' => 'Ownership updated', 'id' => $id]);
    }

    public function adminList()
    {
        $this->list();
    }

    public function stats()
    {
        $collection = Mongo::getInstance()->getCollection('eggs');
        $count = $collection->countDocuments();
        echo json_encode(['total_eggs' => $count]);
    }

    public function getEggById($id)
    {
        $this->get($id);
    }

    public function getEggsByCollection($collectionName)
    {
        $collection = Mongo::getInstance()->getCollection($collectionName);
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $eggs = $collection->find([], ['limit' => $limit])->toArray();
        echo json_encode($this->formatEggs($eggs));
    }

    public function getEggByCollectionAndId($collectionName, $id)
    {
        $collection = Mongo::getInstance()->getCollection($collectionName);
        try {
            $oid = new ObjectId($id);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID']);
            return;
        }

        $egg = $collection->findOne(['_id' => $oid]);
        if (!$egg) {
            http_response_code(404);
            echo json_encode(['error' => 'Egg not found']);
            return;
        }
        echo json_encode($this->formatEgg($egg));
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

        // Ensure owner_id is ObjectId if present
        if (isset($data['owner_id']) && is_string($data['owner_id'])) {
             try {
                 $data['owner_id'] = new ObjectId($data['owner_id']);
             } catch (\Exception $e) {
                 // Ignore if not a valid ObjectId, though it might cause issues later
             }
        }

        $result = $collection->insertOne($data);
        echo json_encode(['message' => 'Created', 'id' => (string)$result->getInsertedId()]);
    }

    private function formatEgg($egg) {
        if (!$egg) return null;
        $egg = (array)$egg;
        if (isset($egg['_id'])) $egg['_id'] = (string)$egg['_id'];
        if (isset($egg['owner_id'])) $egg['owner_id'] = (string)$egg['owner_id'];
        if (isset($egg['origin_egg_id'])) $egg['origin_egg_id'] = (string)$egg['origin_egg_id'];
        return $egg;
    }

    private function formatEggs($eggs) {
        return array_map([$this, 'formatEgg'], $eggs);
    }
}
