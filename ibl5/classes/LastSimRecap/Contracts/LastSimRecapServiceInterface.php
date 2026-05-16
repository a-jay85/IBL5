<?php

declare(strict_types=1);

namespace LastSimRecap\Contracts;

use LastSimRecap\Dto\RecapSlate;

interface LastSimRecapServiceInterface
{
    /**
     * Build the Last-Sim Recap slate for a team, or return null when the
     * team did not play in the last sim window.
     */
    public function buildSlateForTeam(int $tid): ?RecapSlate;
}
