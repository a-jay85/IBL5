<?php

declare(strict_types=1);

namespace ProjectedDraftOrder\Contracts;

/**
 * @phpstan-import-type DraftSlot from ProjectedDraftOrderServiceInterface
 * @phpstan-import-type ProjectedDraftOrderResult from ProjectedDraftOrderServiceInterface
 * @see \ProjectedDraftOrder\ProjectedDraftOrderView
 */
interface ProjectedDraftOrderViewInterface
{
    /**
     * Render the projected draft order page.
     *
     * @param ProjectedDraftOrderResult $draftOrder
     */
    public function render(array $draftOrder, int $seasonYear): string;
}
