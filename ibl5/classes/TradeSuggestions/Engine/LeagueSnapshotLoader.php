<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use League\League;
use ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface;
use Season\Season;
use Standings\Contracts\StandingsRepositoryInterface;
use Trading\Contracts\TradeFormRepositoryInterface;
use TradeSuggestions\Contracts\SuggestionAssetRepositoryInterface;
use TradeSuggestions\Engine\Contracts\LeagueSnapshotProviderInterface;
use TradeSuggestions\Projection\Contracts\ProjectionPlayerRepositoryInterface;
use TradeSuggestions\Valuation\Contracts\ValuationTeamFactorsRepositoryInterface;

/**
 * The uncached snapshot source. It reads every input through its interface and
 * hands them to `LeagueSnapshotBuilder::build`. It has no cache key.
 *
 * @phpstan-import-type StandingsRow from LeagueSnapshot
 * @phpstan-import-type StreakRow from LeagueSnapshot
 */
final class LeagueSnapshotLoader implements LeagueSnapshotProviderInterface
{
    private const string REGULAR_SEASON_PHASE = 'Regular Season';

    public function __construct(
        private readonly ProjectionPlayerRepositoryInterface $playerRepo,
        private readonly ValuationTeamFactorsRepositoryInterface $factorsRepo,
        private readonly StandingsRepositoryInterface $standingsRepo,
        private readonly ProjectedDraftOrderServiceInterface $draftOrderService,
        private readonly SuggestionAssetRepositoryInterface $assetRepo,
        private readonly TradeFormRepositoryInterface $formRepo,
        private readonly ModeResolver $modeResolver,
        private readonly Season $season,
    ) {
    }

    public function snapshot(): LeagueSnapshot
    {
        $endingYear = $this->season->endingYear;
        $advances = $this->season->advancesContractYears();

        $teamNames = [];
        foreach ($this->formRepo->getAllTeamsWithCity() as $team) {
            $teamNames[$team['teamid']] = $team['team_name'];
        }
        $allTids = array_keys($teamNames);

        return LeagueSnapshotBuilder::build(new LeagueSnapshotInputs(
            seasonYear: $endingYear,
            advancesContractYears: $advances,
            regularSeason: $this->season->phase === self::REGULAR_SEASON_PHASE,
            playersByTid: $this->playerRepo->getLeaguePlayers($advances, $endingYear - 1),
            scale: $this->playerRepo->getRatingScale(),
            standings: $this->standings($allTids),
            streaks: $this->streaks(),
            teamFactors: $this->factorsRepo->getTeamFactors(),
            cashByTeamAndOffset: $this->factorsRepo->getCashByTeamAndOffset($advances),
            pickRows: $this->assetRepo->getPicksForTeams($allTids, $endingYear),
            draftOrder: $this->draftOrderService->calculateDraftOrder($endingYear),
            modes: $this->modeResolver->resolveAll(),
            teamNames: $teamNames,
        ));
    }

    public function lastKey(): ?string
    {
        return null;
    }

    /**
     * Re-keyed by team id, with losses derived from games played (D7). A team
     * with no standings row gets a zero row so `TeamSituation::build` never throws.
     *
     * @param list<int> $allTids
     * @return array<int, StandingsRow>
     */
    private function standings(array $allTids): array
    {
        $out = [];
        // games_unplayed and the home/away win-loss columns are nullable in
        // ibl_standings (the CI seed leaves them NULL), though the repository's
        // row type says int. A NULL count reads as a full season left.
        foreach ($this->standingsRepo->getAllStandings() as $r) {
            $out[$r['teamid']] = [
                'wins' => $r['wins'],
                'losses' => self::intOr($r['homeGames'], 0) + self::intOr($r['awayGames'], 0) - $r['wins'],
                'pct' => (float) $r['pct'],
                'games_unplayed' => self::intOr($r['games_unplayed'], League::REGULAR_SEASON_GAMES),
                'conference' => $r['conference'],
            ];
        }
        foreach ($allTids as $tid) {
            $out[$tid] ??= [
                'wins' => 0,
                'losses' => 0,
                'pct' => 0.0,
                'games_unplayed' => League::REGULAR_SEASON_GAMES,
                'conference' => '',
            ];
        }

        return $out;
    }

    private static function intOr(mixed $value, int $default): int
    {
        return is_int($value) ? $value : $default;
    }

    /**
     * @return array<int, StreakRow>
     */
    private function streaks(): array
    {
        $out = [];
        foreach ($this->standingsRepo->getAllStreakData() as $tid => $r) {
            $out[$tid] = ['last_win' => $r['last_win'], 'last_loss' => $r['last_loss']];
        }

        return $out;
    }
}
