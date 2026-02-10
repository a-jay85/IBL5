<?php

declare(strict_types=1);

namespace Api\Contracts;

use Api\Response\JsonResponder;

interface ControllerInterface
{
    /**
     * Handle the API request and send the response.
     *
     * @param array<string, string> $params Route parameters (e.g., ['uuid' => '...'])
     * @param array<string, string> $query  Query string parameters
     * @param array<string, mixed>|null $body Parsed JSON body for POST requests
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void;
}
