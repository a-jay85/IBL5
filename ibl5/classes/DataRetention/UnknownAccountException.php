<?php

declare(strict_types=1);

namespace DataRetention;

/**
 * No `auth_users` row has the requested id, so nothing was changed.
 */
final class UnknownAccountException extends \RuntimeException
{
}
