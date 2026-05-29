<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\FreeAgency;

use FreeAgency\FreeAgencyAdminRepository;
use FreeAgency\FreeAgencyRepository;
use PHPUnit\Framework\Attributes\Group;
use Repositories\PlayerLookupRepository;
use Repositories\TeamIdentityRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class FreeAgencyRepositoryIntegrationTest extends DatabaseTestCase
{
    private FreeAgencyRepository $repository;
    private FreeAgencyAdminRepository $adminRepository;
    private PlayerLookupRepository $playerRepo;
    private TeamIdentityRepository $teamRepo;

    private const TEST_PID_BASE = 200060400;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FreeAgencyRepository($this->db);
        $this->adminRepository = new FreeAgencyAdminRepository($this->db);
        $this->playerRepo = new PlayerLookupRepository($this->db);
        $this->teamRepo = new TeamIdentityRepository($this->db);
    }

    public function testSaveOfferInsertsNewOffer(): void
    {
        $pid = self::TEST_PID_BASE + 1;
        $this->insertTestPlayer($pid, 'FA Target', ['teamid' => 0, 'cy' => 0, 'cyt' => 0, 'salary_yr1' => 0]);

        $offerData = [
            'playerName' => 'FA Target',
            'pid' => $pid,
            'teamName' => 'Metros',
            'teamid' => 1,
            'offer1' => 500,
            'offer2' => 550,
            'offer3' => 600,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.0,
            'random' => 0,
            'perceivedValue' => 1650.0,
            'mle' => 0,
            'lle' => 0,
            'offerType' => 0,
        ];

        $result = $this->repository->saveOffer($offerData);

        $this->assertTrue($result);

        $existing = $this->repository->getExistingOffer(1, $pid);
        $this->assertNotNull($existing);
        $this->assertSame(500, $existing['offer1']);
        $this->assertSame(550, $existing['offer2']);
        $this->assertSame(600, $existing['offer3']);
    }

    public function testSaveOfferReplacesExistingOffer(): void
    {
        $pid = self::TEST_PID_BASE + 2;
        $this->insertTestPlayer($pid, 'FA Replace', ['teamid' => 0, 'cy' => 0, 'cyt' => 0, 'salary_yr1' => 0]);

        $this->insertFaOfferRow($pid, 1, 'FA Replace', 'Metros', [
            'offer1' => 400,
            'offer2' => 0,
        ]);

        $newOfferData = [
            'playerName' => 'FA Replace',
            'pid' => $pid,
            'teamName' => 'Metros',
            'teamid' => 1,
            'offer1' => 700,
            'offer2' => 750,
            'offer3' => 800,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'modifier' => 1.2,
            'random' => 1,
            'perceivedValue' => 2250.0,
            'mle' => 0,
            'lle' => 0,
            'offerType' => 0,
        ];

        $result = $this->repository->saveOffer($newOfferData);

        $this->assertTrue($result);

        $existing = $this->repository->getExistingOffer(1, $pid);
        $this->assertNotNull($existing);
        $this->assertSame(700, $existing['offer1']);
        $this->assertSame(750, $existing['offer2']);
        $this->assertSame(800, $existing['offer3']);
    }

    public function testExecuteSigningsTransactionallyUpdatesPlayerContracts(): void
    {
        $pid1 = self::TEST_PID_BASE + 3;
        $pid2 = self::TEST_PID_BASE + 4;
        $this->insertTestPlayer($pid1, 'FA Signing 1', [
            'teamid' => 0, 'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);
        $this->insertTestPlayer($pid2, 'FA Signing 2', [
            'teamid' => 0, 'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);

        $signings = [
            [
                'playerId' => $pid1,
                'teamId' => 1,
                'teamName' => 'Metros',
                'offerYears' => 3,
                'offers' => [
                    'offer1' => 500, 'offer2' => 550, 'offer3' => 600,
                    'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
            ],
            [
                'playerId' => $pid2,
                'teamId' => 2,
                'teamName' => 'Stars',
                'offerYears' => 2,
                'offers' => [
                    'offer1' => 300, 'offer2' => 350, 'offer3' => 0,
                    'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
                ],
                'usedMle' => false,
                'usedLle' => false,
            ],
        ];

        $result = $this->adminRepository->executeSigningsTransactionally(
            $signings,
            'FA Signings',
            'Players signed',
            'Details of signings'
        );

        $this->assertSame(3, $result['successCount']);
        $this->assertSame(0, $result['errorCount']);

        $player1 = $this->playerRepo->getPlayerByID($pid1);
        $this->assertNotNull($player1);
        $this->assertSame(1, $player1['teamid']);
        $this->assertSame(3, $player1['cyt']);
        $this->assertSame(500, $player1['salary_yr1']);
        $this->assertSame(550, $player1['salary_yr2']);
        $this->assertSame(600, $player1['salary_yr3']);
        $stmt = $this->db->prepare('SELECT fa_signing_flag FROM ibl_plr WHERE pid = ?');
        $this->assertNotFalse($stmt);
        $stmt->bind_param('i', $pid1);
        $stmt->execute();
        $flagRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $this->assertNotNull($flagRow);
        $this->assertSame(1, $flagRow['fa_signing_flag']);

        $player2 = $this->playerRepo->getPlayerByID($pid2);
        $this->assertNotNull($player2);
        $this->assertSame(2, $player2['teamid']);
        $this->assertSame(2, $player2['cyt']);
        $this->assertSame(300, $player2['salary_yr1']);
    }

    public function testExecuteSigningsWithMleMarksTeamFlag(): void
    {
        $pid = self::TEST_PID_BASE + 5;
        $this->insertTestPlayer($pid, 'MLE Signing', [
            'teamid' => 0, 'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);

        $this->setTeamMle('Metros', 1);

        $signings = [
            [
                'playerId' => $pid,
                'teamId' => 1,
                'teamName' => 'Metros',
                'offerYears' => 1,
                'offers' => [
                    'offer1' => 450, 'offer2' => 0, 'offer3' => 0,
                    'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
                ],
                'usedMle' => true,
                'usedLle' => false,
            ],
        ];

        $this->adminRepository->executeSigningsTransactionally(
            $signings,
            'MLE Signing',
            'MLE used',
            'Details'
        );

        $team = $this->teamRepo->getTeamByName('Metros');
        $this->assertNotNull($team);
        $this->assertSame(0, $team['has_mle']);
    }

    private function setTeamMle(string $teamName, int $value): void
    {
        $stmt = $this->db->prepare("UPDATE ibl_team_info SET has_mle = ? WHERE team_name = ?");
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $value, $teamName);
        $stmt->execute();
        $stmt->close();
    }
}
