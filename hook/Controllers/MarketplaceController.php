<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Listing;
use TLC\Hook\Models\Pot;
use TLC\Hook\Models\Sale;
use MongoDB\BSON\ObjectId;

class MarketplaceController
{
    private Listing $listingModel;
    private Pot $potModel;
    private Sale $saleModel;

    public function __construct()
    {
        $this->listingModel = new Listing();
        $this->potModel = new Pot();
        $this->saleModel = new Sale();
    }

    public function getListings()
    {
        $filter = ['status' => 'active']; // Default to active listings
        $listings = $this->listingModel->find($filter);

        $response = array_map(function($l) {
            $l['_id'] = (string)$l['_id'];
            return $l;
        }, $listings);

        echo json_encode($response);
    }

    public function createListing()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Basic validation
        if (empty($data['item_id']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            return;
        }

        $id = $this->listingModel->create([
            'item_id' => new ObjectId($data['item_id']),
            'seller_id' => new ObjectId($_REQUEST['user_id']), // From Auth Middleware
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'SOL',
            'status' => 'active'
        ]);

        echo json_encode(['id' => (string)$id, 'message' => 'Listing created']);
    }

    public function getPots()
    {
        $pots = $this->potModel->find([]);
         $response = array_map(function($p) {
            $p['_id'] = (string)$p['_id'];
            return $p;
        }, $pots);
        echo json_encode($response);
    }
}
