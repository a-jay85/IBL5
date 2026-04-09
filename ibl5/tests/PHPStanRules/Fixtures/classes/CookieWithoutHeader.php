<?php

declare(strict_types=1);

final class CookieWithoutHeader
{
    public function handle(): void
    {
        global $cookie;
        $token = $cookie['_csrf_token'] ?? '';
    }
}
