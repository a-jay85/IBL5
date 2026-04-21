<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use PHPUnit\Framework\TestCase;
use DepthChartEntry\DepthChartEntryController;

/**
 * DepthChartEntryControllerTest - Tests for the depth chart workflow controller
 *
 * Tests:
 * - Controller instantiation
 * - Interface compliance
 * - Dependency injection
 */
class DepthChartEntryControllerTest extends TestCase
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
        
        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                // Don't call parent::__construct() to avoid real DB connection
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
    // CONSTRUCTOR TESTS
    // ============================================

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new DepthChartEntryController($this->mockMysqliDb);
        
        $this->assertInstanceOf(DepthChartEntryController::class, $controller);
    }

    public function testControllerImplementsCorrectInterface(): void
    {
        $controller = new DepthChartEntryController($this->mockMysqliDb);
        
        $this->assertInstanceOf(
            \DepthChartEntry\Contracts\DepthChartEntryControllerInterface::class,
            $controller
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleControllersCanBeInstantiated(): void
    {
        $controller1 = new DepthChartEntryController($this->mockMysqliDb);
        $controller2 = new DepthChartEntryController($this->mockMysqliDb);

        $this->assertInstanceOf(DepthChartEntryController::class, $controller1);
        $this->assertInstanceOf(DepthChartEntryController::class, $controller2);
        $this->assertNotSame($controller1, $controller2);
    }

    // ============================================
    // getTableOutput() SIGNATURE TESTS
    // ============================================

    public function testGetTableOutputAcceptsSplitParameter(): void
    {
        $method = new \ReflectionMethod(DepthChartEntryController::class, 'getTableOutput');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('teamID', $params[0]->getName());
        $this->assertSame('display', $params[1]->getName());
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
        $this->assertTrue($params[2]->allowsNull());
        $this->assertNull($params[2]->getDefaultValue());
    }

    public function testInterfaceDeclaresGetTableOutputWithSplitParameter(): void
    {
        $method = new \ReflectionMethod(
            \DepthChartEntry\Contracts\DepthChartEntryControllerInterface::class,
            'getTableOutput'
        );
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('split', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }

    // ============================================
    // buildFormOverride() — PRG hydration helper
    // ============================================

    public function testBuildFormOverrideMapsPidToSubmittedValues(): void
    {
        $postData = [
            'pid1' => '42',
            'pg1' => '1',
            'sg1' => '2',
            'sf1' => '3',
            'pf1' => '4',
            'c1' => '5',
            'canPlayInGame1' => '1',
            'min1' => '32',
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertArrayHasKey(42, $override);
        $this->assertSame(
            [
                'dc_PGDepth' => 1,
                'dc_SGDepth' => 2,
                'dc_SFDepth' => 3,
                'dc_PFDepth' => 4,
                'dc_CDepth' => 5,
                'dc_canPlayInGame' => 1,
                'dc_minutes' => 32,
            ],
            $override[42],
        );
    }

    public function testBuildFormOverrideClampsOutOfRangeDepth(): void
    {
        $postData = [
            'pid1' => '7',
            'pg1' => '-3',
            'sg1' => '99',
            'sf1' => '0',
            'pf1' => '0',
            'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '12',
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertSame(0, $override[7]['dc_PGDepth'], 'depth < 0 clamps to 0');
        $this->assertSame(5, $override[7]['dc_SGDepth'], 'depth > 5 clamps to 5');
    }

    public function testBuildFormOverrideClampsMinutesTo0_40(): void
    {
        $postData = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '99',
            'pid2' => '8',
            'pg2' => '0', 'sg2' => '0', 'sf2' => '0', 'pf2' => '0', 'c2' => '0',
            'canPlayInGame2' => '0',
            'min2' => '-5',
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertSame(40, $override[7]['dc_minutes'], 'minutes > 40 clamps to 40');
        $this->assertSame(0, $override[8]['dc_minutes'], 'minutes < 0 clamps to 0');
    }

    public function testBuildFormOverrideTreatsActiveFlagAsBoolean(): void
    {
        // Only the literal integer 1 marks a player active; anything else
        // (including the "0" hidden-input fallback that the view always
        // emits alongside the checkbox) maps to inactive.
        $postDataActive = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '1',
            'min1' => '0',
        ];
        $postDataInactive = [
            'pid1' => '7',
            'pg1' => '0', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '0',
            'min1' => '0',
        ];

        $active = DepthChartEntryController::buildFormOverride($postDataActive);
        $inactive = DepthChartEntryController::buildFormOverride($postDataInactive);

        $this->assertSame(1, $active[7]['dc_canPlayInGame']);
        $this->assertSame(0, $inactive[7]['dc_canPlayInGame']);
    }

    public function testBuildFormOverrideSkipsRowsWithoutPid(): void
    {
        $postData = [
            'Team_Name' => 'Metros',
            'pg1' => '3',
            // no pid1 — handler never saw this row
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertSame([], $override);
    }

    public function testBuildFormOverrideSkipsRowsWithZeroOrInvalidPid(): void
    {
        $postData = [
            'pid1' => '0',      // zero PID → skip
            'pg1' => '1',
            'pid2' => 'abc',    // non-numeric → skip
            'pg2' => '2',
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertSame([], $override);
    }

    public function testBuildFormOverrideHandlesMultipleRows(): void
    {
        $postData = [
            'pid1' => '100', 'pg1' => '1', 'sg1' => '0', 'sf1' => '0', 'pf1' => '0', 'c1' => '0',
            'canPlayInGame1' => '1', 'min1' => '30',
            'pid2' => '200', 'pg2' => '0', 'sg2' => '2', 'sf2' => '0', 'pf2' => '0', 'c2' => '0',
            'canPlayInGame2' => '1', 'min2' => '25',
            'pid3' => '300', 'pg3' => '0', 'sg3' => '0', 'sf3' => '3', 'pf3' => '0', 'c3' => '0',
            'canPlayInGame3' => '0', 'min3' => '0',
        ];

        $override = DepthChartEntryController::buildFormOverride($postData);

        $this->assertSame([100, 200, 300], array_keys($override));
        $this->assertSame(1, $override[100]['dc_PGDepth']);
        $this->assertSame(2, $override[200]['dc_SGDepth']);
        $this->assertSame(3, $override[300]['dc_SFDepth']);
        $this->assertSame(0, $override[300]['dc_canPlayInGame']);
    }
}
