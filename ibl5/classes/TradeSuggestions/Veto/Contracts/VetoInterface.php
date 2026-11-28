<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto\Contracts;

use TradeSuggestions\Veto\DealView;

/**
 * One laugh-test rule. A deal that fires any veto is never shown, whatever
 * its gain.
 */
interface VetoInterface
{
    /**
     * Stable upper-snake id, used in diagnostics and on the tuning page.
     */
    public function id(): string;

    /**
     * Whether this deal breaks the rule for either side.
     */
    public function fires(DealView $deal): bool;
}
