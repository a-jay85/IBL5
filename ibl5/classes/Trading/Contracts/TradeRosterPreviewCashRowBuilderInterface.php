<?php

declare(strict_types=1);

namespace Trading\Contracts;

/**
 * TradeRosterPreviewCashRowBuilderInterface - Contract for building the
 * synthetic cash-consideration rows shown in the trade roster preview's
 * contracts view.
 *
 * Extracted verbatim from TradeRosterPreviewApiHandler. The builder reads the
 * already-validated cash `$_GET` parameters through a
 * TradeRosterPreviewParamValidatorInterface collaborator and never touches the
 * database.
 */
interface TradeRosterPreviewCashRowBuilderInterface
{
    /**
     * Build synthetic cash rows for the contracts view
     *
     * @param int $maxCashYear Highest cash year the caller will accept; a
     *                         requested end year above this is rejected outright
     *                         (no rows are built) rather than clamped.
     * @return list<array<string, mixed>> Synthetic cash player rows with isCashRow flag
     */
    public function buildCashRows(int $viewingTeamId, int $maxCashYear): array;
}
