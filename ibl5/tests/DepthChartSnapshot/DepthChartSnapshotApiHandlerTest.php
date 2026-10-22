<?php

declare(strict_types=1);

namespace Tests\DepthChartSnapshot;

use DepthChartSnapshot\DepthChartSnapshotApiHandler;
use Tests\WideUnit\WideUnitTestCase;

/**
 * @covers \DepthChartSnapshot\DepthChartSnapshotApiHandler
 */
class DepthChartSnapshotApiHandlerTest extends WideUnitTestCase
{
    private DepthChartSnapshotApiHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new DepthChartSnapshotApiHandler($this->mockDb);
    }

    public function testHandleUnknownActionReturnsError(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('unknown', 1, 'testuser', []));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Unknown action', $data['error']);
    }

    public function testHandleRenameRejectsInvalidId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 0, 'name' => 'Test']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsNegativeId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => -5, 'name' => 'Test']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsEmptyName(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 1, 'name' => '']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleRenameSuccessReturnsJson(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => 'My DC']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame('My DC', $data['name']);
    }

    public function testHandleRenameTruncatesLongName(): void
    {
        $longName = str_repeat('a', 150);

        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => $longName]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame(100, mb_strlen($data['name']));
    }

    public function testHandleRenameStripsHtmlTags(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => '<b>Bold DC</b>']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Bold DC', $data['name']);
    }

    public function testHandleRenameActiveRejectsEmptyName(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename-active', 1, 'testuser', ['name' => '']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleLoadRejectsInvalidId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('load', 1, 'testuser', ['id' => 0]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleLoadRejectsNegativeId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('load', 1, 'testuser', ['id' => -5]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dcRow(int $dcId, int $teamid, ?string $simEndDate): array
    {
        return [
            'id' => $dcId, 'teamid' => $teamid, 'username' => 'testuser', 'name' => 'Pinned DC',
            'phase' => 'Regular Season', 'season_year' => 2024,
            'sim_start_date' => '2024-01-01', 'sim_end_date' => $simEndDate,
            'sim_number_start' => 1, 'sim_number_end' => null,
            'is_active' => 1, 'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];
    }

    private function registerLoadFixture(int $dcId, int $teamid, ?string $simEndDate): void
    {
        $this->mockDb->onQuery(
            'SELECT \* FROM `?ibl_saved_depth_charts`? WHERE id = ' . $dcId . ' AND teamid = ' . $teamid . ' LIMIT 1',
            [$this->dcRow($dcId, $teamid, $simEndDate)]
        );

        $playerBase = [
            'dc_pg_depth' => 1, 'dc_sg_depth' => 2, 'dc_sf_depth' => 3, 'dc_pf_depth' => 4, 'dc_c_depth' => 5,
            'dc_can_play_in_game' => 1, 'dc_minutes' => 30,
            'dc_of' => 1, 'dc_df' => 2, 'dc_oi' => 3, 'dc_di' => 4, 'dc_bh' => 5,
        ];
        $this->mockDb->onQuery(
            'SELECT \* FROM `?ibl_saved_depth_chart_players`? WHERE depth_chart_id = ' . $dcId,
            [
                ['pid' => 10, 'player_name' => 'Keeper A'] + $playerBase,
                ['pid' => 11, 'player_name' => 'Keeper B'] + $playerBase,
                ['pid' => 99, 'player_name' => 'Traded Guy'] + $playerBase,
            ]
        );

        $this->mockDb->onQuery(
            'SELECT \* FROM `?ibl_plr`? WHERE teamid = ' . $teamid . ' AND retired = 0',
            [
                ['pid' => 10, 'name' => 'Keeper A', 'pos' => 'PG'],
                ['pid' => 11, 'name' => 'Keeper B', 'pos' => 'SG'],
                ['pid' => 12, 'name' => 'New Guy', 'pos' => 'C'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function loadJson(int $teamid, array $params): array
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('load', $teamid, 'testuser', $params));
        $data = json_decode($output, true);
        $this->assertIsArray($data);
        return $data;
    }

    public function testHandleLoadReturnsPayloadShapeForOwnTeam(): void
    {
        $this->registerLoadFixture(7, 1, null);

        $data = $this->loadJson(1, ['id' => 7]);

        $this->assertSame(['depthChart', 'players', 'newPlayers', 'statsHtml'], array_keys($data));
        $this->assertSame([
            'id' => 7, 'name' => 'Pinned DC', 'simStartDate' => '2024-01-01', 'simEndDate' => null,
            'simNumberStart' => 1, 'simNumberEnd' => null, 'isActive' => true,
        ], $data['depthChart']);
        $this->assertSame([
            'pid' => 10, 'playerName' => 'Keeper A',
            'dc_PGDepth' => 1, 'dc_SGDepth' => 2, 'dc_SFDepth' => 3, 'dc_PFDepth' => 4, 'dc_CDepth' => 5,
            'dc_canPlayInGame' => 1, 'dc_minutes' => 30,
            'dc_of' => 1, 'dc_df' => 2, 'dc_oi' => 3, 'dc_di' => 4, 'dc_bh' => 5,
            'isOnCurrentRoster' => true,
        ], $data['players'][0]);
    }

    public function testHandleLoadMarksTradedPlayerNotOnCurrentRoster(): void
    {
        $this->registerLoadFixture(7, 1, null);

        $data = $this->loadJson(1, ['id' => 7]);

        $byPid = [];
        foreach ($data['players'] as $row) {
            $byPid[$row['pid']] = $row['isOnCurrentRoster'];
        }
        $this->assertSame([10 => true, 11 => true, 99 => false], $byPid);
    }

    public function testHandleLoadListsNewRosterPlayersNotInSavedChart(): void
    {
        $this->registerLoadFixture(7, 1, null);

        $data = $this->loadJson(1, ['id' => 7]);

        $this->assertSame([['pid' => 12, 'playerName' => 'New Guy', 'pos' => 'C']], $data['newPlayers']);
    }

    public function testHandleLoadReturnsEmptyStatsHtmlWhenSimEndDateNull(): void
    {
        $this->registerLoadFixture(7, 1, null);

        $data = $this->loadJson(1, ['id' => 7]);

        $this->assertSame('', $data['statsHtml']);
        $this->assertSame(0, $this->countQueriesMatching('ibl_team_info'));
    }

    public function testHandleLoadReturnsEmptyStatsHtmlWhenSimEndDateEmptyString(): void
    {
        $this->registerLoadFixture(7, 1, '');

        $data = $this->loadJson(1, ['id' => 7]);

        $this->assertSame('', $data['statsHtml']);
        $this->assertSame(0, $this->countQueriesMatching('ibl_team_info'));
    }

    public function testHandleLoadReturns404ForUnknownId(): void
    {
        $this->registerLoadFixture(8, 1, null);

        $data = $this->loadJson(1, ['id' => 7]);

        $this->assertSame(['error' => 'Depth chart not found'], $data);
    }

    public function testHandleLoadReturns404ForAnotherTeamsSnapshot(): void
    {
        // The request's params.teamid (2) must be ignored; only handle()'s teamid (1) scopes the lookup.
        $this->registerLoadFixture(7, 2, null);

        $data = $this->loadJson(1, ['id' => 7, 'teamid' => 2]);

        $this->assertSame(['error' => 'Depth chart not found'], $data);
    }

    public function testHandleLoadBindsHandleTeamidNotParamsTeamid(): void
    {
        $this->registerLoadFixture(7, 2, null);

        $this->loadJson(1, ['id' => 7, 'teamid' => 2]);

        $queries = $this->getExecutedQueries();
        $this->assertCount(1, preg_grep('/FROM `?ibl_saved_depth_charts`? WHERE id = 7 AND teamid = 1 LIMIT 1/i', $queries));
        $this->assertCount(0, preg_grep('/WHERE id = 7 AND teamid = 2 LIMIT 1/i', $queries));
    }

    public function testHandleLoadParsesNumericStringId(): void
    {
        $this->registerLoadFixture(7, 1, null);

        $data = $this->loadJson(1, ['id' => '7']);

        $this->assertSame(7, $data['depthChart']['id']);
    }

    public function testHandleLoadRejectsNonNumericStringId(): void
    {
        $data = $this->loadJson(1, ['id' => '7abc']);

        $this->assertSame(['error' => 'Invalid depth chart ID'], $data);
    }

    public function testHandleLoadRejectsFloatId(): void
    {
        $data = $this->loadJson(1, ['id' => 7.5]);

        $this->assertSame(['error' => 'Invalid depth chart ID'], $data);
    }

    public function testHandleLoadRejectsMissingId(): void
    {
        $data = $this->loadJson(1, []);

        $this->assertSame(['error' => 'Invalid depth chart ID'], $data);
    }

    public function testHandleListReturnsOnlyCallerTeamRows(): void
    {
        $this->mockDb->onQuery(
            'FROM `?ibl_saved_depth_charts`? WHERE teamid = 1 ORDER BY created_at DESC, id DESC$',
            [$this->dcRow(7, 1, null)]
        );
        $this->mockDb->onQuery(
            'FROM `?ibl_saved_depth_charts`? WHERE teamid = 2 ORDER BY created_at DESC, id DESC$',
            [$this->dcRow(8, 2, null)]
        );

        $output = $this->captureOutput(fn () => $this->handler->handle('list', 1, 'testuser', ['teamid' => 2]));
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertSame(['depthCharts', 'options', 'currentLiveLabel'], array_keys($data));
        $this->assertSame([7], array_column($data['depthCharts'], 'id'));
        $this->assertSame([
            'id' => 7, 'name' => 'Pinned DC', 'simStartDate' => '2024-01-01', 'simEndDate' => null,
            'simNumberStart' => 1, 'simNumberEnd' => null, 'isActive' => true,
            'createdAt' => '2024-01-01 00:00:00',
        ], $data['depthCharts'][0]);
    }

    public function testHandlerSourceNeverReadsTeamidFromRequestOrGlobals(): void
    {
        $file = (new \ReflectionClass(DepthChartSnapshotApiHandler::class))->getFileName();
        $this->assertIsString($file);
        $src = file_get_contents($file);
        $this->assertIsString($src);

        $this->assertDoesNotMatchRegularExpression(
            '/\$params\[\s*[\'"]teamid[\'"]\s*\]|\$_(SESSION|GET|POST|REQUEST|COOKIE)\b|getTidFromTeamname|getTeamnameFromUsername/',
            $src
        );
    }

    public function testPureHelpersAreStaticAndCarryNoDbOrTeamid(): void
    {
        $expectedParams = [
            'parseId' => ['params'],
            'buildPlayersPayload' => ['players', 'tradedPids'],
            'buildNewPlayers' => ['rosterPlayers', 'newPlayerPids'],
        ];

        foreach ($expectedParams as $name => $paramNames) {
            $method = new \ReflectionMethod(DepthChartSnapshotApiHandler::class, $name);
            $this->assertTrue($method->isStatic(), $name . ' must be static');
            $this->assertTrue($method->isPrivate(), $name . ' must be private');
            $this->assertSame(
                $paramNames,
                array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $method->getParameters()),
                $name . ' parameter list changed'
            );
            foreach ($method->getParameters() as $param) {
                $this->assertNotSame('teamid', $param->getName(), $name . ' must not take teamid');
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $this->assertNotSame('mysqli', $type->getName(), $name . ' must not take a DB handle');
                }
            }
        }
    }

    public function testRenderStatsHtmlTakesTeamidAsIntParameter(): void
    {
        $method = new \ReflectionMethod(DepthChartSnapshotApiHandler::class, 'renderStatsHtml');
        $this->assertTrue($method->isPrivate());
        $this->assertFalse($method->isStatic());

        $first = $method->getParameters()[0];
        $this->assertSame('teamid', $first->getName());
        $type = $first->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame('int', $type->getName());
    }
}
