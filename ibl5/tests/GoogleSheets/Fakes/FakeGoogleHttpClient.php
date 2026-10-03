<?php

declare(strict_types=1);

namespace Tests\GoogleSheets\Fakes;

use GoogleSheets\Contracts\GoogleHttpClientInterface;
use GoogleSheets\GoogleHttpException;

/**
 * Record-and-replay fake: queue literal Google responses, inspect recorded requests.
 */
final class FakeGoogleHttpClient implements GoogleHttpClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<int, string>, body: ?string}> */
    public array $requests = [];

    /** @var list<array{status: int, body: string}|GoogleHttpException> */
    private array $queue = [];

    /**
     * @param string|array<mixed> $body Arrays are JSON-encoded
     */
    public function queue(int $status, string|array $body): void
    {
        $this->queue[] = [
            'status' => $status,
            'body' => is_array($body) ? (string) json_encode($body) : $body,
        ];
    }

    public function queueTransportError(string $message): void
    {
        $this->queue[] = new GoogleHttpException($message);
    }

    /**
     * @param array<int, string> $headers
     * @return array{status: int, body: string}
     */
    public function request(string $method, string $url, array $headers, ?string $body): array
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('no queued response');
        }
        if ($next instanceof GoogleHttpException) {
            throw $next;
        }

        return $next;
    }
}
