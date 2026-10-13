<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PlrParser\PlrParserRepository;

/**
 * Round-trips every ibl_plr_snapshots column the PLR parser writes through
 * PlrParserRepository::upsertSnapshot() against real MariaDB, so a dropped, swapped or
 * mis-bound column in the generated upsert statement fails here.
 */
#[Group('database')]
class PlrParserSnapshotUpsertTest extends DatabaseTestCase
{
    private const PID = 200140001;
    private const SEASON_YEAR = 2097;
    private const PHASE = 'mid-season';

    /**
     * Test-owned copy of the ibl_plr_snapshots write set, in insertion order. Kept apart from
     * the repository on purpose: a column added or dropped there must be reflected here.
     */
    private const SNAPSHOT_COLUMNS = [
        'pid', 'name', 'season_year', 'snapshot_phase', 'source_archive', 'ordinal',
        'teamid', 'age', 'pos', 'peak', 'htft', 'htin',
        'wt', 'oo', 'od', 'r_drive_off', 'dd', 'po',
        'pd', 'r_trans_off', 'td', 'r_fga', 'r_fgp', 'r_fta',
        'r_ftp', 'r_3ga', 'r_3gp', 'r_orb', 'r_drb', 'r_ast',
        'r_stl', 'r_tvr', 'r_blk', 'r_foul', 'talent', 'skill',
        'intangibles', 'clutch', 'consistency', 'exp', 'bird', 'cy',
        'cyt', 'salary_yr1', 'salary_yr2', 'salary_yr3', 'salary_yr4', 'salary_yr5',
        'salary_yr6', 'pg_depth', 'sg_depth', 'sf_depth', 'pf_depth', 'c_depth',
        'stats_gs', 'stats_gm', 'stats_min', 'stats_fgm', 'stats_fga', 'stats_ftm',
        'stats_fta', 'stats_3gm', 'stats_3ga', 'stats_orb', 'stats_drb', 'stats_ast',
        'stats_stl', 'stats_tvr', 'stats_blk', 'stats_pf', 'stats_reb', 'stats_pts',
        'po_stats_gm', 'po_stats_min', 'po_stats_2gm', 'po_stats_2ga', 'po_stats_ftm', 'po_stats_fta',
        'po_stats_3gm', 'po_stats_3ga', 'po_stats_orb', 'po_stats_drb', 'po_stats_ast', 'po_stats_stl',
        'po_stats_tvr', 'po_stats_blk', 'po_stats_pf', 'car_gm', 'car_min', 'car_fgm',
        'car_fga', 'car_ftm', 'car_fta', 'car_3gm', 'car_3ga', 'car_orb',
        'car_drb', 'car_reb', 'car_ast', 'car_stl', 'car_tvr', 'car_blk',
        'car_pf', 'car_pts', 'sh_pts', 'sh_reb', 'sh_ast', 'sh_stl',
        'sh_blk', 's_dd', 's_td', 'sp_pts', 'sp_reb', 'sp_ast',
        'sp_stl', 'sp_blk', 'ch_pts', 'ch_reb', 'ch_ast', 'ch_stl',
        'ch_blk', 'c_dd', 'c_td', 'cp_pts', 'cp_reb', 'cp_ast',
        'cp_stl', 'cp_blk', 'rl_gp', 'rl_min', 'rl_fgm', 'rl_fga',
        'rl_ftm', 'rl_fta', 'rl_3gm', 'rl_3ga', 'rl_orb', 'rl_drb',
        'rl_ast', 'rl_stl', 'rl_tvr', 'rl_blk', 'rl_pf', 'coach',
        'loyalty', 'playing_time', 'winner', 'tradition', 'security', 'draftround',
        'draftpickno', 'fa_signing_flag', 'dc_can_play_in_game', 'injured', 'draftyear', 'salary',
        'unk_112', 'unk_114', 'unk_116', 'unk_118', 'unk_120', 'unk_122',
        'unk_124', 'unk_126', 'unk_138', 'unk_294', 'unk_296', 'unk_322',
        'unk_324', 'unk_331', 'unk_333', 'unk_335', 'unk_337', 'unk_339',
        'unk_512', 'unk_514', 'unk_516', 'unk_518', 'unk_520', 'unk_522',
        'unk_524', 'unk_526', 'unk_528', 'unk_530', 'unk_532', 'unk_534',
        'unk_536', 'unk_538', 'unk_540', 'unk_542', 'unk_544', 'unk_546',
        'unk_548',
    ];

    private PlrParserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlrParserRepository($this->db);
    }

    public function testUpsertSnapshotRoundTripsEveryColumn(): void
    {
        $first = $this->snapshotRow(0, 'Snapshot Player', 'ibl5-2097.zip', 'PG');

        $this->repo->upsertSnapshot($first);

        $stored = $this->fetchSnapshot();
        $storedId = $stored['id'];
        foreach (self::SNAPSHOT_COLUMNS as $column) {
            self::assertSame($first[$column], $stored[$column], 'Column ' . $column . ' must round-trip');
        }

        // Re-upserting the same (pid, season_year, snapshot_phase) updates every non-key column.
        $second = $this->snapshotRow(1, 'Changed Player', 'ibl5-2097-b.zip', 'C');

        $this->repo->upsertSnapshot($second);

        $updated = $this->fetchSnapshot();
        self::assertSame($storedId, $updated['id'], 'The upsert must hit the existing row, not insert a second');
        foreach (self::SNAPSHOT_COLUMNS as $column) {
            self::assertSame($second[$column], $updated[$column], 'Column ' . $column . ' must update on duplicate key');
        }
        self::assertSame(1, $this->countSnapshotRows());
    }

    /**
     * One distinct small value per column (fits the narrowest column, a signed tinyint);
     * $shift moves every non-key numeric value so the second upsert changes all of them.
     *
     * @return array<string, int|string>
     */
    private function snapshotRow(int $shift, string $name, string $archive, string $pos): array
    {
        $row = [];
        foreach (self::SNAPSHOT_COLUMNS as $index => $column) {
            $row[$column] = match ($column) {
                'pid' => self::PID,
                'season_year' => self::SEASON_YEAR,
                'snapshot_phase' => self::PHASE,
                'name' => $name,
                'source_archive' => $archive,
                'pos' => $pos,
                default => ($index % 120) + 1 + $shift,
            };
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSnapshot(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM `ibl_plr_snapshots` WHERE pid = ? AND season_year = ? AND snapshot_phase = ?');
        self::assertNotFalse($stmt);
        $pid = self::PID;
        $year = self::SEASON_YEAR;
        $phase = self::PHASE;
        $stmt->bind_param('iis', $pid, $year, $phase);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);

        return $row;
    }

    private function countSnapshotRows(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS n FROM `ibl_plr_snapshots` WHERE pid = ?');
        self::assertNotFalse($stmt);
        $pid = self::PID;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertIsArray($row);
        self::assertIsScalar($row['n']);

        return (int) $row['n'];
    }
}
