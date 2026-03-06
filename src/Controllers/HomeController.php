<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Controllers;

use Bastivan\UniversalApi\Core\Debugger;
use Bastivan\UniversalApi\Core\Config;
use Bastivan\UniversalApi\Core\Database;

/**
 * Class HomeController
 * Developed by Bastivan Consulting
 */
class HomeController
{
    public function index(): void
    {
        // Simple View Rendering
        require __DIR__ . '/../Views/home.php';
    }

    public function status(): void
    {
        $dbStatus = 'ok';
        $redisStatus = 'ok';
        $httpCode = 200;

        try {
            $connection = Database::getInstance()->getConnection();
            if ($connection instanceof \PDO) {
                $connection->query('SELECT 1');
            } elseif ($connection instanceof \MongoDB\Client) {
                // Perform a lightweight ping command on the database
                $dbName = Config::get('DB_DATABASE');
                $connection->selectDatabase($dbName)->command(['ping' => 1]);
            } else {
                // Unknown connection type
                throw new \Exception("Unknown database connection type");
            }
        } catch (\Exception $e) {
            $dbStatus = 'error';
            $httpCode = 503;
        }

        try {
            \Bastivan\UniversalApi\Core\Cache::getClient()->ping();
        } catch (\Exception $e) {
            $redisStatus = 'error';
            $httpCode = 503; // Service Unavailable
        }

        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $httpCode === 200 ? 'ok' : 'degraded',
            'dependencies' => [
                'database' => $dbStatus,
                'redis' => $redisStatus
            ],
            'version' => '1.0.0'
        ]);
    }
}
