<?php

declare(strict_types=1);

final class FreeAgencyController
{
    public function handle(): void
    {
        global $cookie;
        $token = $cookie['_csrf_token'] ?? '';
        \PageLayout::header('Title');
    }
}
