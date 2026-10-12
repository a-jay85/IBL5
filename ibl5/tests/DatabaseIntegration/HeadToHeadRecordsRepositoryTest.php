<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use HeadToHeadRecords\HeadToHeadRecordsRepository;
use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-integration tests for HeadToHeadRecordsRepository.
 *
 * Fixtures use season_year = 1901 (box scores) so they never collide with real data.
 * ibl_franchise_seasons rows are inserted with season_year=1900, season_ending_year=1901
 * (= the 1901 season in the IBL convention: season_ending_year = box_scores.season_year).
 * Game date '1901-03-15': MONTH=3 < 10 → season_year=1901, MONTH≠6/10 → game_type=1.
 *
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 */
#[Group('database')]
class HeadToHeadRecordsRepositoryTest extends DatabaseTestCase
{
    private HeadToHeadRecordsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert franchise_seasons for the 1901 season (season_ending_year=1901 matches
        // box_scores.season_year=1901; the join in buildTeamsMatrix uses season_ending_year).
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => 17,
            'season_year'        => 1900,
            'season_ending_year' => 1901,
            'team_city'          => 'San Antonio',
            'team_name'          => 'Spurs',
        ]);
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => 22,
            'season_year'        => 1900,
            'season_ending_year' => 1901,
            'team_city'          => 'Seattle',
            'team_name'          => 'Supersonics',
        ]);

        // GM tenures: franchise 17 has two overlapping GMs for 1901.
        // Alpha (start=1900, end=1901): covers 1901, starts earlier → wins LIMIT 1.
        // Beta  (start=1901, end=NULL=current): also covers 1901, starts later.
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 17,
            'gm_display_name'     => 'H2HAlpha',
            'start_season_year'   => 1900,
            'end_season_year'     => 1901,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);
        // Beta: omit end_season_year so it defaults to NULL (current GM).
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 17,
            'gm_display_name'     => 'H2HBeta',
            'start_season_year'   => 1901,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);
        // Gamma: franchise 22, current GM.
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 22,
            'gm_display_name'     => 'H2HGamma',
            'start_season_year'   => 1900,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        // Two rows for one game: visitor=22 beats home=17.
        // game_date='1901-03-15': MONTH=3 → game_type=1 (regular), season_year=1901.
        // Visitor total (30+25+25+25=105) > Home total (20+20+20+20=80): visitor wins.
        $commonCols = [
            'game_date'          => '1901-03-15',
            'game_of_that_day'   => 1,
            'visitor_teamid'     => 22,
            'home_teamid'        => 17,
            'attendance'         => 10000,
            'capacity'           => 15000,
            'visitor_wins'       => 0,
            'visitor_losses'     => 0,
            'home_wins'          => 0,
            'home_losses'        => 0,
            'visitor_q1_points'  => 30,
            'visitor_q2_points'  => 25,
            'visitor_q3_points'  => 25,
            'visitor_q4_points'  => 25,
            'visitor_ot_points'  => 0,
            'home_q1_points'     => 20,
            'home_q2_points'     => 20,
            'home_q3_points'     => 20,
            'home_q4_points'     => 20,
            'home_ot_points'     => 0,
            'game_2gm'           => 30,
            'game_2ga'           => 60,
            'game_ftm'           => 15,
            'game_fta'           => 20,
            'game_3gm'           => 8,
            'game_3ga'           => 22,
            'game_orb'           => 10,
            'game_drb'           => 30,
            'game_ast'           => 20,
            'game_stl'           => 8,
            'game_tov'           => 12,
            'game_blk'           => 5,
            'game_pf'            => 18,
        ];
        $this->insertRow('ibl_box_scores_teams', array_merge($commonCols, ['name' => 'Supersonics']));
        $this->insertRow('ibl_box_scores_teams', array_merge($commonCols, ['name' => 'Spurs']));

        // Repository with explicit current season year 1901 so scope='current' hits only
        // our fixture (real data has no season_year=1901 games).
        $this->repo = new HeadToHeadRecordsRepository($this->db, 1901);
    }

    // -------------------------------------------------------------------------
    // Era branding seed
    // -------------------------------------------------------------------------

    public function testEraBrandingTableIsSeededWithSixRetiredEras(): void
    {
        // Reads the rows the fixture seed (db-seed.sql) plants after ibl_team_info is
        // populated. No DELETE/INSERT here: the test must observe the seeded state.
        $stmt = $this->db->prepare(
            'SELECT franchise_id, team_city, team_name, color1, color2
             FROM `ibl_franchise_era_branding` ORDER BY id',
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(6, $rows);
        self::assertSame([
            ['franchise_id' => 4,  'team_city' => 'Brooklyn',      'team_name' => 'Nets',        'color1' => '000000', 'color2' => 'FFFFFF'],
            ['franchise_id' => 10, 'team_city' => 'Charlotte',     'team_name' => 'Hornets',     'color1' => '00788C', 'color2' => '1D1160'],
            ['franchise_id' => 16, 'team_city' => 'Oklahoma City', 'team_name' => 'Thunder',     'color1' => '007AC1', 'color2' => 'EF6F31'],
            ['franchise_id' => 16, 'team_city' => 'Las Vegas',     'team_name' => 'Thunder',     'color1' => '1C1C1C', 'color2' => 'F5C518'],
            ['franchise_id' => 17, 'team_city' => 'San Antonio',   'team_name' => 'Spurs',       'color1' => 'C4CED4', 'color2' => '000000'],
            ['franchise_id' => 22, 'team_city' => 'Seattle',       'team_name' => 'Supersonics', 'color1' => '00653A', 'color2' => 'FFC200'],
        ], $rows);
    }

    // -------------------------------------------------------------------------
    // Reconciliation: wins+losses == 2 × dedup count for every filter
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function allCombinationsProvider(): iterable
    {
        foreach (['franchises', 'teams', 'gms'] as $dimension) {
            foreach (['heat', 'regular', 'playoffs', 'all'] as $phase) {
                foreach (['current', 'all'] as $scope) {
                    yield "{$dimension}/{$phase}/{$scope}" => [$dimension, $phase, $scope];
                }
            }
        }
    }

    #[DataProvider('allCombinationsProvider')]
    public function testWinsPlusLossesEqualTwiceDeduplicatedGamesForEveryFilter(
        string $dimension,
        string $phase,
        string $scope,
    ): void {
        $payload = match ($dimension) {
            'franchises' => $this->repo->buildFranchisesMatrix($phase, $scope),
            'teams'      => $this->repo->buildTeamsMatrix($phase, $scope),
            'gms'        => $this->repo->buildGmsMatrix($phase, $scope),
            default      => throw new \InvalidArgumentException("Unhandled dimension: {$dimension}"),
        };

        $total = 0;
        foreach ($payload['records'] as $oppMap) {
            foreach ($oppMap as $matchup) {
                $total += $matchup['wins'] + $matchup['losses'];
            }
        }

        $expected = $this->computeDeduplicatedCount($dimension, $phase, $scope);
        self::assertSame(
            2 * $expected,
            $total,
            "wins+losses ({$total}) != 2*dedup ({$expected}) for {$dimension}/{$phase}/{$scope}",
        );
    }

    // -------------------------------------------------------------------------
    // GM tests
    // -------------------------------------------------------------------------

    public function testOverlappingTenuresResolveToEarliestStart(): void
    {
        // Franchise 17 in season 1901: H2HAlpha (start=1900) and H2HBeta (start=1901) both cover 1901.
        // The attribution query orders by start_season_year ASC LIMIT 1 → H2HAlpha wins.
        $payload = $this->repo->buildGmsMatrix('regular', 'current');

        // H2HAlpha should have a record vs H2HGamma.
        self::assertArrayHasKey('H2HAlpha', $payload['records'], 'H2HAlpha should be in records');
        self::assertArrayHasKey('H2HGamma', $payload['records']['H2HAlpha'], 'H2HAlpha vs H2HGamma record missing');

        // H2HBeta should NOT have records (not attributed any game).
        self::assertArrayNotHasKey('H2HBeta', $payload['records'], 'H2HBeta should not be in records');
    }

    public function testGamesBetweenTheSameGmAreExcluded(): void
    {
        // Insert two tenures for 'H2HDelta' managing different franchises in season 1901.
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 1,
            'gm_display_name'     => 'H2HDelta',
            'start_season_year'   => 1900,
            'end_season_year'     => 1902,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 2,
            'gm_display_name'     => 'H2HDelta',
            'start_season_year'   => 1900,
            'end_season_year'     => 1902,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        // Game between franchise 1 (visitor) and franchise 2 (home) in 1901.
        $deltaGame = [
            'game_date'          => '1901-04-01',
            'name'               => 'Team1',
            'game_of_that_day'   => 1,
            'visitor_teamid'     => 1,
            'home_teamid'        => 2,
            'attendance'         => 10000,
            'capacity'           => 15000,
            'visitor_wins'       => 0,
            'visitor_losses'     => 0,
            'home_wins'          => 0,
            'home_losses'        => 0,
            'visitor_q1_points'  => 30,
            'visitor_q2_points'  => 25,
            'visitor_q3_points'  => 25,
            'visitor_q4_points'  => 25,
            'visitor_ot_points'  => 0,
            'home_q1_points'     => 20,
            'home_q2_points'     => 20,
            'home_q3_points'     => 20,
            'home_q4_points'     => 20,
            'home_ot_points'     => 0,
            'game_2gm'           => 30,
            'game_2ga'           => 60,
            'game_ftm'           => 15,
            'game_fta'           => 20,
            'game_3gm'           => 8,
            'game_3ga'           => 22,
            'game_orb'           => 10,
            'game_drb'           => 30,
            'game_ast'           => 20,
            'game_stl'           => 8,
            'game_tov'           => 12,
            'game_blk'           => 5,
            'game_pf'            => 18,
        ];
        $this->insertRow('ibl_box_scores_teams', $deltaGame);
        $this->insertRow('ibl_box_scores_teams', array_merge($deltaGame, ['name' => 'Team2']));

        $payload = $this->repo->buildGmsMatrix('regular', 'current');

        // H2HDelta should not appear as self_gm because both perspectives are same-GM → excluded.
        self::assertArrayNotHasKey('H2HDelta', $payload['records'], 'Same-GM games should be excluded');
    }

    // -------------------------------------------------------------------------
    // currentSeasonHasGames(): drives the Controller's scope default
    // -------------------------------------------------------------------------

    public function testCurrentSeasonHasGamesIsTrueWhenTheSeasonHasBoxScores(): void
    {
        // $this->repo is pinned to 1901, the year the fixture game was played.
        self::assertTrue($this->repo->currentSeasonHasGames());
    }

    public function testCurrentSeasonHasGamesIsFalseForASeasonWithNoBoxScores(): void
    {
        // 1902 has no fixture rows and no real data.
        $repo = new HeadToHeadRecordsRepository($this->db, 1902);

        self::assertFalse($repo->currentSeasonHasGames());
    }

    // -------------------------------------------------------------------------
    // GM link_franchise_id: active tenure links, retired does not
    // -------------------------------------------------------------------------

    public function testLinkFranchiseIdIsSetForActiveTenureGmAndZeroForRetired(): void
    {
        // scope='all' pulls GM names from ibl_gm_tenures so all three appear.
        // H2HAlpha: best tenure has end_season_year=1901 (retired) → link_franchise_id = 0.
        // H2HBeta:  best tenure has end_season_year=NULL (active) → link_franchise_id = 17.
        // H2HGamma: best tenure has end_season_year=NULL (active) → link_franchise_id = 22.
        $payload = $this->repo->buildGmsMatrix('regular', 'all');

        $byName = [];
        foreach ($payload['axis'] as $entry) {
            if (in_array($entry['key'], ['H2HAlpha', 'H2HBeta', 'H2HGamma'], true)) {
                $byName[$entry['key']] = $entry;
            }
        }

        self::assertArrayHasKey('H2HAlpha', $byName, 'H2HAlpha must appear in axis');
        self::assertArrayHasKey('H2HBeta',  $byName, 'H2HBeta must appear in axis');
        self::assertArrayHasKey('H2HGamma', $byName, 'H2HGamma must appear in axis');

        self::assertSame(0,  $byName['H2HAlpha']['link_franchise_id'], 'H2HAlpha is retired; link_franchise_id must be 0');
        self::assertSame(17, $byName['H2HBeta']['link_franchise_id'],  'H2HBeta is active; link_franchise_id must equal franchise_id');
        self::assertSame(22, $byName['H2HGamma']['link_franchise_id'], 'H2HGamma is active; link_franchise_id must equal franchise_id');
    }

    // -------------------------------------------------------------------------
    // GM axis: colors, logos, tenure selection (characterization, pre-split)
    // -------------------------------------------------------------------------

    public function testCurrentGmAxisEntryUsesTeamInfoColorsAndNewLogo(): void
    {
        $ti = $this->teamInfoRow(17);
        self::assertNotSame('', $ti['color1']);

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertArrayHasKey('H2HBeta', $axis);
        self::assertSame($ti['color1'], $axis['H2HBeta']['color1']);
        self::assertSame($ti['color2'], $axis['H2HBeta']['color2']);
        self::assertSame('new17.png', $axis['H2HBeta']['logo']);
        self::assertSame(17, $axis['H2HBeta']['franchise_id']);
    }

    public function testRetiredGmWhoseEraMatchesCurrentIdentityUsesTeamInfoColors(): void
    {
        $ti = $this->teamInfoRow(17);
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => 17,
            'season_year'        => 1896,
            'season_ending_year' => 1897,
            'team_city'          => $ti['team_city'],
            'team_name'          => $ti['team_name'],
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 17,
            'gm_display_name'     => 'H2HTheta',
            'start_season_year'   => 1896,
            'end_season_year'     => 1897,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertArrayHasKey('H2HTheta', $axis);
        self::assertSame($ti['color1'], $axis['H2HTheta']['color1']);
        self::assertSame($ti['color2'], $axis['H2HTheta']['color2']);
        self::assertSame('new17.png', $axis['H2HTheta']['logo']);
        self::assertSame(0, $axis['H2HTheta']['link_franchise_id']);
    }

    public function testRetiredGmWhoseEraDiffersFromCurrentIdentityUsesEraBrandingColors(): void
    {
        $ti = $this->teamInfoRow(17);
        self::assertNotSame(['San Antonio', 'Spurs'], [$ti['team_city'], $ti['team_name']]);

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertArrayHasKey('H2HAlpha', $axis);
        self::assertSame('C4CED4', $axis['H2HAlpha']['color1']);
        self::assertSame('000000', $axis['H2HAlpha']['color2']);
        self::assertNotSame($ti['color1'], $axis['H2HAlpha']['color1']);
        self::assertSame('new17.png', $axis['H2HAlpha']['logo']);
    }

    public function testRetiredGmWithFranchiseSeasonButNoEraBrandingGetsEmptyColors(): void
    {
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => 22,
            'season_year'        => 1898,
            'season_ending_year' => 1899,
            'team_city'          => 'Nowhere',
            'team_name'          => 'Nobodies',
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 22,
            'gm_display_name'     => 'H2HEta',
            'start_season_year'   => 1898,
            'end_season_year'     => 1899,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertArrayHasKey('H2HEta', $axis);
        self::assertSame('', $axis['H2HEta']['color1']);
        self::assertSame('', $axis['H2HEta']['color2']);
        self::assertSame('new22.png', $axis['H2HEta']['logo']);
        self::assertSame(22, $axis['H2HEta']['franchise_id']);
    }

    public function testRetiredGmWithoutFranchiseSeasonRowGetsEmptyColorsAndDefaultLogo(): void
    {
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 3,
            'gm_display_name'     => 'H2HZeta',
            'start_season_year'   => 1894,
            'end_season_year'     => 1895,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertArrayHasKey('H2HZeta', $axis);
        self::assertSame('', $axis['H2HZeta']['color1']);
        self::assertSame('', $axis['H2HZeta']['color2']);
        self::assertSame('new3.png', $axis['H2HZeta']['logo']);
        self::assertSame(3, $axis['H2HZeta']['franchise_id']);
        self::assertSame(0, $axis['H2HZeta']['link_franchise_id']);
    }

    public function testCustomLogoResolverIsCalledOnlyForRetiredGmsWithFranchiseSeasonRow(): void
    {
        /** @var list<array{int, string}> $calls */
        $calls = [];
        $resolver = static function (int $id, string $name) use (&$calls): string {
            $calls[] = [$id, $name];
            return "custom-{$id}-{$name}.svg";
        };

        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id'       => 22,
            'season_year'        => 1898,
            'season_ending_year' => 1899,
            'team_city'          => 'Nowhere',
            'team_name'          => 'Nobodies',
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 22,
            'gm_display_name'     => 'H2HEta',
            'start_season_year'   => 1898,
            'end_season_year'     => 1899,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 3,
            'gm_display_name'     => 'H2HZeta',
            'start_season_year'   => 1894,
            'end_season_year'     => 1895,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        $repo = new HeadToHeadRecordsRepository($this->db, 1901, $resolver);
        $axis = $this->axisByKey($repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertSame('custom-17-Spurs.svg', $axis['H2HAlpha']['logo']);
        self::assertSame('custom-22-Nobodies.svg', $axis['H2HEta']['logo']);
        self::assertSame('new3.png', $axis['H2HZeta']['logo']);
        self::assertSame('new17.png', $axis['H2HBeta']['logo']);
        self::assertSame('new22.png', $axis['H2HGamma']['logo']);
        self::assertSame([[17, 'Spurs'], [22, 'Nobodies']], $calls);
    }

    public function testCurrentScopeDerivesGmAxisFromRecordsOnly(): void
    {
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id'        => 3,
            'gm_display_name'     => 'H2HZeta',
            'start_season_year'   => 1894,
            'end_season_year'     => 1895,
            'is_mid_season_start' => 0,
            'is_mid_season_end'   => 0,
        ]);

        $payload = $this->repo->buildGmsMatrix('regular', 'current');

        self::assertSame(['H2HAlpha', 'H2HGamma'], array_column($payload['axis'], 'key'));
    }

    public function testCurrentScopeWithNoCurrentSeasonGamesReturnsEmptyAxis(): void
    {
        $repo = new HeadToHeadRecordsRepository($this->db, 8888);

        $payload = $repo->buildGmsMatrix('regular', 'current');

        self::assertSame([], $payload['records']);
        self::assertSame([], $payload['axis']);
    }

    public function testBestTenurePrefersCurrentThenLatestEndThenLowestId(): void
    {
        $tenures = [
            ['H2HIota', 1, 1890, 1891],
            ['H2HIota', 2, 1892, 1893],
            ['H2HKappa', 1, 1892, 1893],
            ['H2HKappa', 2, 1894, null],
            ['H2HLambda', 2, 1888, 1889],
            ['H2HLambda', 1, 1888, 1889],
        ];
        foreach ($tenures as [$gm, $fid, $start, $end]) {
            $row = [
                'franchise_id'        => $fid,
                'gm_display_name'     => $gm,
                'start_season_year'   => $start,
                'is_mid_season_start' => 0,
                'is_mid_season_end'   => 0,
            ];
            if ($end !== null) {
                $row['end_season_year'] = $end;
            }
            $this->insertRow('ibl_gm_tenures', $row);
        }

        $axis = $this->axisByKey($this->repo->buildGmsMatrix('regular', 'all')['axis']);

        self::assertSame(2, $axis['H2HIota']['franchise_id']);
        self::assertSame(2, $axis['H2HKappa']['franchise_id']);
        self::assertSame(2, $axis['H2HKappa']['link_franchise_id']);
        self::assertSame(2, $axis['H2HLambda']['franchise_id']);
    }

    // -------------------------------------------------------------------------
    // Teams dimension
    // -------------------------------------------------------------------------

    public function testRetiredEraGameLandsOnEraRowInTeamsDimension(): void
    {
        $payload = $this->repo->buildTeamsMatrix('regular', 'current');

        // The game was visitor=22 (Seattle Supersonics) vs home=17 (San Antonio Spurs) in 1901.
        $seattleKey = '22|Seattle|Supersonics';
        $spursKey   = '17|San Antonio|Spurs';

        self::assertArrayHasKey($seattleKey, $payload['records']);
        self::assertArrayHasKey($spursKey, $payload['records'][$seattleKey]);
        self::assertSame(1, $payload['records'][$seattleKey][$spursKey]['wins']);
        self::assertSame(0, $payload['records'][$seattleKey][$spursKey]['losses']);
    }

    public function testRetiredEraGameLandsOnFranchiseRowInFranchisesDimension(): void
    {
        $payload = $this->repo->buildFranchisesMatrix('regular', 'current');

        // Franchises dimension uses teamid as key.
        self::assertArrayHasKey('22', $payload['records']);
        self::assertArrayHasKey('17', $payload['records']['22']);
        self::assertSame(1, $payload['records']['22']['17']['wins']);
        self::assertSame(0, $payload['records']['22']['17']['losses']);
        self::assertSame(0, $payload['records']['17']['22']['wins']);
        self::assertSame(1, $payload['records']['17']['22']['losses']);
    }

    public function testRetiredEraRowCarriesSeededBrandingColors(): void
    {
        $payload = $this->repo->buildTeamsMatrix('regular', 'current');

        // Seattle Supersonics era: color1='00653A', color2='FFC200' per migration 182.
        $seattleEntry = null;
        foreach ($payload['axis'] as $entry) {
            if ($entry['key'] === '22|Seattle|Supersonics') {
                $seattleEntry = $entry;
                break;
            }
        }

        self::assertNotNull($seattleEntry, 'Expected axis entry for 22|Seattle|Supersonics');
        self::assertSame('00653A', $seattleEntry['color1']);
        self::assertSame('FFC200', $seattleEntry['color2']);
    }

    public function testCurrentIdentityRowCarriesTeamInfoColors(): void
    {
        // Fetch franchise 22's current identity from ibl_team_info.
        $stmt = $this->db->prepare(
            'SELECT team_city, team_name, color1, color2 FROM ibl_team_info WHERE teamid = 22',
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $ti = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($ti);
        self::assertIsString($ti['team_city']);
        self::assertIsString($ti['team_name']);

        // Build teams matrix for all seasons (real data included).
        $payload = $this->repo->buildTeamsMatrix('regular', 'all');

        // Find the current identity axis entry.
        $currentKey = "22|{$ti['team_city']}|{$ti['team_name']}";
        $found = null;
        foreach ($payload['axis'] as $entry) {
            if ($entry['key'] === $currentKey) {
                $found = $entry;
                break;
            }
        }

        self::assertNotNull($found, "Expected current-identity axis entry for {$currentKey}");
        self::assertSame($ti['color1'], $found['color1'], 'color1 should come from ibl_team_info');
        self::assertSame($ti['color2'], $found['color2'], 'color2 should come from ibl_team_info');
    }

    // -------------------------------------------------------------------------
    // Negative path / validation
    // -------------------------------------------------------------------------

    public function testUnknownPhaseThrowsBeforeSql(): void
    {
        $this->expectException(\UnhandledMatchError::class);
        $this->repo->buildFranchisesMatrix('preseason', 'all');
    }

    // -------------------------------------------------------------------------
    // Scope filter
    // -------------------------------------------------------------------------

    public function testCurrentScopeOnlyCountsCurrentSeasonGames(): void
    {
        // Insert an extra game in season 1900 (game_date='1900-03-15': MONTH=3 → game_type=1,
        // season_year=1900) so that 'all' scope sees 2 games but 'current' (1901) sees only 1.
        $pastGame = [
            'game_date'          => '1900-03-15',
            'name'               => 'Supersonics',
            'game_of_that_day'   => 1,
            'visitor_teamid'     => 22,
            'home_teamid'        => 17,
            'attendance'         => 10000,
            'capacity'           => 15000,
            'visitor_wins'       => 0,
            'visitor_losses'     => 0,
            'home_wins'          => 0,
            'home_losses'        => 0,
            'visitor_q1_points'  => 30,
            'visitor_q2_points'  => 25,
            'visitor_q3_points'  => 25,
            'visitor_q4_points'  => 25,
            'visitor_ot_points'  => 0,
            'home_q1_points'     => 20,
            'home_q2_points'     => 20,
            'home_q3_points'     => 20,
            'home_q4_points'     => 20,
            'home_ot_points'     => 0,
            'game_2gm'           => 30,
            'game_2ga'           => 60,
            'game_ftm'           => 15,
            'game_fta'           => 20,
            'game_3gm'           => 8,
            'game_3ga'           => 22,
            'game_orb'           => 10,
            'game_drb'           => 30,
            'game_ast'           => 20,
            'game_stl'           => 8,
            'game_tov'           => 12,
            'game_blk'           => 5,
            'game_pf'            => 18,
        ];
        $this->insertRow('ibl_box_scores_teams', $pastGame);
        $this->insertRow('ibl_box_scores_teams', array_merge($pastGame, ['name' => 'Spurs']));

        // current scope (season_year=1901): only the 1901 game.
        // 1 game × 2 perspectives = 2 total (wins+losses summed across both sides).
        $currentPayload = $this->repo->buildFranchisesMatrix('regular', 'current');
        $currentTotal   = 0;
        foreach ($currentPayload['records'] as $oppMap) {
            foreach ($oppMap as $matchup) {
                $currentTotal += $matchup['wins'] + $matchup['losses'];
            }
        }

        // all scope: 1901 game + 1900 game = 2 games × 2 perspectives = 4.
        $allPayload = $this->repo->buildFranchisesMatrix('regular', 'all');
        $allTotal   = 0;
        foreach ($allPayload['records'] as $oppMap) {
            foreach ($oppMap as $matchup) {
                $allTotal += $matchup['wins'] + $matchup['losses'];
            }
        }

        self::assertSame(2, $currentTotal, 'current scope should see exactly the 1901 test game');
        self::assertSame(4, $allTotal, 'all scope should see both the 1901 and 1900 test games');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute the de-duplicated game count for the given (dimension, phase, scope)
     * using dimension-specific SQL that mirrors the repository's own filtering.
     */
    private function computeDeduplicatedCount(string $dimension, string $phase, string $scope): int
    {
        $maxTid = League::MAX_REAL_TEAMID;
        $phaseFilter = match ($phase) {
            'heat'     => '= 3',
            'regular'  => '= 1',
            'playoffs' => '= 2',
            'all'      => 'IN (1, 2, 3)',
            default    => throw new \InvalidArgumentException("Unhandled phase: {$phase}"),
        };
        $scopeFilter = $scope === 'current' ? ' AND b.season_year = 1901' : '';

        if ($dimension === 'franchises') {
            $sql = "SELECT COUNT(*) AS cnt FROM (
                SELECT 1 FROM ibl_box_scores_teams b
                WHERE b.game_type {$phaseFilter}
                  AND b.visitor_teamid BETWEEN 1 AND {$maxTid}
                  AND b.home_teamid   BETWEEN 1 AND {$maxTid}
                  {$scopeFilter}
                GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
            ) g";
        } elseif ($dimension === 'teams') {
            $sql = "SELECT COUNT(*) AS cnt FROM (
                SELECT 1 FROM ibl_box_scores_teams b
                JOIN ibl_franchise_seasons sv
                    ON sv.franchise_id = b.visitor_teamid AND sv.season_ending_year = b.season_year
                JOIN ibl_franchise_seasons sh
                    ON sh.franchise_id = b.home_teamid   AND sh.season_ending_year = b.season_year
                WHERE b.game_type {$phaseFilter}
                  AND b.visitor_teamid BETWEEN 1 AND {$maxTid}
                  AND b.home_teamid   BETWEEN 1 AND {$maxTid}
                  {$scopeFilter}
                GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
            ) g";
        } else {
            // gms: count games where both teams have attributed GMs and GMs differ.
            $sql = "WITH unique_games AS (
                SELECT MIN(id) AS id
                FROM ibl_box_scores_teams b
                WHERE b.game_type {$phaseFilter}
                  AND b.visitor_teamid BETWEEN 1 AND {$maxTid}
                  AND b.home_teamid   BETWEEN 1 AND {$maxTid}
                  {$scopeFilter}
                GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
            ),
            games AS (
                SELECT b.season_year, b.visitor_teamid, b.home_teamid
                FROM ibl_box_scores_teams b
                JOIN unique_games u ON u.id = b.id
            ),
            gm_attr AS (
                SELECT g.*,
                    (SELECT t.gm_display_name FROM ibl_gm_tenures t
                      WHERE t.franchise_id = g.visitor_teamid
                        AND g.season_year BETWEEN (t.start_season_year + t.is_mid_season_start)
                                              AND COALESCE(t.end_season_year, 9999)
                      ORDER BY t.start_season_year ASC, t.id ASC LIMIT 1) AS vgm,
                    (SELECT t.gm_display_name FROM ibl_gm_tenures t
                      WHERE t.franchise_id = g.home_teamid
                        AND g.season_year BETWEEN (t.start_season_year + t.is_mid_season_start)
                                              AND COALESCE(t.end_season_year, 9999)
                      ORDER BY t.start_season_year ASC, t.id ASC LIMIT 1) AS hgm
                FROM games g
            )
            SELECT COUNT(*) AS cnt FROM gm_attr
            WHERE vgm IS NOT NULL AND hgm IS NOT NULL AND vgm <> hgm";
        }

        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, "Failed to prepare dedup count query for {$dimension}/{$phase}/{$scope}");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * @param list<AxisEntry> $axis
     * @return array<string, AxisEntry>
     */
    private function axisByKey(array $axis): array
    {
        $byKey = [];
        foreach ($axis as $entry) {
            $byKey[$entry['key']] = $entry;
        }
        return $byKey;
    }

    /** @return array{team_city: string, team_name: string, color1: string, color2: string} */
    private function teamInfoRow(int $teamid): array
    {
        $stmt = $this->db->prepare('SELECT team_city, team_name, color1, color2 FROM ibl_team_info WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);
        self::assertIsString($row['team_city'] ?? null, 'ibl_team_info.team_city must be a string');
        self::assertIsString($row['team_name'] ?? null, 'ibl_team_info.team_name must be a string');
        self::assertIsString($row['color1'] ?? null, 'ibl_team_info.color1 must be a string');
        self::assertIsString($row['color2'] ?? null, 'ibl_team_info.color2 must be a string');
        return [
            'team_city' => $row['team_city'],
            'team_name' => $row['team_name'],
            'color1'    => $row['color1'],
            'color2'    => $row['color2'],
        ];
    }
}
