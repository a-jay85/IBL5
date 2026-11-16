<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * One surplus-to-deficit match between two teams at one offset. `kind` is
 * `dimension` (a `ProfileDimension::KEYS` entry) or `slot` (a
 * `SlotMapper::SLOTS` entry). The giver has the surplus; the receiver has the
 * deficit.
 */
final readonly class NeedPair
{
    public const string KIND_DIMENSION = 'dimension';
    public const string KIND_SLOT = 'slot';

    public function __construct(
        public int $offset,
        public string $kind,
        public string $key,
        public int $giverTid,
        public int $receiverTid,
        public int $giverRank,
        public int $receiverRank,
        public float $overlapWeight,
    ) {
    }
}
