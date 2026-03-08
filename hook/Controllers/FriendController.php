<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Friend;
use TLC\Hook\Models\User;
use TLC\Hook\Models\Workout;

class FriendController extends BaseController
{
    private Friend $friendModel;
    private User $userModel;
    private Workout $workoutModel;

    public function __construct()
    {
        $this->friendModel = new Friend();
        $this->userModel = new User();
        $this->workoutModel = new Workout();
    }

    private function getCurrentUser()
    {
        $userId = $_SERVER['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $user = $this->userModel->findById($userId);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        return $user;
    }

    public function sendRequest()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['pseudo'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Friend pseudo is required']);
            return;
        }

        if ($data['pseudo'] === ($user['pseudo'] ?? null)) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot add yourself as a friend']);
            return;
        }

        $friend = $this->userModel->findOne(['pseudo' => $data['pseudo']]);
        if (!$friend) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            return;
        }

        // Check if a request already exists in either direction
        $existingRequest = $this->friendModel->findOne([
            '$or' => [
                ['user_id_1' => (string)$user['_id'], 'user_id_2' => (string)$friend['_id']],
                ['user_id_1' => (string)$friend['_id'], 'user_id_2' => (string)$user['_id']]
            ]
        ]);

        if ($existingRequest) {
            http_response_code(409);
            echo json_encode(['error' => 'Friend request already exists']);
            return;
        }

        $this->friendModel->create([
            'user_id_1' => (string)$user['_id'],
            'user_id_2' => (string)$friend['_id'],
            'status' => 'pending'
        ]);

        echo json_encode(['message' => 'Friend request sent']);
    }

    public function respondRequest()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['request_id']) || empty($data['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Request ID and action (accept/reject) are required']);
            return;
        }

        $request = $this->friendModel->findById($data['request_id']);

        if (!$request || (string)$request['user_id_2'] !== (string)$user['_id']) {
            http_response_code(404);
            echo json_encode(['error' => 'Friend request not found']);
            return;
        }

        if ($data['action'] === 'accept') {
            $this->friendModel->updateOne(
                ['_id' => $request['_id']],
                ['$set' => ['status' => 'accepted']]
            );
            echo json_encode(['message' => 'Friend request accepted']);
        } else {
            $this->friendModel->deleteOne(['_id' => $request['_id']]);
            echo json_encode(['message' => 'Friend request rejected']);
        }
    }

    public function getFriends()
    {
        $user = $this->getCurrentUser();
        $userId = (string)$user['_id'];

        $friendsData = $this->friendModel->find([
            '$or' => [
                ['user_id_1' => $userId],
                ['user_id_2' => $userId]
            ],
            'status' => 'accepted'
        ]);

        $friendProfiles = [];
        foreach ($friendsData as $rel) {
            $friendId = ($rel['user_id_1'] === $userId) ? $rel['user_id_2'] : $rel['user_id_1'];
            $friend = $this->userModel->findById($friendId);
            if ($friend) {
                $friendProfiles[] = [
                    'id' => (string)$friend['_id'],
                    'pseudo' => $friend['pseudo'] ?? '',
                    'name' => $friend['name'] ?? '',
                ];
            }
        }

        echo json_encode(['friends' => $friendProfiles]);
    }

    public function getPendingRequests()
    {
        $user = $this->getCurrentUser();
        $userId = (string)$user['_id'];

        $pendingData = $this->friendModel->find([
            'user_id_2' => $userId,
            'status' => 'pending'
        ]);

        $pendingProfiles = [];
        foreach ($pendingData as $rel) {
            $friendId = $rel['user_id_1'];
            $friend = $this->userModel->findById($friendId);
            if ($friend) {
                $pendingProfiles[] = [
                    'request_id' => (string)$rel['_id'],
                    'id' => (string)$friend['_id'],
                    'pseudo' => $friend['pseudo'] ?? '',
                    'name' => $friend['name'] ?? '',
                ];
            }
        }

        echo json_encode(['requests' => $pendingProfiles]);
    }

    public function getRunningFriends()
    {
        $user = $this->getCurrentUser();
        $userId = (string)$user['_id'];

        $friendsData = $this->friendModel->find([
            '$or' => [
                ['user_id_1' => $userId],
                ['user_id_2' => $userId]
            ],
            'status' => 'accepted'
        ]);

        $runningFriends = [];
        foreach ($friendsData as $rel) {
            $friendId = ($rel['user_id_1'] === $userId) ? $rel['user_id_2'] : $rel['user_id_1'];

            // Check if friend has an active workout
            $activeWorkout = $this->workoutModel->findOne([
                'user_id' => $friendId,
                'status' => 'running'
            ]);

            if ($activeWorkout) {
                $friend = $this->userModel->findById($friendId);
                if ($friend) {
                    $runningFriends[] = [
                        'id' => (string)$friend['_id'],
                        'pseudo' => $friend['pseudo'] ?? '',
                        'name' => $friend['name'] ?? '',
                        'workout_start' => $activeWorkout['start_time'] ?? null
                    ];
                }
            }
        }

        echo json_encode(['running_friends' => $runningFriends]);
    }
}
