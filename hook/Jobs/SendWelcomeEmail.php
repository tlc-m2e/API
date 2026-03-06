<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Hook\Jobs;

use Bastivan\UniversalApi\Core\Queue\Job;
use Bastivan\UniversalApi\Core\Logger;

/**
 * Class SendWelcomeEmail
 * Developed by Bastivan Consulting
 *
 * Example job to send a welcome email.
 */
class SendWelcomeEmail extends Job
{
    private array $user;

    public function __construct(array $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        // Simulate email sending logic
        Logger::info("Sending welcome email to: " . $this->user['email']);

        // Sleep to simulate long processing time
        sleep(2);

        Logger::info("Email sent successfully to: " . $this->user['email']);
    }
}
