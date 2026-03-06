<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core\Queue;

/**
 * Class Job
 * Developed by Bastivan Consulting
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
