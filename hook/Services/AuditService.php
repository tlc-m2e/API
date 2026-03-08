<?php

namespace TLC\Hook\Services;

use TLC\Core\Database;

class AuditService
{
    public static function log(
        string $action,
        string $resourceType,
        string $resourceId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $db = Database::getInstance()->getConnection();

        $userId = $_SERVER['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO `audit_logs` (
            `id`, `user_id`, `action`, `resource_type`, `resource_id`,
            `old_values`, `new_values`, `ip_address`, `user_agent`
        ) VALUES (
            :id, :user_id, :action, :resource_type, :resource_id,
            :old_values, :new_values, :ip_address, :user_agent
        )";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id' => bin2hex(random_bytes(12)),
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }
}
