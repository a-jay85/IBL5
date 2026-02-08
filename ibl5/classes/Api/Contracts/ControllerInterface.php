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
     */
    public function handle(array $params, array $query, JsonResponder $responder): void;
}
