<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\TeamStatsRowHelper;
use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStats;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \BasketballStats\Tables\TeamStatsRowHelper
 */
final class TeamStatsRowHelperTest extends TestCase
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
     * Every key PlayerStats::fill() reads from an ibl_plr row. Values are strings, as mysqli returns them.
     *
     * @param array<string, string|int> $overrides
     * @return array<string, string|int>
     */
    private static function plrStatsRow(int $pid, array $overrides = []): array
    {
        $zeroKeys = [
            'c_dd', 'c_td', 'car_3ga', 'car_3gm', 'car_ast', 'car_blk', 'car_drb', 'car_fga', 'car_fgm',
            'car_fta', 'car_ftm', 'car_gm', 'car_min', 'car_orb', 'car_pf', 'car_reb', 'car_stl', 'car_tvr',
            'ch_ast', 'ch_blk', 'ch_pts', 'ch_reb', 'ch_stl', 'cp_ast', 'cp_blk', 'cp_pts', 'cp_reb', 'cp_stl',
            's_dd', 's_td', 'sh_ast', 'sh_blk', 'sh_pts', 'sh_reb', 'sh_stl', 'sp_ast', 'sp_blk', 'sp_pts',
            'sp_reb', 'sp_stl',
        ];
        $row = array_fill_keys($zeroKeys, '0');
        $row += [
            'pid' => (string) $pid, 'name' => 'Row ' . $pid, 'pos' => 'G', 'retired' => '0',
            'stats_gm' => '20', 'stats_gs' => '15', 'stats_min' => '640',
            'stats_fgm' => '140', 'stats_fga' => '300', 'stats_ftm' => '60', 'stats_fta' => '75',
            'stats_3gm' => '30', 'stats_3ga' => '80', 'stats_orb' => '25', 'stats_drb' => '95',
            'stats_ast' => '90', 'stats_stl' => '22', 'stats_tvr' => '40', 'stats_blk' => '12', 'stats_pf' => '48',
        ];

        return array_merge($row, $overrides);
    }

    private function makePlayer(
        int $pid,
        string $name,
        string $pos,
        int $teamid,
        string $teamName,
        ?string $color1,
        ?string $color2,
        string $statusClass = ''
    ): \Player\Player {
        $player = self::createStub(\Player\Player::class);
        $player->method('getPlayerID')->willReturn($pid);
        $player->method('getName')->willReturn($name);
        $player->method('getDecoratedName')->willReturn($name);
        $player->method('getNameStatusClass')->willReturn($statusClass);
        $player->method('getPosition')->willReturn($pos);
        $player->method('getTeamid')->willReturn($teamid);
        $player->method('getTeamName')->willReturn($teamName);
        $player->method('getTeamColor1')->willReturn($color1);
        $player->method('getTeamColor2')->willReturn($color2);

        return $player;
    }

    private function makeStats(int $pid): PlayerStats
    {
        // withPlrRow() bypasses the query layer, so pid and retired must already be int.
        $stats = PlayerStats::withPlrRow(new MockDatabase(), self::plrStatsRow($pid, ['pid' => $pid, 'retired' => 0]));
        $this->assertInstanceOf(PlayerStats::class, $stats);

        return $stats;
    }

    public function testIsStatelessCarriesNoActorIdentityAndNoDbHandle(): void
    {
        $rc = new \ReflectionClass(TeamStatsRowHelper::class);

        $this->assertSame([], $rc->getProperties());
        $this->assertNull($rc->getConstructor());
        $this->assertTrue($rc->isFinal());

        $names = [];
        foreach ($rc->getMethods() as $method) {
            $this->assertTrue($method->isStatic());
            $this->assertTrue($method->isPublic());
            foreach ($method->getParameters() as $param) {
                $names[] = $param->getName();
                $type = $param->getType();
                $this->assertNotSame('mysqli', $type instanceof \ReflectionNamedType ? $type->getName() : '');
            }
        }

        $sortedNames = array_values(array_unique($names));
        sort($sortedNames);
        $this->assertSame(['games', 'label', 'moduleName', 'player', 'playerStats', 'starterPids', 'team'], $sortedNames);
        $this->assertDoesNotMatchRegularExpression('/session|loggedin|user|actor|request|db/i', implode(',', $names));
    }

    public function testHeadPrefixAddsTeamColumnOnlyForLeagueStarters(): void
    {
        $plain = TeamStatsRowHelper::headPrefix('');
        $this->assertSame("            <th>Pos</th>\n            <th class=\"sticky-col\">Player</th>\n            <th>g</th>\n            <th>gs</th>\n", $plain);
        $this->assertSame("            <th>Team</th>\n" . $plain, TeamStatsRowHelper::headPrefix('LeagueStarters'));
        $this->assertSame($plain, TeamStatsRowHelper::headPrefix('leaguestarters'));
    }

    public function testPlayerRowPrefixEscapesPositionAtItsCell(): void
    {
        $player = $this->makePlayer(11, 'Alpha', '<b>PG</b>', 7, 'Test Team', 'FF0000', '0000FF');
        $html = TeamStatsRowHelper::playerRowPrefix($player, $this->makeStats(11), [11], '');

        $this->assertStringStartsWith("        <tr>\n            <td>&lt;b&gt;PG&lt;/b&gt;</td>\n            <td class=\"sticky-col", $html);
        $this->assertStringNotContainsString('<b>PG</b>', $html);
        $this->assertStringEndsWith("            <td>20</td>\n            <td>15</td>\n", $html);
        $this->assertStringContainsString('</a></td>            <td>20</td>', $html);
        $this->assertStringContainsString(' is-starter', $html);
    }

    public function testPlayerRowPrefixEmitsTeamCellOnlyForLeagueStarters(): void
    {
        $freeAgent = $this->makePlayer(12, 'Bravo', 'C', 0, '', null, null);
        $stats = $this->makeStats(12);

        $this->assertStringContainsString("        <tr>\n<td", TeamStatsRowHelper::playerRowPrefix($freeAgent, $stats, [], 'LeagueStarters'));
        $this->assertStringContainsString('Free Agent', TeamStatsRowHelper::playerRowPrefix($freeAgent, $stats, [], 'LeagueStarters'));
        $this->assertStringStartsWith("        <tr>\n            <td>C</td>", TeamStatsRowHelper::playerRowPrefix($freeAgent, $stats, [], ''));
        $this->assertStringNotContainsString(' is-starter', TeamStatsRowHelper::playerRowPrefix($freeAgent, $stats, [11], ''));
    }

    public function testFooterLabelCellsEscapeTeamNameAndPickColspan(): void
    {
        $team = $this->makeTeam();
        $team->name = "O'Brien <b>Hack</b>";

        $plain = TeamStatsRowHelper::footerLabelCells($team, 'Offense', 10, '');
        $this->assertStringStartsWith("        <tr>\n            <td colspan=\"2\">", $plain);
        $this->assertStringContainsString("&lt;b&gt;Hack&lt;/b&gt; Offense</td>\n            <td>10</td>\n            <td>10</td>\n            <td class=\"sep-r-team\"></td>\n", $plain);
        $this->assertStringNotContainsString('<b>Hack</b>', $plain);
        $this->assertStringStartsWith("        <tr>\n            <td colspan=\"3\">", TeamStatsRowHelper::footerLabelCells($team, 'Defense', 0, 'LeagueStarters'));
        $this->assertStringContainsString(' Defense</td>', TeamStatsRowHelper::footerLabelCells($team, 'Defense', 0, 'LeagueStarters'));
    }
}
