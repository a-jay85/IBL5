<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A pick goes to the side that takes on no more burden than the other. Burden
 * is the contract money beyond this season a side absorbs plus the cap room it
 * consumes. A pick is payment for taking on burden, so the side that takes
 * the pick must take more. Equal burden fires. Each side that receives a pick
 * is checked on its own; a deal that moves no pick never fires.
 */
final class PickToNonBurdenTaker implements VetoInterface
{
    public function id(): string
    {
        return 'PICK_TO_NON_BURDEN_TAKER';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            if ($side->receivedPicks === []) {
                continue;
            }
            if (self::burden($side) <= self::burden($deal->other($side))) {
                return true;
            }
        }

        return false;
    }

    private static function burden(SideView $side): int
    {
        return $side->negativeSurplusAbsorbed() + $side->capRoomConsumed();
    }
}
