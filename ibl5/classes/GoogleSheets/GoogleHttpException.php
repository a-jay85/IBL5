<?php

declare(strict_types=1);

namespace GoogleSheets;

/**
 * Transport-level failure talking to Google. The message carries only the curl error string.
 */
class GoogleHttpException extends \RuntimeException
{
}
