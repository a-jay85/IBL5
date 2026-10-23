<?php

declare(strict_types=1);

namespace Api\Response;

/**
 * Emits a redirect response header.
 *
 * Does NOT call exit — callers are responsible for returning from their
 * method after this call so that no further output is sent.
 */
class RedirectResponder
{
    public function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
    }

    /** Tells HTMX to perform a client-side navigation to $url. */
    public function hxRedirect(string $url): void
    {
        header('HX-Redirect: ' . $url);
    }
}
