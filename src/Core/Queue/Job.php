<?php

declare(strict_types=1);

namespace TLC\Core\Queue;

/**
 * Class Job
 * Developed by THE LIFE COINCOIN
 *
 * Abstract base class for all queue jobs.
 */
abstract class Job
{
    /**
     * Execute the job logic.
     */
    abstract public function handle(): void;
}
