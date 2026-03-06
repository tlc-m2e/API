<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\Activity;
use Bastivan\UniversalApi\Hook\Models\SpendingWallet;
use MongoDB\BSON\ObjectId;

class ActivityController
{
    private Activity $activityModel;
    private SpendingWallet $spendingWalletModel;

    public function __construct()
    {
        $this->activityModel = new Activity();
        $this->spendingWalletModel = new SpendingWallet();
    }

    public function index()
    {
        $userId = $_REQUEST['user_id'];
        $userObjectId = new ObjectId($userId);

        $spendingWallet = $this->spendingWalletModel->findOne(['user' => $userObjectId]);

        if (!$spendingWallet) {
            http_response_code(404);
            echo json_encode(['error' => 'Spending wallet not found for the connected user.']);
            return;
        }

        $activities = $this->activityModel->find(['spendingWallet' => $spendingWallet['_id']]);

        // Convert ObjectIds to strings and format dates for JSON output
        $items = array_map(function($activity) {
            $activity['_id'] = (string)$activity['_id'];
            if (isset($activity['spendingWallet']) && $activity['spendingWallet'] instanceof ObjectId) {
                $activity['spendingWallet'] = (string)$activity['spendingWallet'];
            }
             if (isset($activity['created_at']) && $activity['created_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                $activity['created_at'] = $activity['created_at']->toDateTime()->format(\DateTime::ATOM);
            }
            if (isset($activity['updated_at']) && $activity['updated_at'] instanceof \MongoDB\BSON\UTCDateTime) {
                $activity['updated_at'] = $activity['updated_at']->toDateTime()->format(\DateTime::ATOM);
            }
            return $activity;
        }, $activities);

        echo json_encode(['items' => $items]);
    }
}
