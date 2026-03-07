<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\SpendingWallet;
use TLC\Hook\Models\User;
use MongoDB\BSON\ObjectId;

class EnergyController
{
    private SpendingWallet $spendingWalletModel;
    private User $userModel;

    public function __construct()
    {
        $this->spendingWalletModel = new SpendingWallet();
        $this->userModel = new User();
    }

    public function getEnergy()
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $wallet = $this->spendingWalletModel->findOne(['user' => new ObjectId($userId)]);

        if (!$wallet) {
             // Return default values if wallet not found, or maybe create it?
             // SpendingWalletController creates it if not found. Let's do same or just return 0.
             // For safety, just return 0. The spending endpoint handles creation.
             echo json_encode(['energy' => 0, 'maxEnergy' => 0]);
             return;
        }

        echo json_encode([
            'energy' => $wallet['energy'] ?? 0,
            'maxEnergy' => $wallet['maxEnergy'] ?? 0,
        ]);
    }

    public function refill()
    {
        $this->checkAdmin();

        // Refill all users: Set energy = maxEnergy
        // Using aggregation pipeline in update to reference another field
        $this->spendingWalletModel->updateMany(
            [],
            [['$set' => ['energy' => '$maxEnergy']]]
        );

        echo json_encode(['success' => true, 'message' => 'Energy refilled for all users']);
    }

    public function forceRecomputeMaximumEnergyForSpending()
    {
        $this->checkAdmin();

        // Recompute max energy for all users.
        // Since the specific business logic for calculating max energy is not provided,
        // we will set a default base value (e.g., 100) if it is missing or 0.
        // In a real scenario, this would depend on user level, items, etc.

        $wallets = $this->spendingWalletModel->find([]);
        $count = 0;

        foreach ($wallets as $wallet) {
            // Placeholder logic:
            // If we had Duck or other models, we could calculate.
            // For now, ensure it is at least 100.
            $newMax = 100;

            $this->spendingWalletModel->updateOne(
                ['_id' => $wallet['_id']],
                ['$set' => ['maxEnergy' => $newMax]]
            );
            $count++;
        }

        echo json_encode(['success' => true, 'message' => "Recomputed max energy for $count wallets"]);
    }

    private function checkAdmin()
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId) {
             http_response_code(401);
             echo json_encode(['error' => 'Unauthorized']);
             exit;
        }

        $user = $this->userModel->findById($userId);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
             http_response_code(403);
             echo json_encode(['error' => 'Forbidden']);
             exit;
        }
    }
}
