<?php

declare(strict_types=1);

namespace SimRecap;

use Psr\Log\LoggerInterface;

/**
 * Fires a GitHub repository_dispatch event so the sim-recap workflow runs right after a sim.
 *
 * Holds no DB handle and no actor identity: it only carries a token, a repo slug, and an
 * event type. Every failure is logged and swallowed; dispatch() never throws.
 */
final class GitHubDispatchClient
{
    /** @var callable(string, list<string>, string): int */
    private $transport;

    /**
     * @param null|callable(string, list<string>, string): int $transport (url, headers, body) -> HTTP status, 0 on transport error
     */
    public function __construct(
        private readonly string $token,
        private readonly string $repo,
        private readonly string $eventType,
        ?callable $transport = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->transport = $transport ?? self::defaultTransport();
    }

    /**
     * Null when no config file or an empty token: the caller skips dispatch.
     *
     * @param null|callable(string, list<string>, string): int $transport
     */
    public static function fromConfig(
        ?string $configDir = null,
        ?callable $transport = null,
        ?LoggerInterface $logger = null,
    ): ?self {
        $dir = $configDir ?? __DIR__ . '/../../config';
        $configPath = $dir . '/github-dispatch.config.php';
        $examplePath = $dir . '/github-dispatch.config.example.php';

        if (file_exists($configPath)) {
            $config = require $configPath; /** @phpstan-ignore ibl.requireOnce (config file returns array; not a class) */
        } elseif (file_exists($examplePath)) {
            $config = require $examplePath; /** @phpstan-ignore ibl.requireOnce (config file returns array; not a class) */
        } else {
            return null;
        }

        if (!is_array($config)) {
            return null;
        }

        $token = $config['token'] ?? null;
        $repo = $config['repo'] ?? null;
        $eventType = $config['event_type'] ?? null;

        if (!is_string($token) || !is_string($repo) || !is_string($eventType)) {
            return null;
        }
        if ($token === '' || $repo === '' || $eventType === '') {
            return null;
        }

        return new self($token, $repo, $eventType, $transport, $logger);
    }

    /**
     * True on HTTP 204. Never throws: every failure logs a warning and returns false.
     */
    public function dispatch(int $sim): bool
    {
        $body = json_encode([
            'event_type' => $this->eventType,
            'client_payload' => ['sim' => (string) $sim],
        ]);
        if ($body === false) {
            $this->logger?->warning('sim recap dispatch failed', ['sim' => $sim, 'status' => 0]);
            return false;
        }

        $url = 'https://api.github.com/repos/' . $this->repo . '/dispatches';
        $headers = [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $this->token,
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: IBL5-sim-recap-dispatch',
            'Content-Type: application/json',
        ];

        try {
            $status = ($this->transport)($url, $headers, $body);
        } catch (\Throwable) {
            $status = 0;
        }

        if ($status !== 204) {
            $this->logger?->warning('sim recap dispatch failed', ['sim' => $sim, 'status' => $status]);
            return false;
        }

        return true;
    }

    /**
     * @return array{repo: string, event_type: string}
     */
    public function __debugInfo(): array
    {
        return ['repo' => $this->repo, 'event_type' => $this->eventType];
    }

    /**
     * @return callable(string, list<string>, string): int
     */
    private static function defaultTransport(): callable
    {
        return self::curlPost(...);
    }

    /**
     * @param list<string> $headers
     */
    private static function curlPost(string $url, array $headers, string $body): int
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return 0;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $result = curl_exec($ch);
        $status = $result === false ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $status;
    }
}
