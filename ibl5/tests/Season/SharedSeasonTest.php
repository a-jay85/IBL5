<?php

declare(strict_types=1);

namespace Tests\Season;

use League\LeagueContext;
use PHPUnit\Framework\TestCase;
use Season\SharedSeason;
use Tests\WideUnit\Mocks\MockDatabase;

final class SharedSeasonTest extends TestCase
{
    protected function setUp(): void
    {
        SharedSeason::disable();
    }

    protected function tearDown(): void
    {
        SharedSeason::disable();
    }

    public function testGetReturnsFreshInstanceWhenNotEnabled(): void
    {
        $db = new MockDatabase();
        self::assertNotSame(SharedSeason::get($db), SharedSeason::get($db));

        $result = $this->runRealSeasonScenario(enabled: false);

        self::assertFalse($result['same']);
        self::assertGreaterThan(0, $result['n1']);
        self::assertSame(2 * $result['n1'], $result['n2']);
    }

    public function testEnabledGetSharesOneInstanceAndQueriesSettingsOnce(): void
    {
        SharedSeason::enable();
        $db = new MockDatabase();
        self::assertSame(SharedSeason::get($db), SharedSeason::get($db));

        $result = $this->runRealSeasonScenario(enabled: true);

        self::assertTrue($result['same']);
        self::assertGreaterThan(0, $result['n1']);
        self::assertSame($result['n1'], $result['n2']);
    }

    public function testEnabledKeysInstancesByArgumentLeague(): void
    {
        SharedSeason::enable();
        $db = new MockDatabase();
        $iblContext = $this->createLeagueContextStub(LeagueContext::LEAGUE_IBL);
        $olympicsContext = $this->createLeagueContextStub(LeagueContext::LEAGUE_OLYMPICS);

        $none = SharedSeason::get($db);
        $ibl = SharedSeason::get($db, $iblContext);
        $olympics = SharedSeason::get($db, $olympicsContext);

        self::assertNotSame($none, $ibl);
        self::assertNotSame($none, $olympics);
        self::assertNotSame($ibl, $olympics);
        self::assertSame($olympics, SharedSeason::get($db, $olympicsContext));
    }

    public function testEnabledRebuildsForDifferentDatabase(): void
    {
        SharedSeason::enable();
        $dbA = new MockDatabase();
        $dbB = new MockDatabase();

        $first = SharedSeason::get($dbA);
        $other = SharedSeason::get($dbB);
        $back = SharedSeason::get($dbA);

        self::assertNotSame($first, $other);
        self::assertNotSame($first, $back);
    }

    public function testDisableDropsAlreadyCachedInstance(): void
    {
        SharedSeason::enable();
        $db = new MockDatabase();
        $a = SharedSeason::get($db);

        SharedSeason::disable();
        self::assertFalse(SharedSeason::isEnabled());

        SharedSeason::enable();
        $b = SharedSeason::get($db);

        self::assertNotSame($a, $b);
    }

    public function testResetDropsCacheButStaysEnabled(): void
    {
        SharedSeason::enable();
        $db = new MockDatabase();
        $a = SharedSeason::get($db);

        SharedSeason::reset();
        self::assertTrue(SharedSeason::isEnabled());

        $b = SharedSeason::get($db);
        $c = SharedSeason::get($db);

        self::assertNotSame($a, $b);
        self::assertSame($b, $c);
    }

    private function createLeagueContextStub(string $league): LeagueContext
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('getCurrentLeague')->willReturn($league);

        return $context;
    }

    /**
     * Two get() calls against the real Season in a child process, which stands in for two consumers.
     * The aliased mock never queries, so the settings-query counts can only be read from the real class.
     *
     * @return array{same: bool, n1: int, n2: int}
     */
    private function runRealSeasonScenario(bool $enabled): array
    {
        $enable = $enabled ? 'Season\\SharedSeason::enable();' : '';
        $result = RealSeasonProcess::run(<<<PHP
            \$db = new Tests\\WideUnit\\Mocks\\MockDatabase();
            \$db->onQuery('ibl_settings', []);
            \$count = static fn (): int => count(array_filter(
                array_merge(\$db->getExecutedQueries(), \$db->getPreparedQueries()),
                static fn (string \$q): bool => str_contains(\$q, 'ibl_settings'),
            ));
            $enable
            \$a = Season\\SharedSeason::get(\$db);
            \$n1 = \$count();
            \$b = Season\\SharedSeason::get(\$db);
            \$n2 = \$count();
            echo json_encode(['same' => \$a === \$b, 'n1' => \$n1, 'n2' => \$n2]);
            PHP);

        /** @var array{same: bool, n1: int, n2: int} $result */
        return $result;
    }
}
