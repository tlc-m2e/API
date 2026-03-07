<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Models\Collection;

class CollectionController
{
    private Collection $collectionModel;

    public function __construct()
    {
        $this->collectionModel = new Collection();
    }

    public function list()
    {
        if (!in_array('ob_gzhandler', ob_list_handlers())) {
            ob_start("ob_gzhandler");
        }

        $collections = $this->collectionModel->find([], []);

        // Convert ObjectIds to string for JSON output
        $response = array_map(function($collection) {
            $collection = (array)$collection;
            if (isset($collection['_id'])) {
                $collection['_id'] = (string)$collection['_id'];
            }
            return $collection;
        }, $collections);

        echo json_encode($response);
    }
}
