<?php

declare(strict_types=1);

namespace TLC\Core;

use Predis\Client;

/**
 * Class Cache
 * Developed by THE LIFE COINCOIN
 *
 * Redis Cache Wrapper.
 */
class Cache
{
    private static ?Client $client = null;

    public static function init(): void
    {
        if (self::$client === null) {
            $scheme = Config::get('REDIS_SCHEME', 'tcp');
            $host = Config::get('REDIS_HOST', '127.0.0.1');
            $port = Config::get('REDIS_PORT', 6379);
            $password = Config::get('REDIS_PASSWORD', null);

            $options = [];
            if ($password) {
                $options['parameters']['password'] = $password;
            }

            self::$client = new Client([
                'scheme' => $scheme,
                'host'   => $host,
                'port'   => $port,
            ], $options);
        }
    }

    public static function getClient(): Client
    {
        if (self::$client === null) {
            self::init();
        }
        return self::$client;
    }

    public static function get(string $key): ?string
    {
        return self::getClient()->get($key);
    }

    public static function set(string $key, string $value, int $ttl = 3600): void
    {
        self::getClient()->setex($key, $ttl, $value);
    }
}
