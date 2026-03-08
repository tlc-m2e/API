<?php

namespace TLC\Hook\Controllers;

use TLC\Core\Database;
use TLC\Hook\Models\User;
use PDO;

class BaseController
{
    protected function requirePermission(string $permission): void
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $userModel = new User();
        $user = $userModel->findById($userId);

        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: User not found']);
            exit;
        }

        $role = $user['role'] ?? 'user';

        $db = Database::getInstance()->getConnection();
        $sql = "
            SELECT p.name
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role = :role AND p.name = :permission
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['role' => $role, 'permission' => $permission]);
        $hasPermission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hasPermission) {
            \TLC\Core\Logger::critical('Security Alert: Unauthorized access attempt', ['user_id' => $userId, 'required_permission' => $permission]);
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Missing permission ' . $permission]);
            exit;
        }
    }

    protected function validateMetadata(array $data, array $schema): bool
    {
        foreach ($schema as $field => $type) {
            if (!isset($data[$field])) {
                return false;
            }
            if ($type === 'int' && !is_int($data[$field])) {
                return false;
            }
            if ($type === 'string' && !is_string($data[$field])) {
                return false;
            }
            if ($type === 'bool' && !is_bool($data[$field])) {
                return false;
            }
            if ($type === 'array' && !is_array($data[$field])) {
                return false;
            }
        }
        return true;
    }

    protected function sanitizeOutput(array $data): array
    {
        $sensitiveFields = ['password', 'private_key', 'secret', 'internal_log', 'token'];
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeOutput($value);
            }
        }
        return $data;
    }
}
