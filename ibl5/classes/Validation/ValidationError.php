<?php

declare(strict_types=1);

namespace Validation;

/**
 * One structured validation failure: a machine-readable type, a user-facing
 * message, and optional actionable detail. Message and detail are RAW text;
 * the renderer that prints them owns the escaping.
 */
final class ValidationError
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $detail = '',
    ) {
    }
}
