<?php

declare(strict_types=1);

namespace Draft\Dto;

/**
 * @phpstan-import-type DraftClassPlayerRow from \Draft\Contracts\DraftRepositoryInterface
 */
final class DraftBoardData
{
    /** @param list<DraftClassPlayerRow> $players */
    public function __construct(
        public readonly array $players,
        public readonly string $teamLogo,
        public readonly ?string $pickOwner,
        public readonly ?int $draftRound,
        public readonly ?int $draftPick,
        public readonly int $seasonYear,
        public readonly int $teamId,
    ) {
    }
}
