<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * No `auth_users` row has the requested id. Thrown before any write.
 */
final class UnknownAccountException extends \RuntimeException
{
}
