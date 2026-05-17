<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Extension;

use Extension\ExtensionRepository;
use PHPUnit\Framework\Attributes\Group;
use Repositories\PlayerLookupRepository;
use Repositories\TeamIdentityRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class ExtensionRepositoryIntegrationTest extends DatabaseTestCase
{
    private ExtensionRepository $repository;
    private PlayerLookupRepository $playerRepo;
    private TeamIdentityRepository $teamRepo;

    private const TEST_PID_BASE = 200060300;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ExtensionRepository($this->db);
        $this->playerRepo = new PlayerLookupRepository($this->db);
        $this->teamRepo = new TeamIdentityRepository($this->db);
    }

    public function testSaveAcceptedExtensionUpdatesPlayerContract(): void
    {
        $pid = self::TEST_PID_BASE + 1;
        $playerName = 'Extension Accept';
        $this->insertTestPlayer($pid, $playerName, [
            'teamid' => 1,
            'cy' => 2,
            'cyt' => 3,
            'salary_yr1' => 500,
            'salary_yr2' => 600,
            'salary_yr3' => 700,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'exp' => 5,
            'bird' => 3,
        ]);

        $offer = [
            'year1' => 800,
            'year2' => 850,
            'year3' => 900,
            'year4' => 0,
            'year5' => 0,
        ];
        $currentSalary = 700;

        $this->repository->saveAcceptedExtension(
            $playerName,
            'Metros',
            $offer,
            $currentSalary,
            2.55,
            4,
            '800 850 900'
        );

        $player = $this->playerRepo->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(1, $player['cy']);
        $this->assertSame(4, $player['cyt']);
        $this->assertSame($currentSalary, $player['salary_yr1']);
        $this->assertSame(800, $player['salary_yr2']);
        $this->assertSame(850, $player['salary_yr3']);
        $this->assertSame(900, $player['salary_yr4']);
        $this->assertSame(0, $player['salary_yr5']);
        $this->assertSame(0, $player['salary_yr6']);
    }

    public function testSaveAcceptedExtensionMarksTeamFlags(): void
    {
        $pid = self::TEST_PID_BASE + 2;
        $playerName = 'Extension Flags';
        $this->insertTestPlayer($pid, $playerName, ['teamid' => 1]);

        $offer = [
            'year1' => 800,
            'year2' => 850,
            'year3' => 900,
            'year4' => 0,
            'year5' => 0,
        ];

        $this->repository->saveAcceptedExtension(
            $playerName,
            'Metros',
            $offer,
            500,
            2.55,
            4,
            '800 850 900'
        );

        $team = $this->teamRepo->getTeamByName('Metros');
        $this->assertNotNull($team);
        $this->assertSame(1, $team['used_extension_this_season']);
    }

    public function testMarkExtensionUsedThisSimSetsChunkFlag(): void
    {
        $teamBefore = $this->teamRepo->getTeamByName('Metros');
        $this->assertNotNull($teamBefore);
        $this->assertSame(0, $teamBefore['used_extension_this_chunk']);

        $this->repository->markExtensionUsedThisSim('Metros');

        $teamAfter = $this->teamRepo->getTeamByName('Metros');
        $this->assertNotNull($teamAfter);
        $this->assertSame(1, $teamAfter['used_extension_this_chunk']);
    }

    public function testUpdatePlayerContractSetsCorrectYears(): void
    {
        $pid = self::TEST_PID_BASE + 3;
        $playerName = 'Five Year Ext';
        $this->insertTestPlayer($pid, $playerName, [
            'teamid' => 1,
            'cy' => 3,
            'cyt' => 3,
            'salary_yr1' => 400,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'exp' => 7,
        ]);

        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700,
        ];

        $this->repository->updatePlayerContract($playerName, $offer, 400);

        $player = $this->playerRepo->getPlayerByID($pid);
        $this->assertNotNull($player);
        $this->assertSame(1, $player['cy']);
        $this->assertSame(6, $player['cyt']);
        $this->assertSame(400, $player['salary_yr1']);
        $this->assertSame(500, $player['salary_yr2']);
        $this->assertSame(550, $player['salary_yr3']);
        $this->assertSame(600, $player['salary_yr4']);
        $this->assertSame(650, $player['salary_yr5']);
        $this->assertSame(700, $player['salary_yr6']);
    }
}
