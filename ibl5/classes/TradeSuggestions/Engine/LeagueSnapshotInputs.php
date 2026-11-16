<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\RatingScale;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\TeamFactors;

/**
 * Everything `LeagueSnapshotBuilder::build` needs, already read from the
 * database by `LeagueSnapshotLoader`.
 *
 * @phpstan-import-type ProjectedDraftOrderResult from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 * @phpstan-import-type SuggestionPickRow from \TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface
 * @phpstan-import-type StandingsRow from LeagueSnapshot
 * @phpstan-import-type StreakRow from LeagueSnapshot
 */
final readonly class LeagueSnapshotInputs
{
    /**
     * @param array<int, list<ProjectionPlayer>> $playersByTid
     * @param array<int, StandingsRow> $standings tid => row
     * @param array<int, StreakRow> $streaks tid => row
     * @param array<int, TeamFactors> $teamFactors tid => factors
     * @param array<int, array<int, int>> $cashByTeamAndOffset tid => offset => cash
     * @param array<int, list<SuggestionPickRow>> $pickRows owner tid => picks
     * @param ProjectedDraftOrderResult $draftOrder
     * @param array<int, TeamMode> $modes tid => mode
     * @param array<int, string> $teamNames tid => name
     */
    public function __construct(
        public int $seasonYear,
        public bool $advancesContractYears,
        public bool $regularSeason,
        public array $playersByTid,
        public RatingScale $scale,
        public array $standings,
        public array $streaks,
        public array $teamFactors,
        public array $cashByTeamAndOffset,
        public array $pickRows,
        public array $draftOrder,
        public array $modes,
        public array $teamNames,
    ) {
    }
}
