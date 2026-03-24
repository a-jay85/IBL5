<?php

declare(strict_types=1);

namespace Utilities;

class HtmxHelper
{
    public static function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    public static function isBoostedRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }

    /**
     * Redirect to a URL, using HX-Redirect for HTMX requests so the client
     * performs a boosted navigation (preserving HTMX headers on the follow-up GET).
     * Falls back to standard Location header for non-HTMX requests.
     *
     * @return never
     */
    public static function redirect(string $url): never
    {
        if (self::isHtmxRequest()) {
            header('HX-Redirect: ' . $url);
        } else {
            header('Location: ' . $url);
        }
        exit;
    }
}
