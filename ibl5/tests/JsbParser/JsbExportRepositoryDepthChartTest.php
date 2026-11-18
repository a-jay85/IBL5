<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\JsbExportRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Unit tests for JsbExportRepository::getAllPlayerDepthCharts() over canned rows.
 *
 * The DB integration test in tests/DatabaseIntegration carries the `database`
 * group, which the per-PR Infection run never executes. These rows also pin the
 * value coercion: native ints pass through, numeric strings (COALESCE over a
 * tinyint unsigned column) become ints, and anything else becomes 0.
 */
class JsbExportRepositoryDepthChartTest extends WideUnitTestCase
{
    private JsbExportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JsbExportRepository($this->mockDb);
    }

    public function testReturnsEveryRowKeyedByPidWithCoercedValues(): void
    {
        $this->mockDb->setMockData([
            [
                'pid' => 101,
                'teamid' => 5,
                'dc_pg_depth' => '1',
                'dc_sg_depth' => '0',
                'dc_sf_depth' => 0,
                'dc_pf_depth' => 2,
                'dc_c_depth' => '3',
                'dc_can_play_in_game' => 1,
                'dc_minutes' => '36',
                'dc_of' => 0,
                'dc_df' => 1,
                'dc_oi' => '2',
                'dc_di' => 0,
                'dc_bh' => '1',
            ],
            [
                'pid' => '202',
                'teamid' => '7',
                'dc_pg_depth' => null,
                'dc_sg_depth' => '5abc',
                'dc_sf_depth' => 'abc',
                'dc_pf_depth' => 4.0,
                'dc_c_depth' => 0,
                'dc_can_play_in_game' => '0',
                'dc_minutes' => 12,
                'dc_of' => '2',
                'dc_df' => 0,
                'dc_oi' => 0,
                'dc_di' => '1',
                'dc_bh' => 0,
            ],
        ]);

        $result = $this->repo->getAllPlayerDepthCharts();

        self::assertSame([
            101 => [
                'pid' => 101,
                'teamid' => 5,
                'dc_pg_depth' => 1,
                'dc_sg_depth' => 0,
                'dc_sf_depth' => 0,
                'dc_pf_depth' => 2,
                'dc_c_depth' => 3,
                'dc_can_play_in_game' => 1,
                'dc_minutes' => 36,
                'dc_of' => 0,
                'dc_df' => 1,
                'dc_oi' => 2,
                'dc_di' => 0,
                'dc_bh' => 1,
            ],
            202 => [
                'pid' => 202,
                'teamid' => 7,
                'dc_pg_depth' => 0,
                'dc_sg_depth' => 0,
                'dc_sf_depth' => 0,
                'dc_pf_depth' => 0,
                'dc_c_depth' => 0,
                'dc_can_play_in_game' => 0,
                'dc_minutes' => 12,
                'dc_of' => 2,
                'dc_df' => 0,
                'dc_oi' => 0,
                'dc_di' => 1,
                'dc_bh' => 0,
            ],
        ], $result);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testReturnsEmptyArrayWhenNoRows(): void
    {
        $this->mockDb->setMockData([]);

        self::assertSame([], $this->repo->getAllPlayerDepthCharts());
    }
}
