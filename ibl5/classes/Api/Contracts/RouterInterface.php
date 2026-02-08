<?php

declare(strict_types=1);

namespace Api\Contracts;

interface RouterInterface
{
    /**
     * Match a route path to a controller class and extracted parameters.
     *
     * @param string $path    The URL path (e.g., "players/abc-123-def")
     * @param string $method  The HTTP method (e.g., "GET")
     * @return array{controller: class-string<ControllerInterface>, params: array<string, string>}|null
     *         Returns matched route info, or null if no route matches
     */
    public function match(string $path, string $method): ?array;
}
