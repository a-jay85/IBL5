<?php

declare(strict_types=1);

namespace GoogleSheets;

/**
 * Google answered with an error. Never carries the request URL or body.
 */
class GoogleApiException extends \RuntimeException
{
    private const MAX_MESSAGE_LENGTH = 200;

    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $reason,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(int $status, string $body): self
    {
        $reason = 'unknown';
        $message = '';

        $decoded = GoogleJson::decode($body);
        if (is_array($decoded)) {
            $error = $decoded['error'] ?? null;
            if (is_string($error)) {
                $reason = $error;
                $description = $decoded['error_description'] ?? null;
                if (is_string($description)) {
                    $message = $description;
                }
            } elseif (is_array($error)) {
                $errorStatus = $error['status'] ?? null;
                if (is_string($errorStatus) && $errorStatus !== '') {
                    $reason = $errorStatus;
                }
                $errorMessage = $error['message'] ?? null;
                if (is_string($errorMessage)) {
                    $message = $errorMessage;
                }
            }
        }

        $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH);
        $full = 'Google API error (HTTP ' . $status . ', ' . $reason . ')';
        if ($message !== '') {
            $full .= ': ' . $message;
        }

        return new self($full, $status, $reason);
    }
}
