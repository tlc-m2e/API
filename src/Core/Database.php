<?php

declare(strict_types=1);

namespace TLC\Core;

use PDO;
use PDOException;
use MongoDB\Client as MongoClient;

/**
 * Class Database
 * Developed by THE LIFE COINCOIN
 *
 * A Singleton Wrapper supporting PDO (MySQL/PostgreSQL) and MongoDB.
 * Enforces usage of prepared statements for SQL.
 */
class Database
{
    private static ?Database $instance = null;
    /** @var PDO|MongoClient */
    private $connection;
    private string $driver;

    private function __construct()
    {
        $this->driver = Config::get('DB_CONNECTION');
        $host = Config::get('DB_HOST');
        $port = Config::get('DB_PORT');
        $database = Config::get('DB_DATABASE');
        $username = Config::get('DB_USERNAME');
        $password = Config::get('DB_PASSWORD');

        if ($this->driver === 'mongodb') {
            $this->connectMongoDB($host, $port, $database, $username, $password);
        } else {
            $this->connectPDO($this->driver, $host, $port, $database, $username, $password);
        }
    }

    private function connectMongoDB($host, $port, $database, $username, $password): void
    {
        if (!class_exists(MongoClient::class)) {
            throw new \RuntimeException("THE LIFE COINCOIN API - MongoDB Driver not installed. Please run 'composer require mongodb/mongodb'.");
        }

        try {
            $uri = Config::get('MONGODB_URI');
            if (empty($uri)) {
                $credentials = "";
                if ($username || $password) {
                    $encodedUser = rawurlencode((string)$username);
                    $encodedPass = rawurlencode((string)$password);
                    $credentials = "$encodedUser:$encodedPass@";
                }

                $portStr = $port ? ":$port" : "";
                $uri = "mongodb://{$credentials}{$host}{$portStr}/{$database}";
            }

            // Options can be tuned via config if needed
            $this->connection = new MongoClient($uri);

            // Note: MongoDB lazy connects, so we might not see an error immediately until a command is run.
        } catch (\Exception $e) {
             throw new \RuntimeException("THE LIFE COINCOIN API - MongoDB Connection Error: " . $e->getMessage());
        }
    }

    private function connectPDO($driver, $host, $port, $database, $username, $password): void
    {
        $dsn = "$driver:host=$host;port=$port;dbname=$database";
        if ($driver === 'mysql') {
            $dsn .= ";charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Important for security
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException("THE LIFE COINCOIN API - Database Connection Error: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns the underlying connection object.
     * @return PDO|MongoClient
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Executes a prepared statement (SQL Only).
     * This is the preferred way to query to avoid SQL Injection.
     *
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     * @throws \RuntimeException if using MongoDB
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        if ($this->connection instanceof MongoClient) {
            throw new \RuntimeException("THE LIFE COINCOIN API - method 'query' is not supported for MongoDB. Use the MongoClient instance directly.");
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
