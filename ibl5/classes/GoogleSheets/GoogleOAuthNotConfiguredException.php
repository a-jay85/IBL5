<?php

declare(strict_types=1);

namespace GoogleSheets;

/**
 * A required GOOGLE_OAUTH_* environment variable is unset or empty.
 */
class GoogleOAuthNotConfiguredException extends \RuntimeException
{
}
