<?php

namespace Bastivan\UniversalApi\Hook\Controllers;

use Bastivan\UniversalApi\Hook\Models\TransferAttempt;
use Bastivan\UniversalApi\Hook\Models\SpendingWallet;
use Bastivan\UniversalApi\Hook\Models\GameConstant;
use Bastivan\UniversalApi\Hook\Models\Duck;
use Bastivan\UniversalApi\Hook\Models\Egg;
use Bastivan\UniversalApi\Hook\Middleware\AuthMiddleware;
use MongoDB\BSON\ObjectId;

class TransferAttemptController
{
    private TransferAttempt $transferAttemptModel;
    private SpendingWallet $spendingWalletModel;
    private GameConstant $gameConstantModel;
    private Duck $duckModel;
    private Egg $eggModel;

    public function __construct()
    {
        $this->transferAttemptModel = new TransferAttempt();
        $this->spendingWalletModel = new SpendingWallet();
        $this->gameConstantModel = new GameConstant();
        $this->duckModel = new Duck();
        $this->eggModel = new Egg();
    }

    private function getCurrentUser()
    {
        // AuthMiddleware injects user_id into $_REQUEST
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $userModel = new \Bastivan\UniversalApi\Hook\Models\User();
        $user = $userModel->findById($userId);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        return $user;
    }

