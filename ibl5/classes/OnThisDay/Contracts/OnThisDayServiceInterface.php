<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

use OnThisDay\OnThisDaySelection;

/**
 * Contract for selecting the "on this day" moments shown for the current sim.
 *
 * @package OnThisDay\Contracts
 */
interface OnThisDayServiceInterface
{
    public function getMomentsForSim(int $limit = 3): OnThisDaySelection;
}
