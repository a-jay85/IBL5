<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\TeamStatsTableContext;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use UI\TableStyles;

/**
 * @covers \BasketballStats\Tables\TeamStatsTableContext
 */
final class TeamStatsTableContextTest extends TestCase
{
    private function makeTeam(string $color1 = 'FF0000', string $color2 = '0000FF'): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = $color1;
        $team->color2 = $color2;
        $team->teamid = 7;
        $team->name = 'Test Team';

        return $team;
    }

    /**
     * @return array<string, string>
     */
    private static function bothStatsRow(int $games): array
    {
        $offense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
            'fgm' => '400', 'fga' => '850', 'ftm' => '200', 'fta' => '260',
            'tgm' => '120', 'tga' => '330', 'orb' => '95', 'reb' => '420',
            'ast' => '240', 'stl' => '70', 'tvr' => '130', 'blk' => '45', 'pf' => '190',
        ];
        $defense = [
            'teamID' => '7', 'name' => 'Test Team', 'games' => (string) $games,
            'fgm' => '380', 'fga' => '900', 'ftm' => '170', 'fta' => '230',
            'tgm' => '105', 'tga' => '310', 'orb' => '88', 'reb' => '400',
            'ast' => '215', 'stl' => '62', 'tvr' => '142', 'blk' => '39', 'pf' => '205',
        ];

        $row = [];
        foreach ($offense as $key => $value) {
            $row['tos_' . $key] = $value;
        }
        foreach ($defense as $key => $value) {
            $row['tds_' . $key] = $value;
        }

        return $row;
    }

    /**
     * A null row set registers no route, so that repository call returns no row.
     *
     * @param list<array<string, string>>|null $regular2023
     * @param list<array<string, string>>|null $fallback2023
     * @param list<array<string, string>>|null $regular2024
     */
    private function makeDb(?array $regular2023, ?array $fallback2023, ?array $regular2024): MockDatabase
    {
        $db = new MockDatabase();
        if ($regular2023 !== null) {
            $db->onQuery('bst\.game_type = 1 AND bst\.season_year = 2023', $regular2023);
        }
        if ($fallback2023 !== null) {
            $db->onQuery('^(?![\s\S]*game_type = 1)[\s\S]*bst\.season_year = 2023', $fallback2023);
        }
        if ($regular2024 !== null) {
            $db->onQuery('bst\.game_type = 1 AND bst\.season_year = 2024', $regular2024);
        }

        return $db;
    }

    private function makeFullDb(): MockDatabase
    {
        return $this->makeDb([self::bothStatsRow(10)], [self::bothStatsRow(11)], [self::bothStatsRow(12)]);
    }

    public function testOpenTagWithoutAriaLabel(): void
    {
        $this->assertSame(
            '<table class="ibl-data-table team-table responsive-table sortable" style="'
                . TableStyles::inlineTeamVars('FF0000', '0000FF') . '">',
            TeamStatsTableContext::openTag($this->makeTeam())
        );
    }

    public function testOpenTagWithAriaLabel(): void
    {
        $this->assertSame(
            '<table class="ibl-data-table team-table responsive-table sortable" aria-label="Season totals" style="'
                . TableStyles::inlineTeamVars('FF0000', '0000FF') . '">',
            TeamStatsTableContext::openTag($this->makeTeam(), 'Season totals')
        );
    }

    public function testOpenTagEscapesHostileAriaLabel(): void
    {
        $html = TeamStatsTableContext::openTag($this->makeTeam(), '"><script>alert(1)</script>');

        $this->assertStringContainsString('aria-label="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testOpenTagSanitizesHostileColors(): void
    {
        $html = TeamStatsTableContext::openTag($this->makeTeam('"><img src=x>', 'FF0000'));

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('"><', $html);
        $this->assertStringEndsWith('">', $html);
    }

    public function testOpenTagEmitsNoTrailingNewline(): void
    {
        $html = TeamStatsTableContext::openTag($this->makeTeam());

        $this->assertStringEndsWith('">', $html);
        $this->assertStringNotContainsString("\n", $html);
    }

    public function testResolveBothStatsUsesExplicitYear(): void
    {
        $result = TeamStatsTableContext::resolveBothStats($this->makeFullDb(), $this->makeTeam(), '2023');

        $this->assertNotNull($result);
        $this->assertSame(10, $result['offense']['games']);
    }

    public function testResolveBothStatsUsesSeasonEndingYearWhenYrEmpty(): void
    {
        $result = TeamStatsTableContext::resolveBothStats($this->makeFullDb(), $this->makeTeam(), '');

        $this->assertNotNull($result);
        $this->assertSame(12, $result['offense']['games']);
    }

    public function testResolveBothStatsPrefersRegularSeasonRows(): void
    {
        $result = TeamStatsTableContext::resolveBothStats($this->makeFullDb(), $this->makeTeam(), '2023');

        $this->assertNotNull($result);
        $this->assertSame(10, $result['offense']['games']);
        $this->assertNotSame(11, $result['offense']['games']);
    }

    public function testResolveBothStatsFallsBackToAllGames(): void
    {
        $db = $this->makeDb(null, [self::bothStatsRow(11)], null);

        $result = TeamStatsTableContext::resolveBothStats($db, $this->makeTeam(), '2023');

        $this->assertNotNull($result);
        $this->assertSame(11, $result['offense']['games']);
    }

    public function testResolveBothStatsReturnsNullWhenNoRows(): void
    {
        $this->assertNull(TeamStatsTableContext::resolveBothStats(new MockDatabase(), $this->makeTeam(), '2023'));
    }

    public function testIsStatelessAndCarriesNoActorIdentity(): void
    {
        $rc = new \ReflectionClass(TeamStatsTableContext::class);

        $this->assertSame([], $rc->getProperties());
        $this->assertNull($rc->getConstructor());
        $this->assertTrue($rc->isFinal());

        $names = [];
        foreach ($rc->getMethods() as $method) {
            $this->assertTrue($method->isStatic());
            $this->assertTrue($method->isPublic());
            foreach ($method->getParameters() as $param) {
                $names[] = $param->getName();
            }
        }

        $sortedNames = array_values(array_unique($names));
        sort($sortedNames);
        $this->assertSame(['ariaLabel', 'db', 'team', 'yr'], $sortedNames);
        $this->assertDoesNotMatchRegularExpression('/session|loggedin|user|actor|request/i', implode(',', $names));
    }
}
