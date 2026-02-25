<?php

declare(strict_types=1);

namespace DraftOrder\Contracts;

/**
 * @phpstan-import-type DraftSlot from DraftOrderServiceInterface
 * @phpstan-import-type DraftOrderResult from DraftOrderServiceInterface
 * @see \DraftOrder\DraftOrderView
 */
interface DraftOrderViewInterface
{
    /**
     * Render the projected draft order page.
     *
     * @param DraftOrderResult $draftOrder
     */
    public function render(array $draftOrder, int $seasonYear): string;
}
