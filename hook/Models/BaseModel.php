<?php

namespace Bastivan\UniversalApi\Hook\Models;

use Bastivan\UniversalApi\Hook\Core\Mongo;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;

abstract class BaseModel
{
    protected string $collectionName;
    protected Collection $collection;

    public function __construct()
    {
        if (empty($this->collectionName)) {
            // Deduce collection name from class name (User -> users)
            $className = (new \ReflectionClass($this))->getShortName();
            $this->collectionName = strtolower($className) . 's';
        }
        $this->collection = Mongo::getInstance()->getCollection($this->collectionName);
    }

    public function findOne(array $filter)
    {
        return $this->collection->findOne($filter);
    }

    public function find(array $filter = [], array $options = [])
    {
        return $this->collection->find($filter, $options)->toArray();
    }

    public function create(array $data)
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        }
        $data['updated_at'] = new \MongoDB\BSON\UTCDateTime();

        $result = $this->collection->insertOne($data);
        return $result->getInsertedId();
    }

    public function updateOne(array $filter, array $update)
    {
        if (!isset($update['$set']['updated_at'])) {
            $update['$set']['updated_at'] = new \MongoDB\BSON\UTCDateTime();
        }
        return $this->collection->updateOne($filter, $update);
    }

    public function updateMany(array $filter, $update)
    {
        return $this->collection->updateMany($filter, $update);
    }

    public function deleteOne(array $filter)
    {
        return $this->collection->deleteOne($filter);
    }

    public function findById($id)
    {
        if (!($id instanceof ObjectId)) {
            try {
                $id = new ObjectId($id);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $this->findOne(['_id' => $id]);
    }
}
