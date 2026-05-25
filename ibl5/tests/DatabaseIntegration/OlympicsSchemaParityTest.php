<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class OlympicsSchemaParityTest extends DatabaseTestCase
{
    /** @var array<string, string> */
    private const EXCLUDED_TABLE_PAIRS = [
        'ibl_league_config' => 'ibl_olympics_league_config',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_OLYMPICS_ONLY_COLUMNS = [
        'ibl_olympics_schedule'  => ['round'],
        'ibl_olympics_standings' => ['group_name', 'medal'],
        'ibl_olympics_hist'      => ['nuke_iblhist', 'created_at', 'updated_at'],
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_MISSING_FROM_OLYMPICS = [
        'ibl_olympics_team_info' => [
            'gm_username',
            'used_extension_this_chunk',
            'used_extension_this_season',
            'has_mle',
            'has_lle',
            'depth',
            'sim_depth',
            'asg_vote',
            'eoy_vote',
        ],
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_MISSING_INDEXES = [
        'ibl_olympics_box_scores' => ['idx_uuid'],
        'ibl_olympics_team_info'  => ['idx_gm_username'],
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_INDEX_DEFINITION_MISMATCHES = [
        'ibl_olympics_hist' => ['PRIMARY'],
    ];

    private const TABLE_PAIRS = [
        'ibl_box_scores' => 'ibl_olympics_box_scores',
        'ibl_box_scores_teams' => 'ibl_olympics_box_scores_teams',
        'ibl_schedule' => 'ibl_olympics_schedule',
        'ibl_standings' => 'ibl_olympics_standings',
        'ibl_power' => 'ibl_olympics_power',
        'ibl_team_info' => 'ibl_olympics_team_info',
        'ibl_plr' => 'ibl_olympics_plr',
        'ibl_hist' => 'ibl_olympics_hist',
        'ibl_plr_snapshots' => 'ibl_olympics_plr_snapshots',
        'ibl_jsb_history' => 'ibl_olympics_jsb_history',
        'ibl_jsb_transactions' => 'ibl_olympics_jsb_transactions',
        'ibl_rcb_alltime_records' => 'ibl_olympics_rcb_alltime_records',
        'ibl_rcb_season_records' => 'ibl_olympics_rcb_season_records',
        'ibl_saved_depth_charts' => 'ibl_olympics_saved_depth_charts',
        'ibl_saved_depth_chart_players' => 'ibl_olympics_saved_depth_chart_players',
    ];

    public function testAllOlympicsTablesExist(): void
    {
        $allOlympicsTables = array_merge(
            array_values(self::TABLE_PAIRS),
            array_values(self::EXCLUDED_TABLE_PAIRS),
        );

        $placeholders = implode(',', array_fill(0, count($allOlympicsTables), '?'));
        $types = str_repeat('s', count($allOlympicsTables));

        $stmt = $this->db->prepare(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param($types, ...$allOlympicsTables);
        $stmt->execute();
        $result = $stmt->get_result();

        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $existing[] = $row['TABLE_NAME'];
        }
        $stmt->close();

        $missing = array_diff($allOlympicsTables, $existing);
        self::assertEmpty($missing, 'Missing Olympics tables: ' . implode(', ', $missing));
    }

    #[DataProvider('tablePairsProvider')]
    public function testColumnParityAcrossTablePairs(string $iblTable, string $olympicsTable): void
    {
        $iblColumns = $this->getColumnNames($iblTable);
        $olympicsColumns = $this->getColumnNames($olympicsTable);

        $missingFromOlympics = array_diff($iblColumns, $olympicsColumns);
        $allowedMissing = self::ALLOWED_MISSING_FROM_OLYMPICS[$olympicsTable] ?? [];
        $missingFromOlympics = array_diff($missingFromOlympics, $allowedMissing);

        $extraInOlympics = array_diff($olympicsColumns, $iblColumns);
        $allowedExtra = self::ALLOWED_OLYMPICS_ONLY_COLUMNS[$olympicsTable] ?? [];
        $extraInOlympics = array_diff($extraInOlympics, $allowedExtra);

        $drift = [];
        if (count($missingFromOlympics) > 0) {
            $drift[] = "Columns missing from $olympicsTable: " . implode(', ', $missingFromOlympics);
        }
        if (count($extraInOlympics) > 0) {
            $drift[] = "Unexpected columns in $olympicsTable: " . implode(', ', $extraInOlympics);
        }

        self::assertEmpty($drift, implode("\n", $drift));
    }

    #[DataProvider('tablePairsProvider')]
    public function testIndexParityAcrossTablePairs(string $iblTable, string $olympicsTable): void
    {
        $iblIndexes = $this->getIndexes($iblTable);
        $olympicsIndexes = $this->getIndexes($olympicsTable);

        $iblNames = array_keys($iblIndexes);
        $olympicsNames = array_keys($olympicsIndexes);

        $missingFromOlympics = array_diff($iblNames, $olympicsNames);
        $allowedMissing = self::ALLOWED_MISSING_INDEXES[$olympicsTable] ?? [];
        $missingFromOlympics = array_diff($missingFromOlympics, $allowedMissing);

        $allowedDefMismatch = self::ALLOWED_INDEX_DEFINITION_MISMATCHES[$olympicsTable] ?? [];

        $drift = [];
        if (count($missingFromOlympics) > 0) {
            $drift[] = "Indexes missing from $olympicsTable: " . implode(', ', $missingFromOlympics);
        }

        $shared = array_intersect($iblNames, $olympicsNames);
        foreach ($shared as $idx) {
            if ($iblIndexes[$idx] !== $olympicsIndexes[$idx] && !in_array($idx, $allowedDefMismatch, true)) {
                $drift[] = "Index $idx definition mismatch: IBL={$iblIndexes[$idx]}, Olympics={$olympicsIndexes[$idx]}";
            }
        }

        self::assertEmpty($drift, implode("\n", $drift));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function tablePairsProvider(): array
    {
        $pairs = [];
        foreach (self::TABLE_PAIRS as $ibl => $olympics) {
            $pairs[$ibl] = [$ibl, $olympics];
        }
        return $pairs;
    }

    /**
     * @return list<string> column names (case-sensitive, via BINARY)
     */
    private function getColumnNames(string $table): array
    {
        $stmt = $this->db->prepare(
            "SELECT BINARY COLUMN_NAME AS col_name
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['col_name'];
        }
        $stmt->close();

        return $columns;
    }

    /**
     * @return array<string, string> index_name => "columns|unique"
     */
    private function getIndexes(string $table): array
    {
        $stmt = $this->db->prepare(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols, NON_UNIQUE
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE
             ORDER BY INDEX_NAME"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();

        $indexes = [];
        while ($row = $result->fetch_assoc()) {
            $indexes[$row['INDEX_NAME']] = $row['cols'] . '|' . $row['NON_UNIQUE'];
        }
        $stmt->close();

        return $indexes;
    }
}
