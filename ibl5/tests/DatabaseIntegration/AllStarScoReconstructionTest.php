<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\BoxscoreProcessor;
use Boxscore\BoxscoreRepository;
use JsbParser\ScoFileWriter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Acceptance test: ScoFileWriter-encoded block → BoxscoreProcessor → 48 correct DB rows.
 *
 * Verifies that the 4000-byte All-Star header block produced by ScoFileWriter,
 * when fed through the real processAllStarGamesData path, yields exactly the
 * 48 rows expected for the 2006-07 All-Star Weekend (10+10 RSG + 12+12 ASG
 * player rows, plus 4 team-total rows).
 *
 * @covers \JsbParser\ScoFileWriter
 * @covers \Boxscore\BoxscoreProcessor
 */
#[Group('database')]
class AllStarScoReconstructionTest extends DatabaseTestCase
{
    private const RSG_DATE         = '2007-02-02';
    private const RSG_VISITOR_TID  = 40;
    private const RSG_HOME_TID     = 41;
    private const RSG_VISITOR_NAME = 'Rookies';
    private const RSG_HOME_NAME    = 'Sophomores';

    private const ASG_DATE         = '2007-02-03';
    private const ASG_VISITOR_TID  = 50;
    private const ASG_HOME_TID     = 51;
    // ASG uses BoxscoreProcessor defaults (Outcome C — first import)
    private const ASG_VISITOR_NAME = BoxscoreProcessor::DEFAULT_AWAY_NAME;
    private const ASG_HOME_NAME    = BoxscoreProcessor::DEFAULT_HOME_NAME;

    // Sentinel player row date: > IBL_ALL_STAR_BREAK_END_DAY (Feb 4)
    private const SENTINEL_DATE    = '2007-02-15';

