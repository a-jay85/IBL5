<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use Standings\StandingsRepository;
use Tests\WideUnit\Mocks\Season;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotLoader;
use TradeSuggestions\Engine\ModeResolver;
use TradeSuggestions\Projection\Horizon;
use TradeSuggestions\Projection\ProjectionPlayerRepository;
use TradeSuggestions\SuggestionAssetRepository;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeRepository;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\Valuation\ValuationTeamFactorsRepository;
use Trading\TradeFormRepository;

/**
 * Runs the uncached snapshot loader over the real schema with every real
 * repository, so a renamed column or a broken reshape fails here.
 *
 * Staged rows (inside the test transaction): 13 players on each of teams 1
 * and 2, a pick team 1 owns for next season, and one waived player on team 1.
 */
#[Group('database')]
final class LeagueSnapshotLoaderIntegrationTest extends DatabaseTestCase
{
    private const int PID_BASE = 900400;
    private const int WAIVED_PID = 900499;
    private const array STAGED_TIDS = [1, 2];

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        // The mock Season pins a fixed year and phase. Read the league's real
        // values so picks and last-season stats line up with the data.
        $this->season = new Season($this->db);
        $result = $this->db->query("SELECT setting_key, setting_value FROM ibl_settings WHERE league = 'ibl' AND setting_key IN ('Current Season Ending Year', 'Current Season Phase')");
        self::assertInstanceOf(\mysqli_result::class, $result);
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $value = is_string($row['setting_value']) ? $row['setting_value'] : '';
            if ($row['setting_key'] === 'Current Season Ending Year' && is_numeric($value)) {
                $this->season->endingYear = (int) $value;
                $this->season->beginningYear = (int) $value - 1;
            } elseif ($row['setting_key'] === 'Current Season Phase' && $value !== '') {
                $this->season->phase = $value;
            }
        }

        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        foreach (self::STAGED_TIDS as $tid) {
            for ($i = 1; $i <= 13; $i++) {
                $pid = self::PID_BASE + $tid * 20 + $i;
                $this->insertTestPlayer($pid, 'Snapshot ' . $tid . '-' . $i, [
                    'teamid' => $tid,
                    'pos' => $positions[$i % 5],
                    'ordinal' => $i,
                    'cy' => 1,
                    'cyt' => 3,
                ]);
            }
        }
        $this->insertTestPlayer(self::WAIVED_PID, 'Snapshot Waived', ['teamid' => 1, 'ordinal' => 1000]);
    }

    public function testSnapshotHasBothStagedTeamsWithFourOffsets(): void
    {
        $s = $this->loader()->snapshot();

        self::assertSame(Horizon::OFFSETS, array_keys($s->lineups));
        foreach (self::STAGED_TIDS as $tid) {
            self::assertContains($tid, $s->teamIds);
            foreach (Horizon::OFFSETS as $offset) {
                self::assertArrayHasKey($tid, $s->lineups[$offset]);
                self::assertArrayHasKey($tid, $s->profiles[$offset]);
            }
            self::assertArrayHasKey(self::PID_BASE + $tid * 20 + 1, $s->players);
        }
        self::assertSame($this->season->endingYear, $s->seasonYear);
    }

    public function testStagedPickAppearsWithYearsAway(): void
    {
        $pickId = $this->insertDraftPickRow(1, 2, $this->season->endingYear + 1, 2);

        $s = $this->loader()->snapshot();

        self::assertArrayHasKey($pickId, $s->picks);
        self::assertSame(1, $s->picks[$pickId]->yearsAway);
    }

    public function testWaivedPlayerIsNotInSnapshot(): void
    {
        $s = $this->snapshotWithStagedRoster();

        self::assertArrayNotHasKey(self::WAIVED_PID, $s->players);
        self::assertArrayNotHasKey(self::WAIVED_PID, $s->lineup(0, 1)->players);
    }

    private function snapshotWithStagedRoster(): LeagueSnapshot
    {
        $s = $this->loader()->snapshot();
        self::assertArrayHasKey(self::PID_BASE + 21, $s->players, 'staged team 1 roster loaded');

        return $s;
    }

    private function loader(): LeagueSnapshotLoader
    {
        $formRepo = new TradeFormRepository($this->db);
        $teamModeRepo = new TeamModeRepository($this->db);
        $assetRepo = new SuggestionAssetRepository($this->db);
        $draftOrder = new ProjectedDraftOrderService(new ProjectedDraftOrderRepository($this->db));

        return new LeagueSnapshotLoader(
            new ProjectionPlayerRepository($this->db),
            new ValuationTeamFactorsRepository($this->db),
            new StandingsRepository($this->db),
            $draftOrder,
            $assetRepo,
            $formRepo,
            new ModeResolver(
                $assetRepo,
                $draftOrder,
                $teamModeRepo,
                $formRepo,
                new TeamModeService($teamModeRepo, new TeamModeGuesser()),
                $this->season,
            ),
            $this->season,
        );
    }
}
