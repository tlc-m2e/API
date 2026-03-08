<?php

namespace TLC\Hook\Models;

use TLC\Core\Database;
use PDO;

use TLC\Hook\Services\AuditService;
use TLC\Hook\Services\EncryptionService;

abstract class BaseModel
{
    protected string $collectionName;
    protected PDO $db;
    protected bool $auditEnabled = true;
    protected array $encryptedFields = [];

    public function __construct()
    {
        if (empty($this->collectionName)) {
            // Deduce table name from class name (User -> users)
            $className = (new \ReflectionClass($this))->getShortName();
            $this->collectionName = strtolower($className) . 's';
        }
        $this->db = Database::getInstance()->getConnection();
    }

    private function buildWhereClause(array $filter): array
    {
        if (empty($filter)) {
            return ['', []];
        }

        $conditions = [];
        $params = [];
        foreach ($filter as $key => $value) {
            // Convert _id to id for SQL compatibility
            $sqlKey = $key === '_id' ? 'id' : $key;
            $conditions[] = "`$sqlKey` = :$sqlKey";
            $params[$sqlKey] = is_bool($value) ? (int)$value : $value;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    public function findOne(array $filter, bool $decrypt = true)
    {
        list($where, $params) = $this->buildWhereClause($filter);
        $sql = "SELECT * FROM `{$this->collectionName}` $where LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['id'])) {
            $result['_id'] = $result['id'];
        }

        if ($result && $decrypt) {
            foreach ($this->encryptedFields as $field) {
                if (isset($result[$field]) && !empty($result[$field])) {
                    $result[$field] = EncryptionService::decrypt($result[$field]);
                }
            }
        }

        // Try to decode JSON metadata if it exists
        if ($result && isset($result['metadata']) && is_string($result['metadata'])) {
            $decoded = json_decode($result['metadata'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result = array_merge($result, $decoded);
            }
        }

        return $result ?: null;
    }

    public function find(array $filter = [], array $options = [], bool $decrypt = true)
    {
        list($where, $params) = $this->buildWhereClause($filter);

        $sql = "SELECT * FROM `{$this->collectionName}` $where";

        if (isset($options['sort'])) {
            $sorts = [];
            foreach ($options['sort'] as $key => $dir) {
                $sqlKey = $key === '_id' ? 'id' : $key;
                $direction = $dir == -1 ? 'DESC' : 'ASC';
                $sorts[] = "`$sqlKey` $direction";
            }
            if (!empty($sorts)) {
                $sql .= " ORDER BY " . implode(', ', $sorts);
            }
        }

        if (isset($options['limit'])) {
            $sql .= " LIMIT " . (int)$options['limit'];
        }

        if (isset($options['skip'])) {
            $sql .= " OFFSET " . (int)$options['skip'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            if (isset($result['id'])) {
                $result['_id'] = $result['id'];
            }

            if ($decrypt) {
                foreach ($this->encryptedFields as $field) {
                    if (isset($result[$field]) && !empty($result[$field])) {
                        $result[$field] = EncryptionService::decrypt($result[$field]);
                    }
                }
            }

            if (isset($result['metadata']) && is_string($result['metadata'])) {
                $decoded = json_decode($result['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result = array_merge($result, $decoded);
                }
            }
        }

        return $results;
    }

    private function generateUuid(): string
    {
        // Simple UUID v4 generation compatible with 24 char string if needed,
        // but we'll use a 24 char hex string to match MongoDB's ObjectId length
        return bin2hex(random_bytes(12));
    }

    public function create(array $data)
    {
        if (!isset($data['id']) && !isset($data['_id'])) {
            $data['id'] = $this->generateUuid();
        } else if (isset($data['_id'])) {
            $data['id'] = $data['_id'];
            unset($data['_id']);
        }

        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        foreach ($this->encryptedFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = EncryptionService::encrypt($data[$field]);
            }
        }

        // Handle possible JSON metadata column logic here if needed by descendants,
        // but for now we'll just insert columns directly.
        // If a property isn't a known column, a more advanced ORM would pack it into metadata.
        // To keep it simple, we assume $data matches table columns.

        $keys = array_keys($data);
        $fields = '`' . implode('`, `', $keys) . '`';
        $placeholders = ':' . implode(', :', $keys);

        $sql = "INSERT INTO `{$this->collectionName}` ($fields) VALUES ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        if ($this->auditEnabled) {
            AuditService::log('create', $this->collectionName, $data['id'], null, $data);
        }

        return $data['id'];
    }

    public function updateOne(array $filter, array $update)
    {
        list($where, $params) = $this->buildWhereClause($filter);
        if (empty($where)) {
            return false; // Refuse to update without filter in updateOne
        }

        $setClause = [];
        $updateData = [];

        // Handle MongoDB style operators
        if (isset($update['$set'])) {
            foreach ($update['$set'] as $key => $value) {
                $sqlKey = $key === '_id' ? 'id' : $key;

                // Handle dot notation for JSON metadata update (simplified)
                if (strpos($sqlKey, '.') !== false) {
                    $parts = explode('.', $sqlKey);
                    if ($parts[0] === 'attributes') { // Specific to LevelUpController
                        // We would need to update JSON inside metadata here
                        // MariaDB: JSON_SET(metadata, '$.attributes.key', value)
                        // Simplified: we will just skip this complexity and rely on simple fields
                    }
                } else {
                    $setClause[] = "`$sqlKey` = :update_$sqlKey";
                    $updateData["update_$sqlKey"] = $value;
                }
            }
        }

        if (isset($update['$inc'])) {
            foreach ($update['$inc'] as $key => $value) {
                $sqlKey = $key === '_id' ? 'id' : $key;
                if (strpos($sqlKey, '.') !== false) {
                    continue; // Skip dot notation for inc for now
                }
                $setClause[] = "`$sqlKey` = `$sqlKey` + :inc_$sqlKey";
                $updateData["inc_$sqlKey"] = $value;
            }
        }

        if (isset($update['$push'])) {
            // Simplified push logic, not fully supported in simple SQL
        }

        if (empty($setClause)) {
            // If it's a direct array, act like $set
            foreach ($update as $key => $value) {
                if (strpos($key, '$') === 0) continue;
                $sqlKey = $key === '_id' ? 'id' : $key;

                if (in_array($sqlKey, $this->encryptedFields)) {
                    $value = EncryptionService::encrypt($value);
                }

                $setClause[] = "`$sqlKey` = :update_$sqlKey";
                $updateData["update_$sqlKey"] = $value;
            }
        } else {
            // we already looped over `$set`, let's check for encrypted fields there
            foreach ($updateData as $key => &$val) {
                $sqlKey = preg_replace('/^update_/', '', $key);
                if (in_array($sqlKey, $this->encryptedFields) && is_string($val)) {
                    $val = EncryptionService::encrypt($val);
                }
            }
        }

        if (empty($setClause)) return false;

        $sql = "UPDATE `{$this->collectionName}` SET " . implode(', ', $setClause) . " $where";

        // Get old values before update without decrypting
        $oldRecord = null;
        if ($this->auditEnabled) {
            $oldRecord = $this->findOne($filter, false);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($updateData, $params));

        if ($this->auditEnabled && $oldRecord) {
            // Find what changed
            $newValues = [];
            foreach ($updateData as $key => $val) {
                // simple mapping for now
                $cleanKey = preg_replace('/^(update_|inc_)/', '', $key);
                $newValues[$cleanKey] = $val;
            }
            // Add ID for context
            AuditService::log('update', $this->collectionName, $oldRecord['id'] ?? 'unknown', $oldRecord, $newValues);
        }

        // Mocking MongoDB UpdateResult
        return new class($stmt->rowCount()) {
            private int $modifiedCount;
            public function __construct(int $count) { $this->modifiedCount = $count; }
            public function getModifiedCount(): int { return $this->modifiedCount; }
        };
    }

    public function updateMany(array $filter, $update)
    {
        // Same logic as updateOne, just no LIMIT 1 (which we didn't add anyway)
        return $this->updateOne($filter, $update);
    }

    public function deleteOne(array $filter)
    {
        list($where, $params) = $this->buildWhereClause($filter);
        if (empty($where)) {
            return false;
        }

        $oldRecord = null;
        if ($this->auditEnabled) {
            $oldRecord = $this->findOne($filter, false);
        }

        $sql = "DELETE FROM `{$this->collectionName}` $where LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);

        if ($this->auditEnabled && $result && $oldRecord) {
            AuditService::log('delete', $this->collectionName, $oldRecord['id'] ?? 'unknown', $oldRecord, null);
        }

        return $result;
    }

    public function findById($id)
    {
        return $this->findOne(['id' => (string)$id]);
    }
}
