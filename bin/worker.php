#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TLC\Core\Bootstrap;
use TLC\Core\Queue\Queue;
use TLC\Core\Logger;
use TLC\Core\Queue\Job;

// Initialize the application (load env, config, etc.)
Bootstrap::init(__DIR__ . '/..');

Logger::info("Queue Worker started. Waiting for jobs...");

while (true) {
    try {
        /** @var Job|null $job */
        $job = Queue::pop(10); // Block for 10 seconds

        if ($job) {
            Logger::info("Processing job: " . get_class($job));
            $job->handle();
            Logger::info("Job processed: " . get_class($job));
        }
    } catch (\Throwable $e) {
        Logger::error("Job failed: " . $e->getMessage());
        // Optional: Implement retry logic or dead letter queue here
    }
}
