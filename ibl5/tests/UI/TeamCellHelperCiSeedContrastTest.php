<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\TestCase;
use UI\TeamCellHelper;

final class TeamCellHelperCiSeedContrastTest extends TestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function seedPairs(): array
    {
        $sql = file_get_contents(__DIR__ . '/../e2e/fixtures/ci-seed.sql');
        self::assertIsString($sql);

        $start = strpos($sql, 'INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, uuid) VALUES');
        self::assertNotFalse($start, 'ibl_team_info INSERT not found in ci-seed.sql');

        $rest = substr($sql, $start);
        // The statement ends at the first ";" (the last row closes with ");").
        $end = strpos($rest, ';');
        $block = $end === false ? $rest : substr($rest, 0, $end);

        preg_match_all(
            '/^\s*\(\s*(\d+),\s*\'[^\']*\',\s*\'([^\']*)\',\s*\'([0-9A-Fa-f]{6})\',\s*\'([0-9A-Fa-f]{6})\',/m',
            $block,
            $matches,
            PREG_SET_ORDER,
        );

        $tuples = [];
        foreach ($matches as $row) {
            $tuples[] = [$row[1], $row[2], $row[3], $row[4]];
        }

        return $tuples;
    }

    public function testCiSeedParserFindsAllTwentyNineTeams(): void
    {
        // literal-check: 0-28 -> ibl_team_info.teamid int(11) (per ibl5/docs/schema/current-schema.sql)
        $pairs = $this->seedPairs();

        $this->assertCount(29, $pairs);

        $byId = [];
        foreach ($pairs as $tuple) {
            $byId[$tuple[0]] = $tuple;
        }
        $this->assertSame(['4', 'Diesels', '006BB6', 'ED174C'], $byId['4'] ?? null);
    }

    public function testEveryCiSeedTeamPairRendersAtLeastFourPointFiveToOne(): void
    {
        foreach ($this->seedPairs() as [$id, $name, $c1, $c2]) {
            $html = TeamCellHelper::renderTeamCell((int) $id, $name, $c1, $c2);

            $this->assertSame(
                1,
                preg_match('/--team-cell-bg: #([0-9a-fA-F]{6}); --team-cell-color: #([0-9a-fA-F]{6});/', $html, $m),
                "team $id $name: style not found",
            );
            $this->assertSame($c1, $m[1], "team $id $name: bg changed");
            $this->assertGreaterThanOrEqual(
                4.5,
                \Player\Views\TeamColorHelper::getContrastRatio($m[1], $m[2]),
                "team $id $name: $m[1]/$m[2]",
            );
        }
    }
}
