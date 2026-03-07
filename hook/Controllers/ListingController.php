<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Listing;
use TLC\Hook\Models\Sale;
use TLC\Hook\Models\Duck;
use TLC\Hook\Models\Egg;
use TLC\Hook\Models\SpendingWallet;
use TLC\Hook\Middleware\AuthMiddleware;
use TLC\Hook\Helpers\RedisHelper;
use MongoDB\BSON\ObjectId;

class ListingController
{
    private Listing $listingModel;
    private Sale $saleModel;
    private Duck $duckModel;
    private Egg $eggModel;
    private SpendingWallet $spendingWalletModel;

    public function __construct()
    {
        $this->listingModel = new Listing();
        $this->saleModel = new Sale();
        $this->duckModel = new Duck();
        $this->eggModel = new Egg();
        $this->spendingWalletModel = new SpendingWallet();
    }

    // GET /api/marketplace/listing/
    public function index()
    {
        // Add Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $skip = ($page - 1) * $limit;

        // Caching
        $client = RedisHelper::getClient();
        $cacheKey = "marketplace_listings_page_{$page}_limit_{$limit}";
        $cached = $client->get($cacheKey);

        if ($cached) {
            echo $cached;
            return;
        }

        $filter = ['status' => 'active'];
        $listings = $this->listingModel->find($filter, ['limit' => $limit, 'skip' => $skip]);

        $response = array_map(function($l) {
            $l['_id'] = (string)$l['_id'];
            if (isset($l['item_id']) && $l['item_id'] instanceof ObjectId) {
                $l['item_id'] = (string)$l['item_id'];
            }
            if (isset($l['seller_id']) && $l['seller_id'] instanceof ObjectId) {
                $l['seller_id'] = (string)$l['seller_id'];
            }
            if (isset($l['buyer_id']) && $l['buyer_id'] instanceof ObjectId) {
                $l['buyer_id'] = (string)$l['buyer_id'];
            }
            // Sanitize dates
             foreach ($l as $key => $value) {
                if ($value instanceof \MongoDB\BSON\UTCDateTime) {
                    $l[$key] = $value->toDateTime()->format('c');
                }
            }
            return $l;
        }, $listings);

        $json = json_encode(['items' => $response, 'page' => $page, 'limit' => $limit]);
        $client->setex($cacheKey, 30, $json); // Cache for 30 seconds
        echo $json;
    }

    // POST /api/marketplace/listing/
    public function create()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user_id'] ?? null;

        if (!$userId || !is_string($userId)) {
             http_response_code(401);
             echo json_encode(['error' => 'Unauthorized']);
             return;
        }

        if (empty($data['item_id']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        try {
            $itemId = new ObjectId($data['item_id']);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid item_id']);
            return;
        }

        // Validate item ownership and type (Duck, Egg, etc.)
        $itemType = $data['item_type'] ?? 'duck'; // default to duck
        $item = null;

        if ($itemType === 'duck') {
            $item = $this->duckModel->findById((string)$itemId);
        } else if ($itemType === 'egg') {
            $item = $this->eggModel->findById((string)$itemId);
        }

        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }

        $itemOwnerId = isset($item['owner_id']) ? (string)$item['owner_id'] : null;
        if ($itemOwnerId !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You do not own this item']);
            return;
        }

        $id = $this->listingModel->create([
            'item_id' => $itemId,
            'seller_id' => new ObjectId($userId),
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'SOL',
            'status' => 'active',
            'created_at' => new \MongoDB\BSON\UTCDateTime()
        ]);

        // Discord Webhook Notification
        $webhookUrl = $_ENV['DISCORD_WEBHOOK_URL'] ?? \TLC\Core\Config::get('DISCORD_WEBHOOK_URL');
        if (!empty($webhookUrl)) {
            $message = [
                'content' => "🦆 **Nouveau Duck sur la Marketplace!** 🦆\nUn nouveau Duck a été mis en vente pour **{$data['price']} {$data['currency']}** !",
            ];

            $options = [
                'http' => [
                    'header'  => "Content-type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => json_encode($message),
                    'ignore_errors' => true,
                ],
            ];
            $context  = stream_context_create($options);
            @file_get_contents($webhookUrl, false, $context);
        }

        // Invalidate cache
        // We can't invalidate all pages easily without a pattern delete (which keys doesn't support well in cluster or efficiently)
        // But since we use short cache (30s), it's acceptable.
        // OR we can rely on TTL.

        echo json_encode(['id' => (string)$id, 'message' => 'Listing created']);
    }

