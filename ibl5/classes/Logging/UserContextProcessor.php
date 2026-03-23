<?php

declare(strict_types=1);

namespace Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds authenticated user context to log records from the PHP session.
 *
 * Reads $_SESSION lazily at log-emission time so the processor can be
 * constructed before AuthService resolves the session. In CLI or
 * unauthenticated contexts the extra keys are simply omitted.
 */
class UserContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $userId = $_SESSION['auth_user_id'] ?? null;
        $username = $_SESSION['auth_username'] ?? null;

        if (is_int($userId) && $userId > 0) {
            $record->extra['user_id'] = $userId;
        }

        if (is_string($username) && $username !== '') {
            $record->extra['username'] = $username;
        }

        return $record;
    }
}
