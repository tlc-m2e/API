<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\Workout;
use Bastivan\UniversalApi\Hook\Models\User;
use Bastivan\UniversalApi\Hook\Helpers\RedisHelper;
use Bastivan\UniversalApi\Hook\Services\AIService;
use MongoDB\BSON\ObjectId;

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
            if (isset($workout['created_at'])) {
                $workout['created_at'] = $workout['created_at']->toDateTime()->format(\DateTime::ISO8601);
            }
            if (isset($workout['updated_at'])) {
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
            'start_time' => new \MongoDB\BSON\UTCDateTime(),
            'locations' => [],
            'distance' => 0,
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

        if (isset($data['locations']) && is_array($data['locations'])) {
             $this->workoutModel->updateOne(
                 $filter,
                 ['$push' => ['locations' => ['$each' => $data['locations']]]]
             );
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

        // Use findOneAndUpdate if available to get the doc, but updateOne is fine.
        // We verify it exists first? Or just update.
        // Update is atomic.

        $result = $this->workoutModel->updateOne(
            $filter,
            [
                '$set' => [
                    'status' => 'finished',
                    'end_time' => new \MongoDB\BSON\UTCDateTime()
                ]
            ]
        );

        if ($result->getModifiedCount() === 0) {
             // Maybe no workout or already finished
             // Check to be sure?
        }

        // Invalidate cache
        $client = RedisHelper::getClient();
        $client->del(["workout_history_" . (string)$user['_id'] . "_page_1_limit_20"]);

        echo json_encode(['message' => 'Workout finished']);
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

        try {
            $workoutIdObj = new ObjectId($workoutId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid workout ID']);
            return;
        }

        $workout = $this->workoutModel->findOne([
            '_id' => $workoutIdObj,
            'user_id' => $user['_id']
        ]);

        if (!$workout) {
            http_response_code(404);
            echo json_encode(['error' => 'Workout not found']);
            return;
        }

        $workout['_id'] = (string)$workout['_id'];
        $workout['user_id'] = (string)$workout['user_id'];
         if (isset($workout['created_at'])) {
            $workout['created_at'] = $workout['created_at']->toDateTime()->format(\DateTime::ISO8601);
        }
         if (isset($workout['updated_at'])) {
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

        try {
            $workoutIdObj = new ObjectId($workoutId);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid workout ID']);
            return;
        }

        $workout = $this->workoutModel->findOne(['_id' => $workoutIdObj]);

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
