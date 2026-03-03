<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PlayerIdResolver's multi-strategy fallback chain and caching.
 *
 * Strategy order:
 * 1. ibl_hist by name + team + year
 * 2. ibl_plr by name + teamname
 * 3. ibl_hist by name + year only (traded players)
 * 4. ibl_plr by name only
 */
class PlayerIdResolverTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class ($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                $result = $this->mockDb->sql_query($query);
                if ($result instanceof \MockDatabaseResult) {
                    return false;
                }
                return (bool) $result;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // STRATEGY 1: ibl_hist by name + team + year
    // ============================================

    public function testStrategy1FindsPlayerInHistByNameTeamYear(): void
    {
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 42]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('John Smith', 'Miami', 2025);

        $this->assertSame(42, $result);
    }

    // ============================================
    // STRATEGY 2: ibl_plr by name + teamname
    // ============================================

    public function testStrategy2FindsPlayerInPlrByNameAndTeam(): void
    {
        // Strategy 1 misses, strategy 2 hits
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', [['pid' => 77]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('Jane Doe', 'Chicago', 2025);

        $this->assertSame(77, $result);
    }

    // ============================================
    // STRATEGY 3: ibl_hist by name + year only
    // ============================================

    public function testStrategy3FindsPlayerInHistByNameAndYearOnly(): void
    {
        // Strategies 1-2 miss, strategy 3 hits (traded player)
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND year.*LIMIT', [['pid' => 99]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('Traded Player', 'OldTeam', 2025);

        $this->assertSame(99, $result);
    }

    // ============================================
    // STRATEGY 4: ibl_plr by name only
    // ============================================

    public function testStrategy4FindsPlayerInPlrByNameOnly(): void
    {
        // Strategies 1-3 miss, strategy 4 hits
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND year.*LIMIT', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*LIMIT', [['pid' => 101]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('Unknown Team Guy', 'NoTeam', 2025);

        $this->assertSame(101, $result);
    }

    // ============================================
    // FALLBACK ORDER
    // ============================================

    public function testEarlierStrategyPreventsLaterStrategies(): void
    {
        // Strategy 1 hits — strategies 2-4 should never be reached
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 1]]);
        // If strategy 2 were reached, it would return a different pid
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', [['pid' => 999]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('Matched Early', 'TeamA', 2025);

        $this->assertSame(1, $result);
    }

    // ============================================
    // NO MATCH
    // ============================================

    public function testReturnsNullWhenNoStrategyMatches(): void
    {
        // All strategies miss
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND year.*LIMIT', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*LIMIT', []);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);
        $result = $resolver->resolve('Nobody', 'Nowhere', 2025);

        $this->assertNull($result);
    }

    // ============================================
    // CACHING
    // ============================================

    public function testCacheHitReturnsCachedResult(): void
    {
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 50]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);

        // First call resolves via strategy 1
        $first = $resolver->resolve('Cached Player', 'TeamX', 2025);
        $this->assertSame(50, $first);

        // Change mock data — if cache works, we should still get 50
        $this->mockDb->clearQueryPatterns();
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 999]]);

        $second = $resolver->resolve('Cached Player', 'TeamX', 2025);
        $this->assertSame(50, $second);
    }

    public function testNullResultsAreCached(): void
    {
        // All strategies miss
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*AND teamname', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND year.*LIMIT', []);
        $this->mockDb->onQuery('SELECT pid FROM ibl_plr WHERE name.*LIMIT', []);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);

        $first = $resolver->resolve('Ghost', 'Nowhere', 2025);
        $this->assertNull($first);

        // Now strategy 1 would return a result — but cache should still return null
        $this->mockDb->clearQueryPatterns();
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 123]]);

        $second = $resolver->resolve('Ghost', 'Nowhere', 2025);
        $this->assertNull($second);
    }

    public function testClearCacheResetsCache(): void
    {
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 10]]);

        $resolver = new PlayerIdResolver($this->mockMysqliDb);

        $first = $resolver->resolve('Clearing', 'TeamY', 2025);
        $this->assertSame(10, $first);

        // Clear cache and change mock data
        $resolver->clearCache();
        $this->mockDb->clearQueryPatterns();
        $this->mockDb->onQuery('SELECT pid FROM ibl_hist WHERE name.*AND team.*AND year', [['pid' => 20]]);

        $second = $resolver->resolve('Clearing', 'TeamY', 2025);
        $this->assertSame(20, $second);
    }
}
