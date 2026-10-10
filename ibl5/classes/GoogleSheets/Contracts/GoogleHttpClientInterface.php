<?php

declare(strict_types=1);

namespace GoogleSheets\Contracts;

/**
 * Minimal HTTP seam for Google API calls so tests can replay recorded responses.
 */
interface GoogleHttpClientInterface
{
    /**
     * @param array<int, string> $headers Raw header lines, e.g. "Content-Type: application/json"
     * @return array{status: int, body: string}
     *
     * @throws \GoogleSheets\GoogleHttpException On transport failure (curl error)
     */
    public function request(string $method, string $url, array $headers, ?string $body): array;
}
