<?php

namespace TLC\Hook\Controllers;

use TLC\Hook\Core\Mongo;
use MongoDB\BSON\UTCDateTime;

class StatsController extends BaseController
{
    /**
     * Stats génériques pour une entité donnée.
     * GET /stats/entity/:entity
     */
    public function getEntityStats($entity)
    {
        // Validation simple du nom de l'entité pour éviter les caractères dangereux
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $entity)) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid entity name']);
             return;
        }

        try {
            $collection = Mongo::getInstance()->getCollection($entity);

            $total = $collection->countDocuments();

            $stats = [
                'entity' => $entity,
                'total' => $total,
            ];

            // Calcul des stats basées sur le temps (si 'created_at' existe)
            // On utilise milliseconds pour UTCDateTime
            $now = time();
            $oneDayAgo = ($now - 24 * 60 * 60) * 1000;
            $sevenDaysAgo = ($now - 7 * 24 * 60 * 60) * 1000;
            $thirtyDaysAgo = ($now - 30 * 24 * 60 * 60) * 1000;

            $stats['last_24h'] = $collection->countDocuments(['created_at' => ['$gte' => new UTCDateTime($oneDayAgo)]]);
            $stats['last_7d'] = $collection->countDocuments(['created_at' => ['$gte' => new UTCDateTime($sevenDaysAgo)]]);
            $stats['last_30d'] = $collection->countDocuments(['created_at' => ['$gte' => new UTCDateTime($thirtyDaysAgo)]]);

            echo json_encode($stats);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error retrieving stats: ' . $e->getMessage()]);
        }
    }
}
