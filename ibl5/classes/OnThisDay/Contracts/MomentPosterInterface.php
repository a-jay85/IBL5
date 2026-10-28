<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

/**
 * Contract for publishing an "on this day" line to an outside channel.
 */
interface MomentPosterInterface
{
    /**
     * @throws \Throwable when delivery fails; callers treat the post as best-effort
     */
    public function post(string $message): void;
}
