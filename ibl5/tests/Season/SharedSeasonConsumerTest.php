<?php

declare(strict_types=1);

namespace Tests\Season;

use PHPUnit\Framework\TestCase;

/**
 * Two real consumers that build Season in their constructors, run against the real Season class.
 *
 * The aliased mock never queries, so the settings-query counts can only be read in a child process.
 */
final class SharedSeasonConsumerTest extends TestCase
{
    public function testTwoArmedConsumersShareOneSeasonAndQuerySettingsOnce(): void
    {
        $result = $this->runConsumers(true);

        self::assertGreaterThan(0, $result['n1']);
        self::assertSame($result['n1'], $result['n2']);
    }

    public function testUnarmedConsumersBuildSeparateSeasons(): void
    {
        $result = $this->runConsumers(false);

        self::assertGreaterThan(0, $result['n1']);
        self::assertSame(2 * $result['n1'], $result['n2']);
    }

    /**
     * @return array{n1: int, n2: int}
     */
    private function runConsumers(bool $enabled): array
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
            new Trading\\TradeValidator(\$db);
            \$n1 = \$count();
            new Negotiation\\NegotiationValidator(\$db);
            \$n2 = \$count();
            echo json_encode(['n1' => \$n1, 'n2' => \$n2]);
            PHP);

        /** @var array{n1: int, n2: int} $result */
        return $result;
    }
}