    private BoxscoreRepository $repo;
    private BoxscoreProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo      = new BoxscoreRepository($this->db);
        $this->processor = new BoxscoreProcessor($this->db);
    }

    // ── Dataset builder ───────────────────────────────────────────────────────

    private function buildBlock(): string
    {
        $risingStars = [
            'visitor_name'    => self::RSG_VISITOR_NAME,
            'home_name'       => self::RSG_HOME_NAME,
            'visitor_q'       => [34, 39, 30, 31, 0],
            'home_q'          => [34, 31, 39, 41, 0],
            'visitor_teamid'  => self::RSG_VISITOR_TID,
            'home_teamid'     => self::RSG_HOME_TID,
            'attendance'      => 5244,
            'capacity'        => 20000,
            'visitor_team'    => ['twoGM' => 47, 'twoGA' => 98,  'ftm' => 16, 'fta' => 21, 'threeGM' => 8,  'threeGA' => 21, 'orb' => 25, 'drb' => 41, 'ast' => 30, 'stl' => 11, 'tov' => 22, 'blk' => 8,  'pf' => 19],
            'home_team'       => ['twoGM' => 52, 'twoGA' => 100, 'ftm' => 5,  'fta' => 9,  'threeGM' => 12, 'threeGA' => 27, 'orb' => 24, 'drb' => 43, 'ast' => 34, 'stl' => 10, 'tov' => 21, 'blk' => 14, 'pf' => 21],
            'visitor_players' => [
                ['name' => 'Player V1',  'pos' => 'PG', 'pid' => 5936, 'min' => 32, 'twoGM' => 7,  'twoGA' => 15, 'ftm' => 5, 'fta' => 5, 'threeGM' => 0, 'threeGA' => 3, 'orb' => 3, 'drb' => 1,  'ast' => 7,  'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 3],
                ['name' => 'Player V2',  'pos' => 'PG', 'pid' => 5938, 'min' => 16, 'twoGM' => 1,  'twoGA' => 2,  'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 5, 'orb' => 1, 'drb' => 0,  'ast' => 1,  'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 0],
                ['name' => 'Player V3',  'pos' => 'SG', 'pid' => 5931, 'min' => 32, 'twoGM' => 8,  'twoGA' => 16, 'ftm' => 3, 'fta' => 5, 'threeGM' => 2, 'threeGA' => 5, 'orb' => 4, 'drb' => 4,  'ast' => 0,  'stl' => 3, 'tov' => 1, 'blk' => 1, 'pf' => 2],
                ['name' => 'Player V4',  'pos' => 'SG', 'pid' => 5930, 'min' => 19, 'twoGM' => 1,  'twoGA' => 3,  'ftm' => 2, 'fta' => 3, 'threeGM' => 2, 'threeGA' => 2, 'orb' => 1, 'drb' => 1,  'ast' => 2,  'stl' => 2, 'tov' => 1, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player V5',  'pos' => 'SF', 'pid' => 5937, 'min' => 29, 'twoGM' => 5,  'twoGA' => 11, 'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 0, 'drb' => 4,  'ast' => 11, 'stl' => 1, 'tov' => 5, 'blk' => 0, 'pf' => 2],
                ['name' => 'Player V6',  'pos' => 'SF', 'pid' => 5939, 'min' => 13, 'twoGM' => 6,  'twoGA' => 11, 'ftm' => 1, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 3,  'ast' => 4,  'stl' => 0, 'tov' => 3, 'blk' => 1, 'pf' => 4],
                ['name' => 'Player V7',  'pos' => 'PF', 'pid' => 5929, 'min' => 30, 'twoGM' => 7,  'twoGA' => 15, 'ftm' => 2, 'fta' => 2, 'threeGM' => 2, 'threeGA' => 3, 'orb' => 3, 'drb' => 4,  'ast' => 2,  'stl' => 1, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player V8',  'pos' => 'PF', 'pid' => 5935, 'min' => 30, 'twoGM' => 7,  'twoGA' => 13, 'ftm' => 3, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 6, 'drb' => 6,  'ast' => 2,  'stl' => 4, 'tov' => 3, 'blk' => 1, 'pf' => 3],
                ['name' => 'Player V9',  'pos' => 'C',  'pid' => 5942, 'min' => 14, 'twoGM' => 2,  'twoGA' => 5,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 3, 'drb' => 4,  'ast' => 0,  'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'Player V10', 'pos' => 'C',  'pid' => 5964, 'min' => 22, 'twoGM' => 3,  'twoGA' => 7,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 8,  'ast' => 1,  'stl' => 0, 'tov' => 0, 'blk' => 1, 'pf' => 2],
            ],
            'home_players'    => [
                ['name' => 'Player H1',  'pos' => 'PG', 'pid' => 5640, 'min' => 31, 'twoGM' => 13, 'twoGA' => 21, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 2,  'orb' => 4,  'drb' => 7,  'ast' => 12, 'stl' => 3, 'tov' => 6, 'blk' => 3, 'pf' => 1],
                ['name' => 'Player H2',  'pos' => 'PG', 'pid' => 5649, 'min' => 13, 'twoGM' => 1,  'twoGA' => 3,  'ftm' => 3, 'fta' => 4, 'threeGM' => 1, 'threeGA' => 2,  'orb' => 0,  'drb' => 1,  'ast' => 5,  'stl' => 3, 'tov' => 2, 'blk' => 0, 'pf' => 0],
                ['name' => 'Player H3',  'pos' => 'SG', 'pid' => 5642, 'min' => 20, 'twoGM' => 10, 'twoGA' => 12, 'ftm' => 1, 'fta' => 3, 'threeGM' => 1, 'threeGA' => 2,  'orb' => 1,  'drb' => 1,  'ast' => 6,  'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 2],
                ['name' => 'Player H4',  'pos' => 'SG', 'pid' => 5659, 'min' => 30, 'twoGM' => 4,  'twoGA' => 13, 'ftm' => 0, 'fta' => 0, 'threeGM' => 6, 'threeGA' => 11, 'orb' => 0,  'drb' => 5,  'ast' => 5,  'stl' => 2, 'tov' => 3, 'blk' => 2, 'pf' => 2],
                ['name' => 'Player H5',  'pos' => 'SF', 'pid' => 5645, 'min' => 31, 'twoGM' => 7,  'twoGA' => 14, 'ftm' => 1, 'fta' => 1, 'threeGM' => 2, 'threeGA' => 5,  'orb' => 0,  'drb' => 5,  'ast' => 1,  'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 4],
                ['name' => 'Player H6',  'pos' => 'SF', 'pid' => 5685, 'min' => 20, 'twoGM' => 1,  'twoGA' => 5,  'ftm' => 0, 'fta' => 0, 'threeGM' => 2, 'threeGA' => 4,  'orb' => 0,  'drb' => 1,  'ast' => 2,  'stl' => 1, 'tov' => 0, 'blk' => 0, 'pf' => 2],
                ['name' => 'Player H7',  'pos' => 'PF', 'pid' => 5646, 'min' => 17, 'twoGM' => 3,  'twoGA' => 5,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0,  'orb' => 2,  'drb' => 4,  'ast' => 0,  'stl' => 1, 'tov' => 2, 'blk' => 0, 'pf' => 5],
                ['name' => 'DNP Player', 'pos' => 'PF', 'pid' => 5663, 'min' => 0,  'twoGM' => 0,  'twoGA' => 0,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0,  'orb' => 0,  'drb' => 0,  'ast' => 0,  'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 0],
                ['name' => 'Player H9',  'pos' => 'C',  'pid' => 5641, 'min' => 31, 'twoGM' => 7,  'twoGA' => 16, 'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 1,  'orb' => 10, 'drb' => 6,  'ast' => 2,  'stl' => 0, 'tov' => 1, 'blk' => 4, 'pf' => 4],
                ['name' => 'Player H10', 'pos' => 'C',  'pid' => 5644, 'min' => 43, 'twoGM' => 6,  'twoGA' => 11, 'ftm' => 0, 'fta' => 1, 'threeGM' => 0, 'threeGA' => 0,  'orb' => 6,  'drb' => 11, 'ast' => 1,  'stl' => 0, 'tov' => 4, 'blk' => 5, 'pf' => 1],
            ],
        ];

        $allStar = [
            'visitor_name'    => self::ASG_VISITOR_NAME,
            'home_name'       => self::ASG_HOME_NAME,
            'visitor_q'       => [43, 31, 44, 26, 0],
            'home_q'          => [43, 48, 55, 36, 0],
            'visitor_teamid'  => self::ASG_VISITOR_TID,
            'home_teamid'     => self::ASG_HOME_TID,
            'attendance'      => 5244,
            'capacity'        => 20000,
            'visitor_team'    => ['twoGM' => 48, 'twoGA' => 88,  'ftm' => 33, 'fta' => 38, 'threeGM' => 5,  'threeGA' => 20, 'orb' => 11, 'drb' => 42, 'ast' => 23, 'stl' => 4,  'tov' => 20, 'blk' => 10, 'pf' => 23],
            'home_team'       => ['twoGM' => 60, 'twoGA' => 102, 'ftm' => 26, 'fta' => 29, 'threeGM' => 12, 'threeGA' => 30, 'orb' => 20, 'drb' => 47, 'ast' => 39, 'stl' => 16, 'tov' => 10, 'blk' => 9,  'pf' => 28],
            'visitor_players' => [
                ['name' => 'ASG V01', 'pos' => 'PG', 'pid' => 3852, 'min' => 13, 'twoGM' => 2, 'twoGA' => 4,  'ftm' => 2, 'fta' => 2, 'threeGM' => 1, 'threeGA' => 4, 'orb' => 0, 'drb' => 2, 'ast' => 3, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG V02', 'pos' => 'PG', 'pid' => 5640, 'min' => 17, 'twoGM' => 3, 'twoGA' => 4,  'ftm' => 4, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 2, 'ast' => 5, 'stl' => 0, 'tov' => 2, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG V03', 'pos' => 'PG', 'pid' => 3851, 'min' => 28, 'twoGM' => 8, 'twoGA' => 11, 'ftm' => 4, 'fta' => 4, 'threeGM' => 1, 'threeGA' => 3, 'orb' => 0, 'drb' => 3, 'ast' => 4, 'stl' => 2, 'tov' => 2, 'blk' => 0, 'pf' => 1],
                ['name' => 'ASG V04', 'pos' => 'PF', 'pid' => 4148, 'min' => 21, 'twoGM' => 2, 'twoGA' => 4,  'ftm' => 5, 'fta' => 5, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 5, 'ast' => 2, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 4],
                ['name' => 'ASG V05', 'pos' => 'SF', 'pid' => 5258, 'min' => 25, 'twoGM' => 6, 'twoGA' => 12, 'ftm' => 5, 'fta' => 6, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 5, 'ast' => 4, 'stl' => 1, 'tov' => 3, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG V06', 'pos' => 'C',  'pid' => 4500, 'min' => 22, 'twoGM' => 7, 'twoGA' => 10, 'ftm' => 7, 'fta' => 7, 'threeGM' => 2, 'threeGA' => 2, 'orb' => 3, 'drb' => 4, 'ast' => 0, 'stl' => 0, 'tov' => 0, 'blk' => 5, 'pf' => 2],
                ['name' => 'ASG V07', 'pos' => 'SG', 'pid' => 3282, 'min' => 15, 'twoGM' => 0, 'twoGA' => 1,  'ftm' => 2, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 3, 'ast' => 3, 'stl' => 0, 'tov' => 2, 'blk' => 0, 'pf' => 4],
                ['name' => 'ASG V08', 'pos' => 'SG', 'pid' => 3561, 'min' => 13, 'twoGM' => 2, 'twoGA' => 5,  'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 0, 'drb' => 1, 'ast' => 0, 'stl' => 0, 'tov' => 3, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG V09', 'pos' => 'C',  'pid' => 2975, 'min' => 25, 'twoGM' => 7, 'twoGA' => 15, 'ftm' => 3, 'fta' => 6, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 1, 'drb' => 8, 'ast' => 1, 'stl' => 1, 'tov' => 3, 'blk' => 4, 'pf' => 4],
                ['name' => 'ASG V10', 'pos' => 'SF', 'pid' => 3277, 'min' => 15, 'twoGM' => 4, 'twoGA' => 8,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 5, 'orb' => 0, 'drb' => 0, 'ast' => 0, 'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 2],
                ['name' => 'ASG V11', 'pos' => 'SG', 'pid' => 5265, 'min' => 20, 'twoGM' => 5, 'twoGA' => 6,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 0, 'drb' => 3, 'ast' => 0, 'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG V12', 'pos' => 'PF', 'pid' => 4507, 'min' => 21, 'twoGM' => 2, 'twoGA' => 8,  'ftm' => 1, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 2, 'orb' => 1, 'drb' => 4, 'ast' => 1, 'stl' => 0, 'tov' => 2, 'blk' => 1, 'pf' => 3],
            ],
            'home_players'    => [
                ['name' => 'ASG H01', 'pos' => 'PG', 'pid' => 4150, 'min' => 18, 'twoGM' => 5, 'twoGA' => 8,  'ftm' => 0, 'fta' => 0, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 1, 'drb' => 3, 'ast' => 3,  'stl' => 2, 'tov' => 2, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG H02', 'pos' => 'PG', 'pid' => 3556, 'min' => 18, 'twoGM' => 7, 'twoGA' => 10, 'ftm' => 4, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 3, 'orb' => 1, 'drb' => 6, 'ast' => 5,  'stl' => 2, 'tov' => 1, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG H03', 'pos' => 'SG', 'pid' => 3552, 'min' => 18, 'twoGM' => 4, 'twoGA' => 8,  'ftm' => 0, 'fta' => 0, 'threeGM' => 2, 'threeGA' => 5, 'orb' => 0, 'drb' => 3, 'ast' => 4,  'stl' => 2, 'tov' => 0, 'blk' => 1, 'pf' => 3],
                ['name' => 'ASG H04', 'pos' => 'PF', 'pid' => 5261, 'min' => 22, 'twoGM' => 3, 'twoGA' => 6,  'ftm' => 7, 'fta' => 8, 'threeGM' => 1, 'threeGA' => 1, 'orb' => 1, 'drb' => 1, 'ast' => 2,  'stl' => 0, 'tov' => 0, 'blk' => 1, 'pf' => 1],
                ['name' => 'ASG H05', 'pos' => 'C',  'pid' => 3555, 'min' => 12, 'twoGM' => 3, 'twoGA' => 6,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 5, 'ast' => 0,  'stl' => 0, 'tov' => 1, 'blk' => 0, 'pf' => 0],
                ['name' => 'ASG H06', 'pos' => 'SG', 'pid' => 5259, 'min' => 27, 'twoGM' => 8, 'twoGA' => 19, 'ftm' => 1, 'fta' => 2, 'threeGM' => 1, 'threeGA' => 4, 'orb' => 7, 'drb' => 1, 'ast' => 8,  'stl' => 1, 'tov' => 1, 'blk' => 1, 'pf' => 2],
                ['name' => 'ASG H07', 'pos' => 'C',  'pid' => 4490, 'min' => 20, 'twoGM' => 5, 'twoGA' => 9,  'ftm' => 3, 'fta' => 3, 'threeGM' => 3, 'threeGA' => 5, 'orb' => 1, 'drb' => 4, 'ast' => 1,  'stl' => 1, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG H08', 'pos' => 'C',  'pid' => 4492, 'min' => 20, 'twoGM' => 6, 'twoGA' => 11, 'ftm' => 2, 'fta' => 2, 'threeGM' => 0, 'threeGA' => 0, 'orb' => 2, 'drb' => 7, 'ast' => 1,  'stl' => 0, 'tov' => 1, 'blk' => 1, 'pf' => 3],
                ['name' => 'ASG H09', 'pos' => 'SG', 'pid' => 4494, 'min' => 16, 'twoGM' => 6, 'twoGA' => 7,  'ftm' => 6, 'fta' => 6, 'threeGM' => 1, 'threeGA' => 2, 'orb' => 1, 'drb' => 2, 'ast' => 2,  'stl' => 3, 'tov' => 1, 'blk' => 0, 'pf' => 3],
                ['name' => 'ASG H10', 'pos' => 'PG', 'pid' => 4502, 'min' => 11, 'twoGM' => 2, 'twoGA' => 2,  'ftm' => 0, 'fta' => 0, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 0, 'drb' => 3, 'ast' => 3,  'stl' => 0, 'tov' => 0, 'blk' => 0, 'pf' => 6],
                ['name' => 'ASG H11', 'pos' => 'C',  'pid' => 4824, 'min' => 29, 'twoGM' => 8, 'twoGA' => 9,  'ftm' => 3, 'fta' => 4, 'threeGM' => 0, 'threeGA' => 1, 'orb' => 2, 'drb' => 6, 'ast' => 0,  'stl' => 3, 'tov' => 2, 'blk' => 1, 'pf' => 5],
                ['name' => 'ASG H12', 'pos' => 'PG', 'pid' => 4825, 'min' => 23, 'twoGM' => 3, 'twoGA' => 7,  'ftm' => 0, 'fta' => 0, 'threeGM' => 3, 'threeGA' => 6, 'orb' => 1, 'drb' => 4, 'ast' => 10, 'stl' => 2, 'tov' => 0, 'blk' => 2, 'pf' => 0],
            ],
        ];

        return ScoFileWriter::buildAllStarHeaderBlock($risingStars, $allStar);
    }

    /** Delete ASG rows for both game keys and insert sentinel player row. */
    private function setUpGameState(): void
    {
        $this->repo->deleteTeamBoxscoresByGame(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, 1);
        $this->repo->deletePlayerBoxscoresByGame(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID);
        $this->repo->deleteTeamBoxscoresByGame(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, 1);
        $this->repo->deletePlayerBoxscoresByGame(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);

        // Seed ibl_plr rows for every PID that will be inserted into ibl_box_scores.
        // The fk_boxscore_player FK requires these to exist before any boxscore insert.
        foreach ([
            // RSG visitor
            5936, 5938, 5931, 5930, 5937, 5939, 5929, 5935, 5942, 5964,
            // RSG home
            5640, 5649, 5642, 5659, 5645, 5685, 5646, 5663, 5641, 5644,
            // ASG visitor (5640 already seeded above)
            3852, 3851, 4148, 5258, 4500, 3282, 3561, 2975, 3277, 5265, 4507,
            // ASG home
            4150, 3556, 3552, 5261, 3555, 5259, 4490, 4492, 4494, 4502, 4824, 4825,
            // sentinel
            99999,
        ] as $pid) {
            $this->insertTestPlayer($pid, "Player {$pid}");
        }

        // Season::getLastBoxScoreDate() queries ibl_box_scores — insert sentinel there
        $this->insertPlayerBoxscoreRow(self::SENTINEL_DATE, 99999, 'Sentinel Player', 'PG', 1, 2, 1);
    }

    private function countPlayerRows(string $date, int $visitorTid, int $homeTid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_box_scores WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sii', $date, $visitorTid, $homeTid);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['cnt'] ?? 0);
    }

    private function countTeamRows(string $date, int $visitorTid, int $homeTid): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_box_scores_teams WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('sii', $date, $visitorTid, $homeTid);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Fetch a single team row by name (ibl_box_scores_teams has no teamid column).
     *
     * @return array<string, mixed>
     */
    private function fetchTeamRowByName(string $date, int $visitorTid, int $homeTid, string $name): array
    {
        $stmt = $this->db->prepare(
            'SELECT game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga,
                    game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf,
                    calc_points
             FROM ibl_box_scores_teams
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND name = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('siis', $date, $visitorTid, $homeTid, $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row, "Team row '{$name}' not found for {$date}");
        return $row;
    }

    /**
     * Sum player stats for a given teamid in ibl_box_scores.
     *
     * @return array<string, mixed>
     */
    private function fetchPlayerSumsForTeamId(string $date, int $visitorTid, int $homeTid, int $teamid): array
    {
        $stmt = $this->db->prepare(
            'SELECT SUM(game_2gm) AS game_2gm, SUM(game_2ga) AS game_2ga,
                    SUM(game_ftm) AS game_ftm, SUM(game_fta) AS game_fta,
                    SUM(game_3gm) AS game_3gm, SUM(game_3ga) AS game_3ga,
                    SUM(game_orb) AS game_orb, SUM(game_drb) AS game_drb,
                    SUM(game_ast) AS game_ast, SUM(game_stl) AS game_stl,
                    SUM(game_tov) AS game_tov, SUM(game_blk) AS game_blk,
                    SUM(game_pf) AS game_pf
             FROM ibl_box_scores
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND teamid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('siii', $date, $visitorTid, $homeTid, $teamid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);
        return $row;
    }

    // ── V11: Row counts ──────────────────────────────────────────────────────

    public function testRowCounts(): void
    {
        $this->setUpGameState();
        $result = $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        self::assertTrue($result['success']);
        self::assertArrayNotHasKey('skipped', $result);

        self::assertSame(20, $this->countPlayerRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID), 'RSG player rows (10+10)');
        self::assertSame(24, $this->countPlayerRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID), 'ASG player rows (12+12)');

        $totalPlayerRows = $this->countPlayerRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countPlayerRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);
        self::assertSame(44, $totalPlayerRows, 'Total player rows');

        $totalTeamRows = $this->countTeamRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countTeamRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);
        self::assertSame(4, $totalTeamRows, 'Total team rows');
    }

    // ── V12: Per-player cross-foot — spot-check known expected points ─────────

    public function testPerPlayerCrossfoot(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $rsgDate = self::RSG_DATE;
        $rsgVis  = self::RSG_VISITOR_TID;
        $rsgHome = self::RSG_HOME_TID;

        // pid 5663 (DNP): 0*2 + 0 + 0*3 = 0
        $this->assertPlayerCalcPoints($rsgDate, $rsgVis, $rsgHome, 5663, 0, 'DNP');

        // pid 5936 (RSG visitor, V1): 7*2 + 5 + 0*3 = 19
        $this->assertPlayerCalcPoints($rsgDate, $rsgVis, $rsgHome, 5936, 19, 'RSG V1');

        // pid 5640 (RSG home, H1): 13*2 + 0 + 0*3 = 26
        $this->assertPlayerCalcPoints($rsgDate, $rsgVis, $rsgHome, 5640, 26, 'RSG H1');

        $asgDate = self::ASG_DATE;
        $asgVis  = self::ASG_VISITOR_TID;
        $asgHome = self::ASG_HOME_TID;

        // pid 4500 (ASG V06): 7*2 + 7 + 2*3 = 14 + 7 + 6 = 27
        $this->assertPlayerCalcPoints($asgDate, $asgVis, $asgHome, 4500, 27, 'ASG V06');
    }

    private function assertPlayerCalcPoints(string $date, int $vTid, int $hTid, int $pid, int $expectedPts, string $label): void
    {
        $stmt = $this->db->prepare(
            'SELECT calc_points FROM ibl_box_scores WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND pid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('siii', $date, $vTid, $hTid, $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row, "No row found for pid {$pid} ({$label})");
        self::assertSame($expectedPts, (int) $row['calc_points'], "calc_points for {$label} (pid {$pid})");
    }

    // ── V13: Team-total calc_points = RSG 134/145, ASG 144/182 ───────────────

    public function testTeamTotalPoints(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $rsgVis = $this->fetchTeamRowByName(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, self::RSG_VISITOR_NAME);
        $rsgHome = $this->fetchTeamRowByName(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, self::RSG_HOME_NAME);
        $asgVis  = $this->fetchTeamRowByName(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, self::ASG_VISITOR_NAME);
        $asgHome = $this->fetchTeamRowByName(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, self::ASG_HOME_NAME);

        self::assertSame(134, (int) $rsgVis['calc_points'],  'RSG Rookies calc_points');
        self::assertSame(145, (int) $rsgHome['calc_points'], 'RSG Sophomores calc_points');
        self::assertSame(144, (int) $asgVis['calc_points'],  'ASG visitor calc_points');
        self::assertSame(182, (int) $asgHome['calc_points'], 'ASG home calc_points');
    }

    // ── V14: Player-sum == team-total (counting stats) ────────────────────────

    public function testPlayerSumEqualsTeamTotal(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $games = [
            [self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, self::RSG_VISITOR_NAME, self::RSG_HOME_NAME],
            [self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, self::ASG_VISITOR_NAME, self::ASG_HOME_NAME],
        ];

        foreach ($games as [$date, $vTid, $hTid, $vName, $hName]) {
            foreach ([[$vTid, $vName], [$hTid, $hName]] as [$teamid, $teamName]) {
                $teamRow    = $this->fetchTeamRowByName($date, $vTid, $hTid, $teamName);
                $playerSums = $this->fetchPlayerSumsForTeamId($date, $vTid, $hTid, $teamid);

                foreach (['game_2gm', 'game_2ga', 'game_ftm', 'game_fta', 'game_3gm', 'game_3ga', 'game_ast', 'game_stl', 'game_tov', 'game_blk', 'game_pf'] as $col) {
                    self::assertSame(
                        (int) $teamRow[$col],
                        (int) ($playerSums[$col] ?? 0),
                        "V14 {$date} {$teamName} {$col}: player sum != team total"
                    );
                }
            }
        }
    }

    // ── V15: Player-sum ORB/DRB ≤ team total ─────────────────────────────────

    public function testPlayerReboundSumsDoNotExceedTeamTotal(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $games = [
            [self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, self::RSG_VISITOR_NAME, self::RSG_HOME_NAME],
            [self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, self::ASG_VISITOR_NAME, self::ASG_HOME_NAME],
        ];

        foreach ($games as [$date, $vTid, $hTid, $vName, $hName]) {
            foreach ([[$vTid, $vName], [$hTid, $hName]] as [$teamid, $teamName]) {
                $teamRow    = $this->fetchTeamRowByName($date, $vTid, $hTid, $teamName);
                $playerSums = $this->fetchPlayerSumsForTeamId($date, $vTid, $hTid, $teamid);
                self::assertLessThanOrEqual((int) $teamRow['game_orb'], (int) ($playerSums['game_orb'] ?? 0), "V15 ORB {$date} {$teamName}");
                self::assertLessThanOrEqual((int) $teamRow['game_drb'], (int) ($playerSums['game_drb'] ?? 0), "V15 DRB {$date} {$teamName}");
            }
        }
    }

    // ── V16: game_type=1 and season_year=2007 ────────────────────────────────

    public function testGeneratedColumnsGameTypeAndSeasonYear(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_box_scores
             WHERE (game_date = ? AND visitor_teamid = ? AND home_teamid = ?)
                OR (game_date = ? AND visitor_teamid = ? AND home_teamid = ?)'
        );
        self::assertNotFalse($stmt);
        $rsgDate = self::RSG_DATE; $rsgVis = self::RSG_VISITOR_TID; $rsgHome = self::RSG_HOME_TID;
        $asgDate = self::ASG_DATE; $asgVis = self::ASG_VISITOR_TID; $asgHome = self::ASG_HOME_TID;
        $stmt->bind_param('siisii', $rsgDate, $rsgVis, $rsgHome, $asgDate, $asgVis, $asgHome);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $totalRows = (int) ($row['cnt'] ?? 0);
        self::assertSame(44, $totalRows, 'All 44 player rows present');

        // Query rows that DO NOT match game_type=1 and season_year=2007
        $stmt2 = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_box_scores
             WHERE ((game_date = ? AND visitor_teamid = ? AND home_teamid = ?)
                 OR (game_date = ? AND visitor_teamid = ? AND home_teamid = ?))
               AND NOT (game_type = 1 AND season_year = 2007)'
        );
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('siisii', $rsgDate, $rsgVis, $rsgHome, $asgDate, $asgVis, $asgHome);
        $stmt2->execute();
        /** @var array{cnt: int} $row2 */
        $row2 = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        self::assertSame(0, (int) ($row2['cnt'] ?? 0), 'All rows have game_type=1 and season_year=2007');
    }

    // ── V17: Exactly 1 DNP (pid 5663) ────────────────────────────────────────

    public function testExactlyOneDnpPlayer(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $stmt = $this->db->prepare(
            'SELECT pid, game_min, calc_points FROM ibl_box_scores
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_min = 0'
        );
        self::assertNotFalse($stmt);
        $rsgDate = self::RSG_DATE;
        $rsgVis  = self::RSG_VISITOR_TID;
        $rsgHome = self::RSG_HOME_TID;
        $stmt->bind_param('sii', $rsgDate, $rsgVis, $rsgHome);
        $stmt->execute();
        $dnpRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(1, $dnpRows, 'Exactly 1 DNP row in RSG');
        self::assertSame(5663, (int) $dnpRows[0]['pid'], 'DNP pid is 5663');
        self::assertSame(0, (int) $dnpRows[0]['game_min']);
        self::assertSame(0, (int) $dnpRows[0]['calc_points']);
    }

    // ── V18: Zero NULL pid/teamid ─────────────────────────────────────────────

    public function testNoPidOrTeamidNulls(): void
    {
        $this->setUpGameState();
        $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_box_scores
             WHERE ((game_date = ? AND visitor_teamid = ? AND home_teamid = ?)
                 OR (game_date = ? AND visitor_teamid = ? AND home_teamid = ?))
               AND (pid IS NULL OR teamid IS NULL)'
        );
        self::assertNotFalse($stmt);
        $rsgDate = self::RSG_DATE; $rsgVis = self::RSG_VISITOR_TID; $rsgHome = self::RSG_HOME_TID;
        $asgDate = self::ASG_DATE; $asgVis = self::ASG_VISITOR_TID; $asgHome = self::ASG_HOME_TID;
        $stmt->bind_param('siisii', $rsgDate, $rsgVis, $rsgHome, $asgDate, $asgVis, $asgHome);
        $stmt->execute();
        /** @var array{cnt: int} $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertSame(0, (int) ($row['cnt'] ?? 0), 'Zero NULL pid/teamid rows');
    }

    // ── V19: Season gate — no sentinel → skipped ─────────────────────────────

    public function testSeasonGateSkipsWithoutSentinel(): void
    {
        $this->repo->deleteTeamBoxscoresByGame(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID, 1);
        $this->repo->deletePlayerBoxscoresByGame(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID);
        $this->repo->deleteTeamBoxscoresByGame(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID, 1);
        $this->repo->deletePlayerBoxscoresByGame(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);
        // Do NOT insert sentinel — gate should fire

        $result = $this->processor->processAllStarGamesData($this->buildBlock(), 2007);

        self::assertTrue($result['success']);
        self::assertSame('All-Star Weekend not yet reached', $result['skipped'] ?? null);

        self::assertSame(0, $this->countPlayerRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countPlayerRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID));
    }

    // ── V20: Idempotency ──────────────────────────────────────────────────────

    public function testIdempotency(): void
    {
        $this->setUpGameState();
        $block = $this->buildBlock();

        $this->processor->processAllStarGamesData($block, 2007);
        $playerAfterFirst = $this->countPlayerRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countPlayerRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);
        $teamAfterFirst = $this->countTeamRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countTeamRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);

        $this->processor->processAllStarGamesData($block, 2007);
        $playerAfterSecond = $this->countPlayerRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countPlayerRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);
        $teamAfterSecond = $this->countTeamRows(self::RSG_DATE, self::RSG_VISITOR_TID, self::RSG_HOME_TID)
            + $this->countTeamRows(self::ASG_DATE, self::ASG_VISITOR_TID, self::ASG_HOME_TID);

        self::assertSame($playerAfterFirst, $playerAfterSecond, 'Player count stable (V20)');
        self::assertSame($teamAfterFirst, $teamAfterSecond, 'Team count stable (V20)');
    }
}
