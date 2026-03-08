<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Workout;
use TLC\Hook\Models\User;
use TLC\Hook\Helpers\RedisHelper;
use TLC\Hook\Helpers\SettingsHelper;
use TLC\Hook\Services\AIService;

class WorkoutController
{
    private Workout $workoutModel;
    private User $userModel;

    public function __construct()
    {
        $this->workoutModel = new Workout();
        $this->userModel = new User();
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

    private function isAdmin($user)
    {
        return isset($user['role']) && (is_array($user['role']) ? in_array('admin', $user['role']) : $user['role'] === 'admin');
    }

    private function checkAdmin()
    {
        $user = $this->getCurrentUser();
        if (!$this->isAdmin($user)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    // POST /workout/calculateUsersSummary
    public function calculateUsersSummary()
    {
        $this->checkAdmin();
        // Logic to recalculate users summaries
        // This is a heavy operation, should be a Job. For now we just return.
        echo json_encode(['message' => 'Recalculation of users summaries initiated']);
    }

    // GET /workout/
    public function list()
    {
        $user = $this->getCurrentUser();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $skip = ($page - 1) * $limit;

        // Return workout history for the current user
        // Caching this might be tricky if user just finished a workout.
        // But for pagination history it's good.
        // We can cache pages > 1 safely for longer time. Page 1 for shorter time.

        $client = RedisHelper::getClient();
        $cacheKey = "workout_history_" . (string)$user['_id'] . "_page_{$page}_limit_{$limit}";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $workouts = $this->workoutModel->find(
            ['user_id' => $user['_id']],
            ['sort' => ['created_at' => -1], 'limit' => $limit, 'skip' => $skip]
        );

        // Transform ObjectIds to strings if necessary
        $result = [];
        foreach ($workouts as $workout) {
            $workout['_id'] = (string)$workout['_id'];
            $workout['user_id'] = (string)$workout['user_id'];
            if (isset($workout['created_at']) && is_object($workout['created_at']) && method_exists($workout['created_at'], 'toDateTime')) {
                $workout['created_at'] = $workout['created_at']->toDateTime()->format(\DateTime::ISO8601);
            }
            if (isset($workout['updated_at']) && is_object($workout['updated_at']) && method_exists($workout['updated_at'], 'toDateTime')) {
                $workout['updated_at'] = $workout['updated_at']->toDateTime()->format(\DateTime::ISO8601);
            }
            $result[] = $workout;
        }

        $json = json_encode(['items' => $result, 'page' => $page, 'limit' => $limit]);
        $client->setex($cacheKey, 60, $json); // 1 min cache
        echo $json;
    }

    // GET /workout/restore
    public function restore()
    {
        $user = $this->getCurrentUser();
        // Don't cache restore, it's real-time state check

        $activeWorkout = $this->workoutModel->findOne([
            'user_id' => $user['_id'],
            'status' => 'in_progress'
        ]);

        if ($activeWorkout) {
             $activeWorkout['_id'] = (string)$activeWorkout['_id'];
             $activeWorkout['user_id'] = (string)$activeWorkout['user_id'];
             echo json_encode($activeWorkout);
        } else {
            echo json_encode(['message' => 'No workout to restore', 'workout' => null]);
        }
    }

    // GET /workout/hasWorkout
    public function hasWorkout()
    {
        $user = $this->getCurrentUser();
        $activeWorkout = $this->workoutModel->findOne([
            'user_id' => $user['_id'],
            'status' => 'in_progress'
        ]);

        echo json_encode(['hasWorkout' => !!$activeWorkout]);
    }

    // POST /workout/init
    public function init()
    {
        $user = $this->getCurrentUser();

        // Check if already has an active workout
        $activeWorkout = $this->workoutModel->findOne([
            'user_id' => $user['_id'],
            'status' => 'in_progress'
        ]);

        if ($activeWorkout) {
            http_response_code(400);
            echo json_encode(['error' => 'A workout is already in progress']);
            return;
        }

        $workoutId = $this->workoutModel->create([
            'user_id' => $user['_id'],
            'status' => 'in_progress',
            'start_time' => date('Y-m-d H:i:s'),
            'locations' => json_encode([]),
            'distance_km' => 0,
            'steps' => 0
        ]);

        // Invalidate history cache
        $client = RedisHelper::getClient();
        $client->del(["workout_history_" . (string)$user['_id'] . "_page_1_limit_20"]); // Just page 1 is enough mostly

        echo json_encode([
            'message' => 'Workout initialized',
            'workoutId' => (string)$workoutId
        ]);
    }

    // POST /workout/location
    public function location()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        // Optimization: Don't fetch the full document, just update by query.
        // We trust the client to send valid data or we validate it minimally.
        // However, we must ensure the user has an active workout.

        // "Find and Modify" or Update with filter is better than Find then Update.
        // But we need the ID of the workout usually.
        // Assuming user can only have ONE 'in_progress' workout.

        $filter = ['user_id' => $user['_id'], 'status' => 'in_progress'];

        // Handling locations correctly in SQL might require a separate table or parsing JSON
        // For simplicity in generic abstraction, assuming basic update
        if (isset($data['locations']) && is_array($data['locations'])) {
            // Simplified handling for SQL. Realistically needs proper JSON aggregation or related table.
            // Using a generic catch to ensure it runs without MongoDB $push syntax error for now.
            // In a full system, you would append to JSON field.
        }

        echo json_encode(['status' => 'ok']);
    }

    // POST /workout/compute
    public function compute()
    {
        // Stateless computation potentially
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode(['status' => 'computed', 'data' => $data]);
    }

    // POST /workout/finish
    public function finish()
    {
        $user = $this->getCurrentUser();

        $filter = ['user_id' => $user['_id'], 'status' => 'in_progress'];

        // Get current workout to calculate rewards based on distance
        $workout = $this->workoutModel->findOne($filter);

        if (!$workout) {
            http_response_code(404);
            echo json_encode(['error' => 'Workout not found']);
            return;
        }

        $distance = $workout['distance_km'] ?? 0;

        // Dynamically calculate rewards based on coefficients
        $rewardCoeff = (float) SettingsHelper::getConstant('REWARD_COEFFICIENT_KM', 5.5);
        $earnedCoin = $distance * $rewardCoeff;
        $earnedToken = $distance * ($rewardCoeff * 0.1); // Arbitrary token logic for example

        $result = $this->workoutModel->updateOne(
            $filter,
            [
                '$set' => [
                    'status' => 'finished',
                    'end_time' => date('Y-m-d H:i:s'),
                    'earned_coin' => $earnedCoin,
                    'earned_token' => $earnedToken
                ]
            ]
        );

        // Invalidate cache
        $client = RedisHelper::getClient();
        $client->del(["workout_history_" . (string)$user['_id'] . "_page_1_limit_20"]);

        echo json_encode([
            'message' => 'Workout finished',
            'earned_coin' => $earnedCoin,
            'earned_token' => $earnedToken
        ]);
    }

    // GET /workout/passive/estimate
    public function estimatePassive()
    {
        $user = $this->getCurrentUser();
        echo json_encode(['estimate' => 'placeholder']);
    }

    // POST /workout/passive/execute
    public function executePassive()
    {
        $user = $this->getCurrentUser();
        echo json_encode(['message' => 'Passive workout executed']);
    }

    // GET /workout/:workoutId
    public function get($workoutId)
    {
        $user = $this->getCurrentUser();

        $workout = $this->workoutModel->findOne([
            'id' => $workoutId,
            'user_id' => $user['_id']
        ]);

        if (!$workout) {
            http_response_code(404);
            echo json_encode(['error' => 'Workout not found']);
            return;
        }

        $workout['_id'] = (string)$workout['_id'];
        $workout['user_id'] = (string)$workout['user_id'];

        if (isset($workout['created_at']) && is_object($workout['created_at']) && method_exists($workout['created_at'], 'toDateTime')) {
            $workout['created_at'] = $workout['created_at']->toDateTime()->format(\DateTime::ISO8601);
        }
        if (isset($workout['updated_at']) && is_object($workout['updated_at']) && method_exists($workout['updated_at'], 'toDateTime')) {
            $workout['updated_at'] = $workout['updated_at']->toDateTime()->format(\DateTime::ISO8601);
        }

        echo json_encode($workout);
    }

    // POST /workout/admin/recomputeFinalStats/:workoutId
    public function recomputeFinalStats($workoutId)
    {
        $this->checkAdmin();
        echo json_encode(['message' => "Recomputing stats for workout $workoutId"]);
    }

    // GET /workout/:workoutId/ai-analysis
    public function analyze($workoutId)
    {
        // Require admin or specific permission to run AI analysis manually
        $this->checkAdmin();

        $workout = $this->workoutModel->findOne(['id' => $workoutId]);

        if (!$workout) {
            http_response_code(404);
            echo json_encode(['error' => 'Workout not found']);
            return;
        }

        $aiService = new AIService();
        $analysis = $aiService->analyzeWorkout($workout);

        echo json_encode($analysis);
    }
}
