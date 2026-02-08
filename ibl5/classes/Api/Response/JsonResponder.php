<?php

declare(strict_types=1);

namespace Api\Response;

class JsonResponder
{
    /**
     * Send a success response with data and optional pagination metadata.
     *
     * @param mixed $data                         Response payload
     * @param array<string, mixed> $meta           Additional metadata (page, per_page, total, etc.)
     * @param int $statusCode                      HTTP status code
     * @param array<string, string> $extraHeaders  Additional headers (e.g., Cache-Control, ETag)
     */
    public function success(mixed $data, array $meta = [], int $statusCode = 200, array $extraHeaders = []): void
    {
        $body = [
            'status' => 'success',
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'version' => 'v1',
            ], $meta),
        ];

        $this->send($statusCode, $body, $extraHeaders);
    }

    /**
     * Send an error response.
     *
     * @param int $statusCode                     HTTP status code
     * @param string $errorCode                   Machine-readable error code (e.g., "not_found")
     * @param string $message                     Human-readable error message
     * @param array<string, string> $extraHeaders Additional headers (e.g., Retry-After)
     */
    public function error(int $statusCode, string $errorCode, string $message, array $extraHeaders = []): void
    {
        $body = [
            'status' => 'error',
            'error' => [
                'code' => $errorCode,
                'message' => $message,
            ],
            'meta' => [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'version' => 'v1',
            ],
        ];

        $this->send($statusCode, $body, $extraHeaders);
    }

    /**
     * Send a 304 Not Modified response (no body).
     */
    public function notModified(): void
    {
        http_response_code(304);
        // No body for 304
    }

    /**
     * Send the JSON response with appropriate headers.
     *
     * @param int $statusCode
     * @param array<string, mixed> $body
     * @param array<string, string> $extraHeaders
     */
    private function send(int $statusCode, array $body, array $extraHeaders): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

        foreach ($extraHeaders as $name => $value) {
            header($name . ': ' . $value);
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json !== false) {
            echo $json;
        }
    }
}
