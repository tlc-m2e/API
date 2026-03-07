<?php

declare(strict_types=1);

namespace TLC\Core\Queue;

use TLC\Core\Cache;

/**
 * Class Queue
 * Developed by THE LIFE COINCOIN
 *
 * Simple Redis-based Queue manager.
 */
class Queue
{
    private const QUEUE_KEY = 'queue:default';

    /**
     * Push a job onto the queue.
     *
     * @param Job $job The job instance to queue.
     */
    public static function push(Job $job): void
    {
        $payload = serialize($job);
        Cache::getClient()->rpush(self::QUEUE_KEY, [$payload]);
    }

    /**
     * Pop a job from the queue (blocking).
     *
     * @param int $timeout Timeout in seconds for blocking pop.
     * @return Job|null The job instance or null if timeout.
     */
    public static function pop(int $timeout = 5): ?Job
    {
        // blpop returns array [key, value] or null on timeout
        $result = Cache::getClient()->blpop([self::QUEUE_KEY], $timeout);

        if ($result && isset($result[1])) {
            return unserialize($result[1]);
        }

        return null;
    }
}
