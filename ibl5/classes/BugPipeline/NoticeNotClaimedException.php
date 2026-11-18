<?php

declare(strict_types=1);

namespace BugPipeline;

/**
 * Thrown when a notice row is not in the claimed state (release() on a row that was
 * never claimed, or was already released or marked).
 */
final class NoticeNotClaimedException extends \RuntimeException
{
}
