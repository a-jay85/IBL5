<?php

declare(strict_types=1);

namespace Tests\Negotiation;

use Negotiation\NegotiationRepository;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Tests\Clock\FixedClock;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins the injected clock at the market-maximums cache expiry boundary.
 */
class NegotiationRepositoryClockTest extends TestCase
{
    private const EXPIRATION = 1_700_000_000;

    private function cachedMaximumsJson(): string
    {
        $keys = ['fga', 'fgp', 'fta', 'ftp', 'tga', 'tgp', 'orb', 'drb', 'ast', 'stl', 'tov', 'blk', 'foul', 'oo', 'od', 'do', 'dd', 'po', 'pd', 'to', 'td'];
        return (string) json_encode(array_fill_keys($keys, 77));
    }

    private function repositoryAt(MockDatabase $db, int $now): NegotiationRepository
    {
        $db->onQuery('FROM cache', [['value' => $this->cachedMaximumsJson(), 'expiration' => self::EXPIRATION]]);

        return new NegotiationRepository($db, self::createStub(SalaryCapRepositoryInterface::class), new FixedClock($now));
    }

    public function testCacheIsServedWhenClockIsAtExpiryBoundary(): void
    {
        $db = new MockDatabase();
        $repo = $this->repositoryAt($db, self::EXPIRATION);

        $result = $repo->getMarketMaximums();

        self::assertSame(77, $result['fga']);
        foreach ($db->getExecutedQueries() as $query) {
            self::assertStringNotContainsString('REPLACE INTO cache', $query);
        }
    }

    public function testCacheIsRecomputedAndRewrittenOneSecondAfterExpiry(): void
    {
        $now = self::EXPIRATION + 1;
        $db = new MockDatabase();
        $repo = $this->repositoryAt($db, $now);

        $repo->getMarketMaximums();

        $rewrote = false;
        foreach ($db->getExecutedQueries() as $query) {
            if (str_contains($query, 'REPLACE INTO cache')) {
                $rewrote = true;
            }
        }
        self::assertTrue($rewrote, 'Expired cache should be recomputed and rewritten');
        self::assertSame($now + 86400, $db->getLastBoundParams()[2] ?? null);
    }
}
