<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Admin\Contracts\FreeAgencyAdminRepositoryInterface;
use FreeAgency\Admin\FreeAgencyAdminProcessor;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Characterization tests for FreeAgencyAdminProcessor::processDay().
 *
 * Pins the current processDay output and read order so the private-method split
 * preserves exact behavior. processDay is read-only (no writes, no transaction),
 * so the order that matters is the repository calls and the Team/Player lookups.
 * Each test is labelled with the invariant it guards.
 */
class FreeAgencyAdminProcessorProcessDayCharacterizationTest extends TestCase
{
    private const DAY = 3;
    private const PROCESSED_AT = '2026-10-01 09:00:00';

    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();

        // One team row per name; Delta is the team every fixture player belongs to.
        $teams = [
            'Alpha' => [1, 1111],
            'Bravo' => [2, 2222],
            'Charlie' => [3, 3333],
            'Delta' => [4, 4444],
        ];
        $teamRows = [];
        foreach ($teams as $name => [$teamId, $discordId]) {
            $teamRows[] = $this->makeTeamRow($teamId, $name, $discordId);
        }
        $this->mockDb->setMockTeamData($teamRows);
        foreach ($teamRows as $teamRow) {
            $this->mockDb->onQuery("team_name = '" . $teamRow['team_name'] . "'", [$teamRow]);
        }

        $players = [101 => 'Player A', 102 => 'Player B', 103 => 'Player C', 104 => 'Player D'];
        foreach ($players as $pid => $name) {
            $this->mockDb->onQuery('p\.pid = ' . $pid . '\b', [$this->makePlayerRow($pid, $name)]);
        }