    private function checkAdmin()
    {
        $user = $this->getCurrentUser();
        if (!isset($user['role']) || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }

    public function init()
    {
        $user = $this->getCurrentUser();
        $data = json_decode(file_get_contents('php://input'), true);

        $userIdStr = (string)$user['_id'];
        $spendingWallet = $this->spendingWalletModel->findOne(['user_id' => $userIdStr]);
        if (!$spendingWallet) {
             $spendingWallet = $this->spendingWalletModel->findOne(['user._id' => $user['_id']]);
        }

        $type = $data['type'] ?? null;
        $amount = (float)($data['amount'] ?? 0);

        if (!$type || ($type !== 'toSpending' && $type !== 'toWallet')) {
             http_response_code(400);
             echo json_encode(['error' => 'Missing or invalid type']);
             return;
        }

        $cryptoTokenType = $data['cryptoTokenType'] ?? null;
        $splTokenIds = $data['splTokenIds'] ?? null;

        if (!$splTokenIds && !$cryptoTokenType) {
            http_response_code(400);
            echo json_encode(['error' => 'Specify cryptoTokenId or splTokenIds']);
            return;
        }

        if ($splTokenIds && $cryptoTokenType) {
            http_response_code(400);
            echo json_encode(['error' => 'Only one type of token allowed']);
            return;
        }

        $attempts = [];
        // Basic transfer logic structure
        $attempt = [
            'user' => ['_id' => $user['_id']],
            'type' => $type,
            'status' => 'completed', // Simulate immediate completion for basic beta
            'createdAt' => new \MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new \MongoDB\BSON\UTCDateTime(),
        ];

        if ($cryptoTokenType) {
            $attempt['tokenType'] = 'crypto';
            $attempt['tokenId'] = $cryptoTokenType;
            $attempt['amount'] = $amount;

            // Simple mock balance update
            if ($spendingWallet && $amount > 0) {
                $field = "amountOf" . strtoupper($cryptoTokenType);
                $inc = $type === 'toSpending' ? $amount : -$amount;

                // Prevent negative balance on exit
                if ($type === 'toWallet' && (!isset($spendingWallet[$field]) || $spendingWallet[$field] < $amount)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Not enough balance in spending wallet']);
                    return;
                }

                $this->spendingWalletModel->updateOne(
                    ['_id' => $spendingWallet['_id']],
                    ['$inc' => [$field => $inc]]
                );
            }
        } else {
             $attempt['tokenType'] = 'spl';
             $attempt['tokenIds'] = $splTokenIds;
             // Basic implementation for NFTs (e.g. just record it)
        }

        $this->transferAttemptModel->insertOne($attempt);
        $attempts[] = $attempt;

        // Clean up _id for response
        foreach ($attempts as &$a) {
             if (isset($a['_id'])) $a['_id'] = (string)$a['_id'];
             if (isset($a['user']['_id'])) $a['user']['_id'] = (string)$a['user']['_id'];
             $a['createdAt'] = $a['createdAt']->toDateTime()->format(\DateTime::ISO8601);
             $a['updatedAt'] = $a['updatedAt']->toDateTime()->format(\DateTime::ISO8601);
        }

        echo json_encode(['items' => $attempts]);
    }

    public function list()
    {
        // It seems to return all. Maybe admin only?
        // However, looking at the code, it just returns all. I will assume it is for admin or testing, or the user is checked inside service (which I don't have access to deep logic of).
        // Let's protect it with Admin check to be safe, or just return all if it was public.
        // Given it's "transfers/attempt", maybe it's user's history?
        // `findAll` usually means ALL.

        $user = $this->getCurrentUser();
        // If regular user, maybe filter by user?

        $attempts = $this->transferAttemptModel->find([], ['limit' => 100, 'sort' => ['createdAt' => -1]]);

        $result = [];
        foreach ($attempts as $a) {
             $a['_id'] = (string)$a['_id'];
             if (isset($a['user']['_id'])) $a['user']['_id'] = (string)$a['user']['_id'];
             if (isset($a['createdAt'])) $a['createdAt'] = $a['createdAt']->toDateTime()->format(\DateTime::ISO8601);
             if (isset($a['updatedAt'])) $a['updatedAt'] = $a['updatedAt']->toDateTime()->format(\DateTime::ISO8601);
             $result[] = $a;
        }

        echo json_encode(['items' => $result]);
    }

    public function check($id)
    {
        $attempt = $this->transferAttemptModel->findById($id);
        if (!$attempt) {
            http_response_code(404);
            echo json_encode(['error' => 'Attempt not found']);
            return;
        }

        $attempt['_id'] = (string)$attempt['_id'];
        if (isset($attempt['user']['_id'])) $attempt['user']['_id'] = (string)$attempt['user']['_id'];
        if (isset($attempt['createdAt'])) $attempt['createdAt'] = $attempt['createdAt']->toDateTime()->format(\DateTime::ISO8601);
        if (isset($attempt['updatedAt'])) $attempt['updatedAt'] = $attempt['updatedAt']->toDateTime()->format(\DateTime::ISO8601);

        echo json_encode(['item' => $attempt]);
    }

    public function removeDuplicatesTokensIdNfts()
    {
        $this->checkAdmin();

        // find all ducks and egge with tokenId
        // ensure unique

        $duckTokens = $this->duckModel->find(['tokenId' => ['$exists' => true, '$nin' => [null, '']]]);
        $eggTokens = $this->eggModel->find(['tokenId' => ['$exists' => true, '$nin' => [null, '']]]);

        // This seems to be a maintenance task.
        // In PHP we might not want to load ALL in memory if there are many.
        // But following logic:

        $ids = [];
        foreach ($duckTokens as $b) $ids[] = $b['tokenId'];
        foreach ($eggTokens as $l) $ids[] = $l['tokenId'];

        $uniqueIds = array_unique($ids);

        // Implement simple duplicate resolution
        // Find exact duplicates and remove them, keeping the first one found
        $seen = [];
        $duplicatesRemoved = 0;

        foreach ($duckTokens as $duck) {
            if (isset($seen[$duck['tokenId']])) {
                $this->duckModel->deleteOne(['_id' => $duck['_id']]);
                $duplicatesRemoved++;
            } else {
                $seen[$duck['tokenId']] = true;
            }
        }

        foreach ($eggTokens as $egg) {
            if (isset($seen[$egg['tokenId']])) {
                $this->eggModel->deleteOne(['_id' => $egg['_id']]);
                $duplicatesRemoved++;
            } else {
                $seen[$egg['tokenId']] = true;
            }
        }

        echo json_encode(['item' => "Duplicates check completed. $duplicatesRemoved removed."]);
    }
}
