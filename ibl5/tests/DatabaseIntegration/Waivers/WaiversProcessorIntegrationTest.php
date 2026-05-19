<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Waivers;

use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Repositories\PlayerLookupRepository;
use Topics\News\NewsRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Waivers\WaiversProcessor;
use Waivers\WaiversRepository;
use Waivers\WaiversValidator;

#[Group('database')]
class WaiversProcessorIntegrationTest extends DatabaseTestCase
{
    private WaiversProcessor $processor;
    private TeamIdentityRepository $teamIdentityRepository;
    private PlayerLookupRepository $playerLookupRepository;

    private const TEST_PID_BASE = 200060100;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['SERVER_NAME'] = 'localhost';

        $repository = new WaiversRepository($this->db);
        $this->teamIdentityRepository = new TeamIdentityRepository($this->db);
        $this->playerLookupRepository = new PlayerLookupRepository($this->db);
        $validator = new WaiversValidator();
        $newsService = new NewsRepository($this->db);

        $this->processor = new WaiversProcessor(
            $repository,
            $this->teamIdentityRepository,
            $this->playerLookupRepository,
            $validator,
            $newsService,
            $this->db
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    public function testDropPlayerSetsOrdinalAndDroptime(): void
    {
        $pid = self::TEST_PID_BASE + 1;
        $this->insertTestPlayer($pid, 'Waiver Drop Test', [
            'teamid' => 1,
            'ordinal' => 5,
            'droptime' => 0,
        ]);

        $result = $this->processor->processDrop($pid, 'Metros', 5, 3000);

        $this->assertTrue($result['success']);
        $this->assertSame('player_dropped', $result['result']);

        $player = $this->playerLookupRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(1000, $player['ordinal']);
        $this->assertGreaterThan(0, $player['droptime']);
    }

    public function testSignPlayerWithoutExistingContract(): void
    {
        $pid = self::TEST_PID_BASE + 2;
        $this->insertTestPlayer($pid, 'Waiver FA Pickup', [
            'teamid' => 0,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'exp' => 5,
            'bird' => 0,
            'ordinal' => 1000,
            'droptime' => 1000000,
        ]);

        $result = $this->processor->processAdd($pid, 'Metros', 4, 3000);

        $this->assertTrue($result['success']);
        $this->assertSame('player_added', $result['result']);

        $player = $this->playerLookupRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(1, $player['teamid']);
        $this->assertSame(800, $player['ordinal']);
        $this->assertSame(0, $player['droptime']);
        $this->assertSame(0, $player['bird']);
        $this->assertSame(0, $player['cy']);
        $this->assertSame(1, $player['cyt']);
        $vetMin = \ContractRules::getVeteranMinimumSalary(5);
        $this->assertSame($vetMin, $player['salary_yr1']);
        $this->assertSame(0, $player['salary_yr2']);
    }

    public function testSignPlayerWithExistingContractPreservesContract(): void
    {
        $pid = self::TEST_PID_BASE + 3;
        $this->insertTestPlayer($pid, 'Waiver Contract Keep', [
            'teamid' => 0,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 500,
            'salary_yr2' => 600,
            'salary_yr3' => 700,
            'exp' => 4,
            'bird' => 2,
            'ordinal' => 1000,
            'droptime' => 1000000,
        ]);

        $result = $this->processor->processAdd($pid, 'Metros', 4, 3000);

        $this->assertTrue($result['success']);
        $this->assertSame('player_added', $result['result']);

        $player = $this->playerLookupRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(1, $player['teamid']);
        $this->assertSame(800, $player['ordinal']);
        $this->assertSame(0, $player['droptime']);
        $this->assertSame(0, $player['bird']);
        $this->assertSame(1, $player['cy']);
        $this->assertSame(3, $player['cyt']);
        $this->assertSame(500, $player['salary_yr1']);
        $this->assertSame(600, $player['salary_yr2']);
        $this->assertSame(700, $player['salary_yr3']);
    }
}
