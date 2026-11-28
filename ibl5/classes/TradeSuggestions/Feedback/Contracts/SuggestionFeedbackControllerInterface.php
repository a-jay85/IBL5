<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback\Contracts;

interface SuggestionFeedbackControllerInterface
{
    /**
     * Handle a trade-idea card POST: `action=hide` or `action=refresh`.
     *
     * Checks, in order: POST method, CSRF token (form `trade_suggestion_action`),
     * the acting team resolved from the session (never from the POST body), and
     * the action. A hide then checks the deal key against the ideas the panel
     * offered this session, the reason codes against the closed set, and the
     * note length (500 characters). Writes only when every check passes.
     *
     * @param array<mixed> $post Submitted form fields ($_POST)
     * @return string The URL to redirect to; this method never redirects itself
     */
    public function handle(string $requestMethod, array $post): string;
}