        $this->mockDb->clearQueries();
    }

    // Invariant: the whole return array (all eight keys, key order, byte-exact text) for a
    // day that hits every branch and both comparison boundaries.
    public function testProcessDayReturnsExactResultForMixedOfferDay(): void
    {
        $repoLog = [];
        $processor = new FreeAgencyAdminProcessor($this->buildRepository($this->mixedOffers(), $repoLog), $this->mockDb);

        $result = $processor->processDay(self::DAY);

        // Shape guards: a fixture edit cannot quietly skip a branch.
        $this->assertCount(2, $result['signings']);
        $this->assertCount(1, $result['rejections']);
        $this->assertCount(2, $result['autoRejections']);
        $this->assertTrue($result['signings'][0]['usedMle']);

        $this->assertSame($this->expectedMixedResult(), $result);
    }

    // Invariant: read order. Offers, then demand batch (before any lookup), then per-offer
    // lookups, then the processed marker (after every lookup).
    public function testProcessDayReadsRepositoryAndLookupsInFixedOrder(): void
    {
        $repoLog = [];
        $processor = new FreeAgencyAdminProcessor($this->buildRepository($this->mixedOffers(), $repoLog), $this->mockDb);

        $processor->processDay(self::DAY);

        $trace = $this->lookupTrace();
        $this->assertSame(
            [
                'getAllOffersWithBirdYears@0',
                'getPlayerDemandsBatch[101,102,104,103]@0',
                'getDayProcessedMarker(3)@' . count($trace),
            ],
            $repoLog
        );
        $this->assertSame(
            [
                // Offer 1: first offer for Player A.
                'team:Alpha', 'player:101', 'team:Delta',
                // Offer 2: extra offer.
                'team:Bravo',
                // Offer 3: auto-reject, no lookup.
                // Offer 4: first offer for Player B.
                'team:Bravo', 'player:102', 'team:Delta',
                // Offer 5: extra offer.
                'team:Alpha',
                // Offer 6: auto-reject, no lookup.
                // Offer 7: first offer for Player C.
                'team:Charlie', 'player:103', 'team:Delta',
            ],
            $trace
        );
    }

    // Invariant: an empty offer list still loads the demand batch with [] and reads the
    // marker last, and runs no Team/Player lookup.
    public function testProcessDayWithNoOffersReturnsEmptyResultAndReadsOnlyMarker(): void
    {
        $repoLog = [];
        $processor = new FreeAgencyAdminProcessor($this->buildRepository([], $repoLog), $this->mockDb);

        $result = $processor->processDay(self::DAY);

        $this->assertSame(
            [
                'signings' => [],
                'rejections' => [],
                'autoRejections' => [],
                'allOffers' => [],
                'newsHomeText' => '',
                'newsBodyText' => '',
                'discordText' => '',
                'processed_at' => self::PROCESSED_AT,
            ],
            $result
        );
        $this->assertSame([], $this->lookupTrace());
        $this->assertSame(
            [
                'getAllOffersWithBirdYears@0',
                'getPlayerDemandsBatch[]@0',
                'getDayProcessedMarker(3)@0',
            ],
            $repoLog
        );
    }

    /**
     * Repository double that records each call with the DB lookup count at call time.
     *
     * @param list<array<string, mixed>> $offers
     * @param list<string> $repoLog
     * @return FreeAgencyAdminRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function buildRepository(array $offers, array &$repoLog): FreeAgencyAdminRepositoryInterface
    {
        $mockDb = $this->mockDb;
        $demands = [
            101 => ['dem1' => 1000, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            102 => ['dem1' => 500, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            104 => ['dem1' => 250, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ];

        $repository = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('getAllOffersWithBirdYears')
            ->willReturnCallback(static function () use (&$repoLog, $mockDb, $offers): array {
                $repoLog[] = 'getAllOffersWithBirdYears@' . count($mockDb->getExecutedQueries());
                return $offers;
            });
        $repository->expects(self::once())
            ->method('getPlayerDemandsBatch')
            ->willReturnCallback(static function (array $ids) use (&$repoLog, $mockDb, $demands): array {
                $repoLog[] = 'getPlayerDemandsBatch[' . implode(',', $ids) . ']@' . count($mockDb->getExecutedQueries());
                return $demands;
            });
        $repository->expects(self::once())
            ->method('getDayProcessedMarker')
            ->willReturnCallback(static function (int $day) use (&$repoLog, $mockDb): string {
                $repoLog[] = 'getDayProcessedMarker(' . $day . ')@' . count($mockDb->getExecutedQueries());
                return self::PROCESSED_AT;
            });

        return $repository;
    }

    /**
     * One token per DB query, in execution order: "<player|team|other>:<key>".
     *
     * @return list<string>
     */
    private function lookupTrace(): array
    {
        $tokens = [];
        foreach ($this->mockDb->getExecutedQueries() as $sql) {
            if (str_contains($sql, 'ibl_plr')) {
                $key = preg_match('/pid = (\d+)/', $sql, $m) === 1 ? $m[1] : '?';
                $tokens[] = 'player:' . $key;
            } elseif (str_contains($sql, 'ibl_team_info')) {
                $key = preg_match("/team_name = '(\w+)'/", $sql, $m) === 1 ? $m[1] : '?';
                $tokens[] = 'team:' . $key;
            } else {
                $tokens[] = 'other:' . $sql;
            }
        }
        return $tokens;
    }

    /**
     * Seven offers in repository order (by player, then perceived value descending).
     *
     * @return list<array<string, mixed>>
     */
    private function mixedOffers(): array
    {
        return [
            // Day 3 demands: A = 800 (half 400), B = 400 (half 200), D = 200 (half 100), C has no row (0.0).
            $this->makeOfferRow('Player A', 101, 'Alpha', 1, [500, 550, 600, 0, 0, 0], 2, 1, 0, 3, 1000.0),
            $this->makeOfferRow('Player A', 101, 'Bravo', 2, [400, 450, 0, 0, 0, 0], 0, 0, 0, 5, 600.0),
            $this->makeOfferRow('Player A', 101, 'Charlie', 3, [300, 0, 0, 0, 0, 0], 0, 0, 0, 7, 400.0),
            $this->makeOfferRow('Player B', 102, 'Bravo', 2, [350, 350, 0, 0, 0, 0], 1, 0, 0, 2, 400.0),
            $this->makeOfferRow('Player B', 102, 'Alpha', 1, [200, 0, 0, 0, 0, 0], 3, 0, 1, 4, 300.0),
            $this->makeOfferRow('Player D', 104, 'Charlie', 3, [100, 0, 0, 0, 0, 0], 0, 0, 0, 6, 50.0),
            $this->makeOfferRow('Player C', 103, 'Charlie', 3, [250, 260, 270, 280, 0, 0], 0, 0, 0, 1, 50.0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedMixedResult(): array
    {
        $offersOf = static fn (int $o1, int $o2, int $o3, int $o4, int $o5, int $o6): array => [
            'offer1' => $o1, 'offer2' => $o2, 'offer3' => $o3,
            'offer4' => $o4, 'offer5' => $o5, 'offer6' => $o6,
        ];
        $allOffer = static fn (string $player, string $team, array $offers, int $bird, int $mle, int $lle, int $random, float $pv): array => [
            'playerName' => $player,
            'teamName' => $team,
            'offers' => $offers,
            'birdYears' => $bird,
            'mle' => $mle,
            'lle' => $lle,
            'random' => $random,
            'perceivedValue' => $pv,
        ];

        return [
            'signings' => [
                [
                    'playerName' => 'Player A',
                    'playerId' => 101,
                    'teamName' => 'Alpha',
                    'teamId' => 1,
                    'offers' => $offersOf(500, 550, 600, 0, 0, 0),
                    'offerYears' => 3,
                    'offerTotal' => 16.5,
                    'usedMle' => true,
                    'usedLle' => false,
                ],
                [
                    'playerName' => 'Player C',
                    'playerId' => 103,
                    'teamName' => 'Charlie',
                    'teamId' => 3,
                    'offers' => $offersOf(250, 260, 270, 280, 0, 0),
                    'offerYears' => 4,
                    'offerTotal' => 10.6,
                    'usedMle' => false,
                    'usedLle' => false,
                ],
            ],
            'rejections' => [
                ['playerName' => 'Player B', 'reason' => 'Best offer did not meet player demands'],
            ],
            'autoRejections' => [
                [
                    'playerName' => 'Player A',
                    'teamName' => 'Charlie',
                    'offers' => $offersOf(300, 0, 0, 0, 0, 0),
                    'reason' => 'Offer under half of player demands',
                ],
                [
                    'playerName' => 'Player D',
                    'teamName' => 'Charlie',
                    'offers' => $offersOf(100, 0, 0, 0, 0, 0),
                    'reason' => 'Offer under half of player demands',
                ],
            ],
            'allOffers' => [
                $allOffer('Player A', 'Alpha', $offersOf(500, 550, 600, 0, 0, 0), 2, 1, 0, 3, 1000.0),
                $allOffer('Player A', 'Bravo', $offersOf(400, 450, 0, 0, 0, 0), 0, 0, 0, 5, 600.0),
                $allOffer('Player A', 'Charlie', $offersOf(300, 0, 0, 0, 0, 0), 0, 0, 0, 7, 400.0),
                $allOffer('Player B', 'Bravo', $offersOf(350, 350, 0, 0, 0, 0), 1, 0, 0, 2, 400.0),
                $allOffer('Player B', 'Alpha', $offersOf(200, 0, 0, 0, 0, 0), 3, 0, 1, 4, 300.0),
                $allOffer('Player D', 'Charlie', $offersOf(100, 0, 0, 0, 0, 0), 0, 0, 0, 6, 50.0),
                $allOffer('Player C', 'Charlie', $offersOf(250, 260, 270, 280, 0, 0), 0, 0, 0, 1, 50.0),
            ],
            'newsHomeText' => "Player A accepts the Alpha offer of a 3-year deal worth a total of 16.5 million dollars.\n"
                . "Player C accepts the Charlie offer of a 4-year deal worth a total of 10.6 million dollars.\n",
            'newsBodyText' => "The Alpha offered Player A a 3-year deal worth a total of 16.5 million dollars.\n"
                . "The Bravo offered Player A a 2-year deal worth a total of 8.5 million dollars.\n"
                . "The Charlie offered Player A a 1-year deal worth a total of 3 million dollars.\n"
                . "The Bravo offered Player B a 2-year deal worth a total of 7 million dollars.\n"
                . "The Alpha offered Player B a 1-year deal worth a total of 2 million dollars.\n"
                . "The Charlie offered Player D a 1-year deal worth a total of 1 million dollars.\n"
                . "The Charlie offered Player C a 4-year deal worth a total of 10.6 million dollars.\n",
            'discordText' => "**PLAYER A, DELTA CITY DELTA** <@!4444>\n"
                . "Alpha - 500/550/600 <@!1111>\n"
                . "Bravo - 400/450 <@!2222>\n"
                . "Player A accepts the Alpha offer of a 3-year deal worth a total of 16.5 million dollars. <@!1111>\n\n"
                . "**PLAYER B, DELTA CITY DELTA** <@!4444>\n"
                . "Alpha - 200 <@!1111>\n"
                . "Bravo - 350/350 <@!2222>\n"
                . "**REJECTED**\n\n"
                . "**PLAYER C, DELTA CITY DELTA** <@!4444>\n"
                . "Charlie - 250/260/270/280 <@!3333>\n"
                . "Player C accepts the Charlie offer of a 4-year deal worth a total of 10.6 million dollars. <@!3333>\n\n",
            'processed_at' => self::PROCESSED_AT,
        ];
    }

    /**
     * @param list<int> $offers
     * @return array<string, mixed>
     */
    private function makeOfferRow(
        string $name,
        int $pid,
        string $team,
        int $teamId,
        array $offers,
        int $bird,
        int $mle,
        int $lle,
        int $random,
        float $perceivedValue
    ): array {
        return [
            'name' => $name,
            'pid' => $pid,
            'team' => $team,
            'teamid' => $teamId,
            'offer1' => $offers[0],
            'offer2' => $offers[1],
            'offer3' => $offers[2],
            'offer4' => $offers[3],
            'offer5' => $offers[4],
            'offer6' => $offers[5],
            'bird' => $bird,
            'mle' => $mle,
            'lle' => $lle,
            'random' => $random,
            'perceivedvalue' => $perceivedValue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTeamRow(int $teamId, string $name, int $discordId): array
    {
        return [
            'teamid' => $teamId,
            'team_city' => $name . ' City',
            'team_name' => $name,
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'arena' => 'Test Arena',
            'capacity' => 20000,
            'owner_name' => 'Owner',
            'owner_email' => 'owner@test.com',
            'discord_id' => $discordId,
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'has_mle' => 0,
            'has_lle' => 0,
            'contract_wins' => 40,
            'contract_losses' => 42,
            'contract_avg_w' => 40,
            'contract_avg_l' => 42,
            'league_record' => '40-42',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makePlayerRow(int $pid, string $name): array
    {
        return [
            'pid' => $pid,
            'ordinal' => 1,
            'name' => $name,
            'nickname' => null,
            'age' => 25,
            'peak' => 28,
            'teamid' => 4,
            'teamname' => 'Delta',
            'pos' => 'PF',
            'stamina' => 5,
            'oo' => 50, 'od' => 50, 'r_drive_off' => 50, 'dd' => 50,
            'po' => 50, 'pd' => 50, 'r_trans_off' => 50, 'td' => 50,
            'clutch' => 3, 'consistency' => 3,
            'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 5, 'c_depth' => 0,
            'dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 5, 'dc_c_depth' => 0,
            'dc_can_play_in_game' => 1, 'dc_minutes' => 30,
            'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 0, 'dc_di' => 0, 'dc_bh' => 0,
            'active' => 1,
            'talent' => 50, 'skill' => 50, 'intangibles' => 50, 'coach' => 0,
            'loyalty' => 3, 'playing_time' => 3, 'winner' => 3, 'tradition' => 3, 'security' => 3,
            'exp' => 5, 'bird' => 1,
            'cy' => 0, 'cyt' => 0, 'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
            'fa_signing_flag' => 0,
            'stats_gs' => 0, 'stats_gm' => 0, 'stats_min' => 0,
            'stats_fgm' => 0, 'stats_fga' => 0, 'stats_ftm' => 0, 'stats_fta' => 0,
            'stats_3gm' => 0, 'stats_3ga' => 0,
            'stats_orb' => 0, 'stats_drb' => 0, 'stats_ast' => 0,
            'stats_stl' => 0, 'stats_tvr' => 0, 'stats_blk' => 0, 'stats_pf' => 0,
            'r_fga' => 50, 'r_fgp' => 50, 'r_fta' => 50, 'r_ftp' => 50,
            'r_3ga' => 50, 'r_3gp' => 50, 'r_orb' => 50, 'r_drb' => 50,
            'r_ast' => 50, 'r_stl' => 50, 'r_tvr' => 50, 'r_blk' => 50, 'r_foul' => 50,
            'draftround' => 1, 'draftedby' => 'MIA', 'draftedbycurrentname' => 'Miami',
            'draftyear' => 2020, 'draftpickno' => 1,
            'injured' => 0, 'htft' => 6, 'htin' => 8, 'wt' => 220,
            'retired' => 0, 'college' => 'Test University',
            'sh_pts' => 0, 'sh_reb' => 0, 'sh_ast' => 0, 'sh_stl' => 0, 'sh_blk' => 0,
            's_dd' => 0, 's_td' => 0,
            'sp_pts' => 0, 'sp_reb' => 0, 'sp_ast' => 0, 'sp_stl' => 0, 'sp_blk' => 0,
            'ch_pts' => 0, 'ch_reb' => 0, 'ch_ast' => 0, 'ch_stl' => 0, 'ch_blk' => 0,
            'c_dd' => 0, 'c_td' => 0,
            'cp_pts' => 0, 'cp_reb' => 0, 'cp_ast' => 0, 'cp_stl' => 0, 'cp_blk' => 0,
            'car_gm' => 0, 'car_min' => 0,
            'car_fgm' => 0, 'car_fga' => 0, 'car_ftm' => 0, 'car_fta' => 0,
            'car_3gm' => 0, 'car_3ga' => 0,
            'car_orb' => 0, 'car_drb' => 0, 'car_reb' => 0,
            'car_ast' => 0, 'car_stl' => 0, 'car_tvr' => 0, 'car_blk' => 0,
            'car_pf' => 0, 'car_pts' => 0,
            'car_playoff_min' => 0, 'car_preseason_min' => 0,
            'droptime' => 0,
        ];
    }
}
