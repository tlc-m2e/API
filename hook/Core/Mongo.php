<?php

namespace TLC\Hook\Core;

use TLC\Core\Config;
use MongoDB\Client;
use MongoDB\Collection;

class Mongo
{
    private static ?Mongo $instance = null;
    private Client $client;
    private string $databaseName;

    private function __construct()
    {
        $uri = Config::get('MONGODB_URI');
        if (empty($uri)) {
             $host = Config::get('DB_HOST', '127.0.0.1');
             $port = Config::get('DB_PORT', '27017');
             $username = Config::get('DB_USERNAME');
             $password = Config::get('DB_PASSWORD');
             $database = Config::get('DB_DATABASE', 'universal_api');

             $credentials = "";
             if ($username || $password) {
                 $encodedUser = rawurlencode((string)$username);
                 $encodedPass = rawurlencode((string)$password);
                 $credentials = "$encodedUser:$encodedPass@";
             }
             $portStr = $port ? ":$port" : "";
             $uri = "mongodb://{$credentials}{$host}{$portStr}/{$database}";
             $this->databaseName = $database;
        } else {
            // Parse DB name from URI if needed, or get from config
             $this->databaseName = Config::get('DB_DATABASE', 'universal_api');
        }

        $this->client = new Client($uri);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getCollection(string $collection): Collection
    {
        return $this->client->selectDatabase($this->databaseName)->selectCollection($collection);
    }
}
