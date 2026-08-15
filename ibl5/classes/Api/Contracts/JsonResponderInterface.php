<?php

declare(strict_types=1);

namespace Api\Contracts;

interface JsonResponderInterface
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @param array<string, string> $extraHeaders
     */
    public function success(mixed $data, array $meta, int $statusCode, array $extraHeaders): void;

    /**
     * @param array<string, string> $extraHeaders
     */
    public function error(int $statusCode, string $errorCode, string $message, array $extraHeaders): void;

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $extraHeaders
     */
    public function raw(array $body, int $statusCode, array $extraHeaders): void;

    public function notModified(): void;
}
