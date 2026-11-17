<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\JsbExportRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests JsbExportRepository::getAllPlayerDepthCharts() against real MariaDB.
 */
#[Group('database')]
class JsbExportRepositoryDepthChartTest extends DatabaseTestCase
{
    private JsbExportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JsbExportRepository($this->db);
    }

    public function testReturnsDcColumnsKeyedByPid(): void
    {
        $this->insertTestPlayer(200000080, 'DB Test Depth Keyed', [
            'teamid' => 20,
            'ordinal' => 585,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 1,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 40,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 2,
            'dc_di' => 0,
            'dc_bh' => 0,
        ]);

        $result = $this->repo->getAllPlayerDepthCharts();

        self::assertArrayHasKey(200000080, $result);
        self::assertSame([
            'pid' => 200000080,
            'teamid' => 20,
            'dc_pg_depth' => 0,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 1,
            'dc_can_play_in_game' => 1,
            'dc_minutes' => 40,
            'dc_of' => 0,
            'dc_df' => 0,
            'dc_oi' => 2,
            'dc_di' => 0,
            'dc_bh' => 0,
        ], $result[200000080]);
    }

    public function testExcludesZeroPidRows(): void
    {
        $existing = $this->db->query('SELECT COUNT(*) AS n FROM ibl_plr WHERE pid = 0');
        self::assertInstanceOf(\mysqli_result::class, $existing);
        $row = $existing->fetch_assoc();
        if (!is_array($row) || (int) $row['n'] === 0) {
            $this->insertTestPlayer(0, 'DB Test Empty Slot Depth', [
                'teamid' => 20,
                'ordinal' => 586,
                'dc_minutes' => 30,
                'uuid' => 'test-000000000-0000-000000000098',
            ]);
        }
        $this->insertTestPlayer(200000081, 'DB Test Depth Real Player', ['teamid' => 20, 'ordinal' => 587]);

        $result = $this->repo->getAllPlayerDepthCharts();

        self::assertArrayNotHasKey(0, $result);
        self::assertArrayHasKey(200000081, $result);
    }
}