    // POST /api/marketplace/listing/buy/:listingId
    public function buy($listingId)
    {
        $userId = $_REQUEST['user_id'] ?? null;
        if (!$userId || !is_string($userId)) {
             http_response_code(401);
             echo json_encode(['error' => 'Unauthorized']);
             return;
        }

        $listing = $this->listingModel->findById($listingId);
        if (!$listing || $listing['status'] !== 'active') {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found or not active']);
            return;
        }

        if ((string)$listing['seller_id'] === $userId) {
             http_response_code(400);
             echo json_encode(['error' => 'Cannot buy your own listing']);
             return;
        }

        // Handle Payment / Wallet deduction
        $price = (float)$listing['price'];
        $currency = $listing['currency'] ?? 'SOL';
        $walletField = "amountOf" . strtoupper($currency);

        $buyerWallet = $this->spendingWalletModel->findOne(['user_id' => $userId]);
        if (!$buyerWallet || !isset($buyerWallet[$walletField]) || $buyerWallet[$walletField] < $price) {
            http_response_code(400);
            echo json_encode(['error' => 'Not enough funds to buy this item']);
            return;
        }

        // Deduct from buyer
        $this->spendingWalletModel->updateOne(
            ['user_id' => $userId],
            ['$inc' => [$walletField => -$price]]
        );

        // Add to seller
        $this->spendingWalletModel->updateOne(
            ['user_id' => (string)$listing['seller_id']],
            ['$inc' => [$walletField => $price]]
        );

        // Mark as sold
        $this->listingModel->updateOne(['_id' => new ObjectId($listingId)], ['$set' => [
            'status' => 'sold',
            'buyer_id' => new ObjectId($userId),
            'sold_at' => new \MongoDB\BSON\UTCDateTime()
        ]]);

        // Record sale
        $this->saleModel->create([
            'listing_id' => new ObjectId($listingId),
            'seller_id' => $listing['seller_id'],
            'buyer_id' => new ObjectId($userId),
            'price' => $listing['price'],
            'currency' => $listing['currency'],
            'timestamp' => new \MongoDB\BSON\UTCDateTime()
        ]);

        // Transfer item ownership
        $itemIdStr = (string)$listing['item_id'];
        $itemType = $listing['item_type'] ?? 'duck'; // assume duck if not set in listing

        // In this basic version we'll just check duck then egg
        $duck = $this->duckModel->findById($itemIdStr);
        if ($duck) {
            $this->duckModel->updateOne(
                ['_id' => new ObjectId($itemIdStr)],
                ['$set' => ['owner_id' => new ObjectId($userId)]]
            );
        } else {
            $egg = $this->eggModel->findById($itemIdStr);
            if ($egg) {
                $this->eggModel->updateOne(
                    ['_id' => new ObjectId($itemIdStr)],
                    ['$set' => ['owner_id' => new ObjectId($userId)]]
                );
            }
        }

        echo json_encode(['message' => 'Item purchased successfully']);
    }

    // DELETE /api/marketplace/listing/:listingId
    public function delete($listingId)
    {
        $userId = $_REQUEST['user_id'] ?? null;
         if (!$userId || !is_string($userId)) {
             http_response_code(401);
             echo json_encode(['error' => 'Unauthorized']);
             return;
        }

        $listing = $this->listingModel->findById($listingId);
        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found']);
            return;
        }

        if ((string)$listing['seller_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Cancel listing
        $this->listingModel->updateOne(['_id' => new ObjectId($listingId)], ['$set' => ['status' => 'cancelled']]);

        echo json_encode(['message' => 'Listing removed']);
    }
}
