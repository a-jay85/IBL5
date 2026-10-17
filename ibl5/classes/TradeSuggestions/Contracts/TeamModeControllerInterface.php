<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

interface TeamModeControllerInterface
{
    /**
     * Handle the GM's team-mode override form POST.
     *
     * Checks, in order: POST method, CSRF token (form `trade_mode`), the
     * acting team resolved from the session (never from the POST body), and
     * the submitted mode against the closed TeamModeSetting enum. Writes only
     * when every check passes.
     *
     * @param array<mixed> $post Submitted form fields ($_POST)
     * @return string The URL to redirect to; this method never redirects itself
     */
    public function handleSetMode(string $requestMethod, array $post): string;
}
