<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

use OnThisDay\OnThisDaySelection;

/**
 * Contract for rendering the "on this day" homepage block.
 *
 * @package OnThisDay\Contracts
 */
interface OnThisDayViewInterface
{
    public function renderHomepageBlock(OnThisDaySelection $selection): string;
}
