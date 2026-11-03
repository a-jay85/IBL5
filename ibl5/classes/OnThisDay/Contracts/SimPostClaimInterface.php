<?php

declare(strict_types=1);

namespace OnThisDay\Contracts;

/**
 * Contract for the once-per-sim claim that guards the "on this day" Discord post.
 */
interface SimPostClaimInterface
{
    /**
     * Atomically claim the right to post for a sim.
     *
     * @return bool true when this call advanced the last-posted sim; false when
     *              the same or a newer sim was already claimed
     */
    public function claimSimPost(int $sim): bool;
}
