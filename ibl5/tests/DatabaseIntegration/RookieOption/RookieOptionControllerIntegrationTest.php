<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\RookieOption;

use PHPUnit\Framework\Attributes\Group;
use RookieOption\RookieOptionRepository;
use Services\PlayerLookupRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class RookieOptionControllerIntegrationTest extends DatabaseTestCase
{
    private RookieOptionRepository $repository;
    private PlayerLookupRepository $commonRepository;

    private const TEST_PID_BASE = 200060200;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new RookieOptionRepository($this->db);
        $this->commonRepository = new PlayerLookupRepository($this->db);
    }

    public function testRound1OptionSetsSalaryYr4(): void
    {
        $pid = self::TEST_PID_BASE + 1;
        $this->insertTestPlayer($pid, 'Round1 Rookie', [
            'teamid' => 1,
            'draftround' => 1,
            'exp' => 2,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 200,
            'salary_yr2' => 250,
            'salary_yr3' => 300,
            'salary_yr4' => 0,
        ]);

        $result = $this->repository->updatePlayerRookieOption($pid, 1, 400);

        $this->assertTrue($result);

        $player = $this->commonRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(400, $player['salary_yr4']);
        $this->assertSame(200, $player['salary_yr1']);
        $this->assertSame(250, $player['salary_yr2']);
        $this->assertSame(300, $player['salary_yr3']);
    }

    public function testRound2OptionSetsSalaryYr3(): void
    {
        $pid = self::TEST_PID_BASE + 2;
        $this->insertTestPlayer($pid, 'Round2 Rookie', [
            'teamid' => 1,
            'draftround' => 2,
            'exp' => 1,
            'cy' => 1,
            'cyt' => 2,
            'salary_yr1' => 100,
            'salary_yr2' => 150,
            'salary_yr3' => 0,
        ]);

        $result = $this->repository->updatePlayerRookieOption($pid, 2, 200);

        $this->assertTrue($result);

        $player = $this->commonRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(200, $player['salary_yr3']);
        $this->assertSame(100, $player['salary_yr1']);
        $this->assertSame(150, $player['salary_yr2']);
    }

    public function testOptionDoesNotAffectOtherSalaryFields(): void
    {
        $pid = self::TEST_PID_BASE + 3;
        $this->insertTestPlayer($pid, 'Salary Isolation', [
            'teamid' => 1,
            'draftround' => 1,
            'exp' => 3,
            'cy' => 1,
            'cyt' => 3,
            'salary_yr1' => 300,
            'salary_yr2' => 350,
            'salary_yr3' => 400,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ]);

        $this->repository->updatePlayerRookieOption($pid, 1, 500);

        $player = $this->commonRepository->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(500, $player['salary_yr4']);
        $this->assertSame(300, $player['salary_yr1']);
        $this->assertSame(350, $player['salary_yr2']);
        $this->assertSame(400, $player['salary_yr3']);
        $this->assertSame(0, $player['salary_yr5']);
        $this->assertSame(0, $player['salary_yr6']);
    }

    public function testOptionForNonExistentPlayerReturnsFalse(): void
    {
        $result = $this->repository->updatePlayerRookieOption(999999999, 1, 400);

        $this->assertFalse($result);
    }
}
