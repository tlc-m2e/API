<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Hook\Helpers;

use Predis\Client;

class RedisHelper
{
    private static ?Client $client = null;

    public static function getClient(): Client
    {
        if (self::$client === null) {
            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = $_ENV['REDIS_PORT'] ?? 6379;
            $password = $_ENV['REDIS_PASSWORD'] ?? null;

            $config = [
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
            ];

            if (!empty($password) && $password !== 'null') {
                $config['password'] = $password;
            }

            self::$client = new Client($config);
        }

        return self::$client;
    }
}
