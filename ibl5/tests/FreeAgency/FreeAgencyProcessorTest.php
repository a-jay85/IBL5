<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyProcessor;

/**
 * FreeAgencyProcessorTest - Tests for free agency offer processing
 *
 * Tests the main processor workflow including:
 * - Offer submission validation
 * - Offer deletion
 * - Already-signed player checks
 * 
 * Note: Uses MockDatabase setMockData() which sets a single dataset for all queries.
 * Tests are designed to work within this constraint.
 */
class FreeAgencyProcessorTest extends TestCase
{
    private \MockDatabase $mockDb;
    private FreeAgencyProcessor $processor;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->injectGlobalMockDb();
        $this->processor = new FreeAgencyProcessor($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function injectGlobalMockDb(): void
    {
        $mockDb = $this->mockDb;
        
        $GLOBALS['mysqli_db'] = new class($mockDb) {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            public function prepare(string $query): \MockPreparedStatement
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            public function query(string $query): mixed
            {
                return $this->mockDb->sql_query($query);
            }

            public function real_escape_string(string $value): string
            {
                return addslashes($value);
            }
        };
    }

    // ============================================
    // OFFER DELETION TESTS
    // ============================================

    public function testDeleteOffersExecutesDeleteQuery(): void
    {
        // Setup complete player data to avoid undefined array key warnings
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $this->processor->deleteOffers('Test Team', 1);

        // Verify that a DELETE query was executed
        $queries = $this->mockDb->getExecutedQueries();
        $deleteQueries = array_filter($queries, fn($q) => stripos($q, 'DELETE') !== false);
        
        $this->assertNotEmpty($deleteQueries, 'Expected DELETE query to be executed');
    }

    public function testDeleteOffersTargetsCorrectTable(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $this->processor->deleteOffers('Test Team', 1);

        $queries = $this->mockDb->getExecutedQueries();
        $faOfferQueries = array_filter($queries, fn($q) => stripos($q, 'ibl_fa_offers') !== false);
        
        $this->assertNotEmpty($faOfferQueries, 'Expected query targeting ibl_fa_offers table');
    }

    public function testDeleteOffersReturnsHtmlResponse(): void
    {
        $this->mockDb->setMockData([$this->getCompletePlayerData()]);

        $result = $this->processor->deleteOffers('Test Team', 1);

        $this->assertIsString($result);
        // The result should contain HTML markup
        $this->assertMatchesRegularExpression('/<.*>/', $result);
    }

    // ============================================
    // CONSTRUCTOR AND INITIALIZATION TESTS
    // ============================================

    public function testProcessorAcceptsDatabaseInConstructor(): void
    {
        $mockDb = new \MockDatabase();
        $processor = new FreeAgencyProcessor($mockDb);
        
        $this->assertInstanceOf(FreeAgencyProcessor::class, $processor);
    }

    public function testProcessorCanBeInstantiatedMultipleTimes(): void
    {
        $processor1 = new FreeAgencyProcessor($this->mockDb);
        $processor2 = new FreeAgencyProcessor($this->mockDb);
        
        $this->assertInstanceOf(FreeAgencyProcessor::class, $processor1);
        $this->assertInstanceOf(FreeAgencyProcessor::class, $processor2);
        $this->assertNotSame($processor1, $processor2);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get complete player data with all required fields to avoid undefined array key warnings
     */
    private function getCompletePlayerData(array $overrides = []): array
    {
        return array_merge([
            // Basic fields
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'nickname' => '',
            'teamname' => 'Test Team',
            'tid' => 1,
            'pos' => 'G',
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            // Physical attributes
            'height' => 75,
            'weight' => 200,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
            // Contract fields
            'cy' => 0,
            'cyt' => 0,
            'cy1' => 0,
            'cy2' => 0,
            'cy3' => 0,
            'cy4' => 0,
            'cy5' => 0,
            'cy6' => 0,
            'exp' => 3,
            'bird' => 0,
            'bird_years' => 0,
            // Status fields
            'retired' => 0,
            'injured' => 0,
            'signed' => 0,
            'droptime' => 0,
            // Free agency preferences (snake_case and camelCase)
            'fa_loyalty' => 50,
            'fa_playing_time' => 50,
            'fa_play_for_winner' => 50,
            'fa_tradition' => 50,
            'fa_security' => 50,
            'loyalty' => 50,
            'playingTime' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
            // Base rating fields (r_*)
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_tga' => 50,
            'r_tgp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_to' => 50,
            'r_blk' => 50,
            'r_foul' => 50,
            'r_ass' => 50,
            'r_tvr' => 50,
            'r_low' => 50,
            'r_def' => 50,
            'r_dis' => 50,
            'r_pss' => 50,
            'r_hnb' => 50,
            'r_ins' => 50,
            // Position-based ratings
            'oo' => 50,
            'od' => 50,
            'do' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'to' => 50,
            'td' => 50,
            // Other ratings
            'Clutch' => 50,
            'Consistency' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'ovr' => 75,
            // Draft fields
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 10,
            'draftedby' => 'Test Team',
            'draftedbycurrentname' => 'Test Team',
            'college' => 'Test University',
        ], $overrides);
    }
}
