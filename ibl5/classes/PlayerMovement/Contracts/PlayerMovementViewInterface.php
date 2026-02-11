<?php

declare(strict_types=1);

namespace PlayerMovement\Contracts;

/**
 * PlayerMovementViewInterface - Contract for player movement HTML rendering
 *
 * @phpstan-import-type MovementRow from PlayerMovementRepositoryInterface
 *
 * @see \PlayerMovement\PlayerMovementView For the concrete implementation
 */
interface PlayerMovementViewInterface
{
    /**
     * Render the player movement table
     *
     * @param list<MovementRow> $movements Player movement data
     * @return string HTML output
     */
    public function render(array $movements): string;
}
