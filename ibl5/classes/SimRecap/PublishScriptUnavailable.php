<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Thrown by PublishScriptBuilder::build() when a sim has no summary or no
 * displayable game recaps, so there is nothing to publish.
 */
final class PublishScriptUnavailable extends \RuntimeException
{
}
