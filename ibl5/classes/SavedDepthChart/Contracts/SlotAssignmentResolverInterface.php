<?php

declare(strict_types=1);

namespace SavedDepthChart\Contracts;

/**
 * Resolves which submitted depth-chart form slot (1..15) a roster player occupies,
 * and returns that slot's depth-chart settings.
 *
 * Extracted from SavedDepthChartService (backlog 1.34) so the pid -> name -> ordinal
 * matching strategy is independently testable and substitutable.
 *
 * @phpstan-type SlotSettings array{
 *     pg: int,
 *     sg: int,
 *     sf: int,
 *     pf: int,
 *     c: int,
 *     canPlayInGame: int,
 *     min: int,
 *     of: int,
 *     df: int,
 *     oi: int,
 *     di: int,
 *     bh: int
 * }
 */
interface SlotAssignmentResolverInterface
{
    /**
     * Resolve the depth-chart settings for one roster player from the submitted form.
     *
     * Matching strategy, in order: (1) `pid<N>` hidden field equal to the player's pid;
     * (2) `Name<N>` field whose trimmed, tag-stripped value equals the player's name;
     * (3) the player's 1-based roster ordinal, if `Name<ordinal>` was submitted at all.
     *
     * @param array<string, mixed> $player   Roster row; reads only 'pid' and 'name'.
     * @param array<string, mixed> $postData Raw submitted form fields.
     * @param int $ordinal                   1-based roster position (last-resort match).
     * @return SlotSettings|null             Null when the player occupies no submitted slot.
     */
    public function resolveSlotSettings(array $player, array $postData, int $ordinal): ?array;
}
