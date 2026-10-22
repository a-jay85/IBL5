<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use BasketballStats\Tables\SplitStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization pins for SplitStats::render().
 *
 * The golden under fixtures/ was captured once from the unmodified class. Never
 * regenerate it to make a failing test pass.
 *
 * @covers \BasketballStats\Tables\SplitStats
 */
final class SplitStatsTest extends TestCase
{
    private function makeTeam(): \Team\Team
    {
        $team = self::createStub(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 7;
        $team->name = 'Test Team';

        return $team;
    }

    /**
     * @return array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}
     */
    private static function playerRow(int $pid, string $name, string $pos): array
    {
        return [
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'games' => 12,
            'gameMINavg' => '31.5',
            'gameFGMavg' => '6.20',
            'gameFGAavg' => '13.10',
            'gameFGPavg' => '0.473',
            'gameFTMavg' => '3.40',
            'gameFTAavg' => '4.10',
            'gameFTPavg' => '0.829',
            'game3GMavg' => '1.70',
            'game3GAavg' => '4.30',
            'game3GPavg' => '0.395',
            'gameORBavg' => '1.1',
            'gameREBavg' => '5.2',
            'gameASTavg' => '7.3',
            'gameSTLavg' => '1.4',
            'gameTOVavg' => '2.5',
            'gameBLKavg' => '0.6',
            'gamePFavg' => '2.7',
            'gamePTSavg' => '17.8',
        ];
    }

    /**
     * @return array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}
     */
    private static function nullStatsRow(int $pid, string $name, string $pos): array
    {
        return [
            'name' => $name,
            'pos' => $pos,
            'pid' => $pid,
            'games' => 0,
            'gameMINavg' => null,
            'gameFGMavg' => null,
            'gameFGAavg' => null,
            'gameFGPavg' => null,
            'gameFTMavg' => null,
            'gameFTAavg' => null,
            'gameFTPavg' => null,
            'game3GMavg' => null,
            'game3GAavg' => null,
            'game3GPavg' => null,
            'gameORBavg' => null,
            'gameREBavg' => null,
            'gameASTavg' => null,
            'gameSTLavg' => null,
            'gameTOVavg' => null,
            'gameBLKavg' => null,
            'gamePFavg' => null,
            'gamePTSavg' => null,
        ];
    }

    /**
     * @return array<string, array{string, list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}>, string, list<int>}>
     */
    public static function characterizationCases(): array
    {
        return [
            'hostile-rows' => [
                'hostile-rows',
                [
                    self::playerRow(7, '<script>alert("n")</script> & O\'Neil', '<b>PG</b>'),
                    self::nullStatsRow(8, 'Null Stats', 'C'),
                    self::playerRow(9, 'Cash | Buyout', 'SF'),
                ],
                'Away <img src=x onerror=alert(1)>',
                [7],
            ],
            'empty-hostile-label' => [
                'empty-hostile-label',
                [],
                '<img src=x onerror="alert(1)"> & Road',
                [],
            ],
        ];
    }

    public function testRenderMatchesGolden(): void
    {
        $html = SplitStats::render([self::playerRow(7, 'Ann Starter', 'PG')], $this->makeTeam(), 'Home', [7]);

        $goldenPath = __DIR__ . '/fixtures/split-stats-basic.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testEmptyRowsStillEmitsOpenTag(): void
    {
        $html = SplitStats::render([], $this->makeTeam(), 'Away');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable" style="', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }

    /**
     * @param list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}> $rows
     * @param list<int> $starterPids
     */
    #[DataProvider('characterizationCases')]
    public function testRenderMatchesCharacterizationGolden(string $case, array $rows, string $splitLabel, array $starterPids): void
    {
        $html = SplitStats::render($rows, $this->makeTeam(), $splitLabel, $starterPids);

        $this->assertStringEqualsFile(__DIR__ . '/fixtures/split-stats-' . $case . '.golden.html', $html);
    }

    public function testHostilePlayerNameAndPositionAreEscaped(): void
    {
        $html = SplitStats::render(self::characterizationCases()['hostile-rows'][1], $this->makeTeam(), 'Away', [7]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>PG</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;PG&lt;/b&gt;', $html);
    }

    public function testHostileSplitLabelIsEscapedInEmptyRowsMessage(): void
    {
        $html = SplitStats::render([], $this->makeTeam(), '<img src=x onerror="alert(1)"> & Road');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; Road', $html);
        $this->assertStringContainsString('class="table-empty-message"', $html);
    }

    public function testNullStatsRowRendersEachDefaultLiteral(): void
    {
        $html = SplitStats::render([self::nullStatsRow(8, 'Null Stats', 'C')], $this->makeTeam(), 'Home');

        $this->assertSame(8, substr_count($html, '<td>0.0</td>'));
        $this->assertSame(6, substr_count($html, '<td>0.00</td>'));
        $this->assertSame(3, substr_count($html, '0.000</td>'));
        $this->assertSame(1, substr_count($html, 'sep-r-team">0.0</td>'));
    }

    public function testNonEmptyTableOmitsEmptyRowsMessage(): void
    {
        $html = SplitStats::render(self::characterizationCases()['hostile-rows'][1], $this->makeTeam(), 'Away <img src=x onerror=alert(1)>', [7]);

        $this->assertStringNotContainsString('table-empty-message', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function testSplitStatsExposesOnlyRenderAsPublicMethod(): void
    {
        $class = new \ReflectionClass(SplitStats::class);

        $publicNames = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $class->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        $this->assertSame(['render'], $publicNames);
        $this->assertSame([], $class->getProperties());
        $this->assertNull($class->getConstructor());

        $render = $class->getMethod('render');
        $this->assertTrue($render->isStatic());
        $params = $render->getParameters();
        $this->assertSame(
            ['rows', 'team', 'splitLabel', 'starterPids'],
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $params)
        );
        $teamType = $params[1]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $teamType);
        $this->assertSame('Team\Team', $teamType->getName());
        $this->assertTrue($params[3]->isOptional());
        $returnType = $render->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
    }

    public function testRenderHelpersArePrivateStaticAndTakeNoDatabaseOrActorValue(): void
    {
        $class = new \ReflectionClass(SplitStats::class);
        $helpers = array_values(array_filter(
            $class->getMethods(),
            static fn (\ReflectionMethod $m): bool => !$m->isPublic()
        ));

        $this->assertSame(
            ['mapRows', 'renderHeader', 'renderPlayerRow'],
            array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $helpers)
        );

        $expectedReturnTypes = ['mapRows' => 'array', 'renderHeader' => 'string', 'renderPlayerRow' => 'string'];
        foreach ($helpers as $helper) {
            $this->assertTrue($helper->isPrivate(), $helper->getName());
            $this->assertTrue($helper->isStatic(), $helper->getName());
            $returnType = $helper->getReturnType();
            $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, $helper->getName());
            $this->assertSame($expectedReturnTypes[$helper->getName()], $returnType->getName(), $helper->getName());
        }

        $this->assertSame([], self::helperParameterViolations($class));
    }

    /**
     * @param \ReflectionClass<object> $class
     * @return list<string> one entry per non-public method parameter typed outside the allowlist
     */
    private static function helperParameterViolations(\ReflectionClass $class): array
    {
        $violations = [];
        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                continue;
            }
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $name = $type instanceof \ReflectionNamedType ? $type->getName() : 'untyped';
                if (!in_array($name, ['array'], true)) {
                    $violations[] = $method->getName() . ' $' . $param->getName() . ': ' . $name;
                }
            }
        }

        return $violations;
    }

    public function testDischargeCheckFlagsDatabaseHandleAndIdentityParameters(): void
    {
        $mutant = new class {
            private static ?\mysqli $db = null;

            public function connect(\mysqli $db): void
            {
                self::$db = $db;
            }

            public function touch(\Team\Team $team): string
            {
                return self::$db === null ? '' : self::renderPlayerRow(self::$db, 0, $team);
            }

            private static function renderPlayerRow(\mysqli $db, int $loggedInTeamID, \Team\Team $team): string
            {
                return $db->host_info . $loggedInTeamID . $team->name;
            }
        };
        $class = new \ReflectionClass($mutant);

        $this->assertSame(
            ['renderPlayerRow $db: mysqli', 'renderPlayerRow $loggedInTeamID: int', 'renderPlayerRow $team: Team\Team'],
            self::helperParameterViolations($class)
        );
        $this->assertCount(1, $class->getProperties());
    }
}
