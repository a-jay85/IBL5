<?php

declare(strict_types=1);

namespace Security;

/**
 * The SecretBox key is unset, empty, malformed, or ext-sodium is missing.
 *
 * Messages name the variable only, never its value.
 */
class SecretBoxKeyUnavailableException extends \RuntimeException
{
}
