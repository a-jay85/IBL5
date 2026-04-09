<?php

declare(strict_types=1);

final class CookieAfterHeader
{
    public function handle(): void
    {
        \PageLayout::header('Title');
        global $cookie;
        $token = $cookie['_csrf_token'] ?? '';
    }
}
