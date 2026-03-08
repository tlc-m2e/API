<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Notification;
use TLC\Hook\Models\User;
use TLC\Hook\Models\Activity;
use TLC\Hook\Middleware\AuthMiddleware;
use MongoDB\BSON\ObjectId;

class NotificationController extends BaseController
{
    private Notification $notificationModel;
    private User $userModel;
    private Activity $activityModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->userModel = new User();
        $this->activityModel = new Activity();
    }

    private function getCurrentUser()
    {
        // AuthMiddleware verifies the token and sets $_REQUEST['user_id'].
        // This relies on the middleware running before the controller.
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

    // GET /notifications/onlineUsers
    public function onlineUsers()
    {
        $this->requirePermission("viewLogs");

        // Count users with activity in the last 5 minutes.
        $fiveMinutesAgo = new \MongoDB\BSON\UTCDateTime((time() - 300) * 1000);

        $pipeline = [
            [
                '$match' => [
                    'created_at' => ['$gte' => $fiveMinutesAgo]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$user_id'
                ]
            ],
            [
                '$count' => 'online_count'
            ]
        ];

        try {
            $result = $this->activityModel->aggregate($pipeline);
            $count = 0;
            foreach ($result as $r) {
                $count = $r['online_count'];
            }
            echo json_encode(['online_users' => $count]);
        } catch (\Exception $e) {
            // Fallback if Activity collection issues
            error_log("Error in onlineUsers: " . $e->getMessage());
            echo json_encode(['online_users' => 0, 'error' => 'Could not retrieve online users']);
        }
    }

    // POST /notifications/notify/users
    public function notifyUsers()
    {
        $this->requirePermission("updateConfig");
        $data = json_decode(file_get_contents('php://input'), true);

        $userIds = $data['user_ids'] ?? [];
        $message = $data['message'] ?? '';
        $title = $data['title'] ?? 'Notification';
        $type = $data['type'] ?? 'info';

        if (!is_array($userIds)) {
             http_response_code(400);
             echo json_encode(['error' => 'user_ids must be an array']);
             return;
        }

        if (empty($userIds) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'user_ids and message are required']);
            return;
        }

        $notifications = [];
        foreach ($userIds as $uid) {
            try {
                $notifications[] = [
                    'user_id' => new ObjectId($uid),
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'read' => false,
                    'created_at' => new \MongoDB\BSON\UTCDateTime()
                ];
            } catch (\Exception $e) {
                // Ignore invalid IDs
            }
        }

        if (!empty($notifications)) {
            $this->notificationModel->insertMany($notifications);
        }

        echo json_encode(['message' => 'Notifications sent to ' . count($notifications) . ' users']);
    }

    // POST /notifications/notify/groups
    public function notifyGroups()
    {
        $this->requirePermission("updateConfig");
        $data = json_decode(file_get_contents('php://input'), true);

        $groups = $data['groups'] ?? []; // e.g. ['admin', 'user']
        $message = $data['message'] ?? '';
        $title = $data['title'] ?? 'Notification';
        $type = $data['type'] ?? 'info';

        if (!is_array($groups)) {
             http_response_code(400);
             echo json_encode(['error' => 'groups must be an array']);
             return;
        }

        if (empty($groups) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'groups and message are required']);
            return;
        }

        // Find users in these groups (roles)
        $users = $this->userModel->find(['role' => ['$in' => $groups]]);

        $notifications = [];
        foreach ($users as $user) {
             $notifications[] = [
                'user_id' => $user['_id'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'read' => false,
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ];
        }

        if (!empty($notifications)) {
            $this->notificationModel->insertMany($notifications);
        }

        echo json_encode(['message' => 'Notifications sent to ' . count($notifications) . ' users in groups ' . implode(', ', $groups)]);
    }
}
