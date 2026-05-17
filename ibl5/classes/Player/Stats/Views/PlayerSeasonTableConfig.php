<?php

declare(strict_types=1);

namespace Player\Stats\Views;

final class PlayerSeasonTableConfig
{
    public function __construct(
        public readonly PlayerSeasonTableMode $mode,
        public readonly string $title,
        public readonly string $careerLabel,
        public readonly bool $recalculatePoints = false,
    ) {
    }

    public function getColspan(): int
    {
        return $this->mode === PlayerSeasonTableMode::AVERAGES ? 16 : 15;
    }
}
