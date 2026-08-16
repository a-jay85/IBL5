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
    public function redirect(string $url): void
    {
        header('Location: ' . $url);
    }
}
