<?php

declare(strict_types=1);

namespace GoogleSheets;

/**
 * The stored refresh token is no longer valid (user revoked access or it expired).
 */
class GoogleGrantRevokedException extends GoogleApiException
{
}
