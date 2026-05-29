<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\FreeAgencyService;
use League\League;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \FreeAgency\FreeAgencyService
 */
class FreeAgencyServiceTest extends TestCase
{
    /** @var FreeAgencyRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FreeAgencyRepositoryInterface $stubRepo;
    /** @var FreeAgencyDemandRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FreeAgencyDemandRepositoryInterface $stubDemandRepo;
    /** @var TeamQueryRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamQueryRepositoryInterface $stubTeamQueryRepo;
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(FreeAgencyRepositoryInterface::class);
        $this->stubDemandRepo = $this->createStub(FreeAgencyDemandRepositoryInterface::class);
        $this->stubTeamQueryRepo = $this->createStub(TeamQueryRepositoryInterface::class);
        $this->stubTeamQueryRepo->method('getRosterUnderContractOrderedByOrdinal')->willReturn([]);
        $this->stubTeamQueryRepo->method('getFreeAgencyOffers')->willReturn([]);
        $this->mockDb = new MockDatabase();
    }

    // ── getExistingOffer ─────────────────────────────────────────

    public function testGetExistingOfferReturnsZerosWhenRepoReturnsNull(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn(null);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class), $this->stubTeamQueryRepo);
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(0, $result['offer1']);
        $this->assertSame(0, $result['offer6']);
        $this->assertCount(6, $result);
    }

    public function testGetExistingOfferMapsAllSixOfferFields(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => 500,
            'offer2' => 450,
            'offer3' => 400,
            'offer4' => 350,
            'offer5' => 300,
            'offer6' => 250,
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class), $this->stubTeamQueryRepo);
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(500, $result['offer1']);
        $this->assertSame(450, $result['offer2']);
        $this->assertSame(400, $result['offer3']);
        $this->assertSame(350, $result['offer4']);
        $this->assertSame(300, $result['offer5']);
        $this->assertSame(250, $result['offer6']);
    }

    public function testGetExistingOfferCoercesNullValuesToZero(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => 500,
            'offer2' => null,
            'offer3' => null,
            'offer4' => 350,
            'offer5' => null,
            'offer6' => null,
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class), $this->stubTeamQueryRepo);
        $result = $service->getExistingOffer(1, 100);

        $this->assertSame(500, $result['offer1']);
        $this->assertSame(0, $result['offer2']);
        $this->assertSame(0, $result['offer3']);
        $this->assertSame(350, $result['offer4']);
        $this->assertSame(0, $result['offer5']);
        $this->assertSame(0, $result['offer6']);
    }

    public function testGetExistingOfferReturnsIntegers(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => '500',
            'offer2' => '0',
            'offer3' => '400',
            'offer4' => '350',
            'offer5' => '300',
            'offer6' => '250',
        ]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->createStub(\mysqli::class), $this->stubTeamQueryRepo);
        $result = $service->getExistingOffer(1, 100);

        foreach ($result as $value) {
            $this->assertIsInt($value);
        }
    }

    // ── getMainPageData ──────────────────────────────────────────

    public function testGetMainPageDataReturnsExpectedKeys(): void
    {
        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn([]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getMainPageData($team, $season);

        $this->assertArrayHasKey('capMetrics', $result);
        $this->assertArrayHasKey('team', $result);
        $this->assertArrayHasKey('season', $result);
        $this->assertArrayHasKey('allOtherPlayers', $result);
        $this->assertArrayHasKey('playersUnderContract', $result);
        $this->assertArrayHasKey('unsignedFreeAgents', $result);
        $this->assertArrayHasKey('offerPlayers', $result);
        $this->assertArrayHasKey('cashPlayers', $result);
        $this->assertArrayHasKey('teamColorsByTeamId', $result);
    }

    public function testGetMainPageDataIncludesAllOtherPlayers(): void
    {
        $testPlayers = [
            ['pid' => 1, 'name' => 'Player A'],
            ['pid' => 2, 'name' => 'Player B'],
        ];
        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn($testPlayers);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getMainPageData($team, $season);

        $this->assertCount(2, $result['allOtherPlayers']);
        $this->assertContainsOnlyInstancesOf(\Player\Player::class, $result['allOtherPlayers']);
    }

    public function testGetMainPageDataCapMetricsHasRequiredKeys(): void
    {
        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn([]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getMainPageData($team, $season);

        $capMetrics = $result['capMetrics'];
        $this->assertArrayHasKey('totalSalaries', $capMetrics);
        $this->assertArrayHasKey('softCapSpace', $capMetrics);
        $this->assertArrayHasKey('hardCapSpace', $capMetrics);
        $this->assertArrayHasKey('rosterSpots', $capMetrics);
        $this->assertCount(6, $capMetrics['totalSalaries']);
    }

    // ── getNegotiationData ───────────────────────────────────────

    public function testGetNegotiationDataReturnsExpectedKeys(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn(null);
        $this->stubDemandRepo->method('getPlayerDemands')->willReturn([
            'dem1' => 500, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0,
        ]);

        $this->mockDb->setMockData([$this->getBasePlayerData()]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getNegotiationData(1, $team, $season);

        $this->assertArrayHasKey('player', $result);
        $this->assertArrayHasKey('capMetrics', $result);
        $this->assertArrayHasKey('demands', $result);
        $this->assertArrayHasKey('existingOffer', $result);
        $this->assertArrayHasKey('amendedCapSpace', $result);
        $this->assertArrayHasKey('hasExistingOffer', $result);
        $this->assertArrayHasKey('veteranMinimum', $result);
        $this->assertArrayHasKey('maxContract', $result);
    }

    public function testGetNegotiationDataHasExistingOfferFalseWhenNoOffer(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn(null);
        $this->stubDemandRepo->method('getPlayerDemands')->willReturn([
            'dem1' => 0, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0,
        ]);

        $this->mockDb->setMockData([$this->getBasePlayerData()]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getNegotiationData(1, $team, $season);

        $this->assertFalse($result['hasExistingOffer']);
        $this->assertSame(League::SOFT_CAP_MAX, $result['amendedCapSpace']);
    }

    public function testGetNegotiationDataHasExistingOfferTrueWhenOfferExists(): void
    {
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => 500, 'offer2' => 0, 'offer3' => 0,
            'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
        ]);
        $this->stubDemandRepo->method('getPlayerDemands')->willReturn([
            'dem1' => 0, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0,
        ]);

        $this->mockDb->setMockData([$this->getBasePlayerData()]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getNegotiationData(1, $team, $season);

        $this->assertTrue($result['hasExistingOffer']);
        // calculateTeamCapMetrics() already excludes this player's offer,
        // so amendedCapSpace equals softCapSpace[0] without double-counting
        $this->assertSame(League::SOFT_CAP_MAX, $result['amendedCapSpace']);
    }

    /**
     * Regression: updating an existing offer must not inflate cap space.
     *
     * Before the fix, amendedCapSpace was softCapSpace[0] + existingOffer['offer1'].
     * Since calculateTeamCapMetrics() already excludes the player's offer,
     * adding it back double-counted — allowing offers above the soft cap.
     */
    public function testAmendedCapSpaceDoesNotInflateWhenExistingOfferPresent(): void
    {
        $existingOffer1 = 784;
        $this->stubRepo->method('getExistingOffer')->willReturn([
            'offer1' => $existingOffer1, 'offer2' => 0, 'offer3' => 0,
            'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
        ]);
        $this->stubDemandRepo->method('getPlayerDemands')->willReturn([
            'dem1' => 0, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0,
        ]);

        $this->mockDb->setMockData([$this->getBasePlayerData()]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $this->stubTeamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getNegotiationData(1, $team, $season);

        // Before fix: SOFT_CAP_MAX + 784 (inflated). After fix: SOFT_CAP_MAX (correct).
        $this->assertSame(League::SOFT_CAP_MAX, $result['amendedCapSpace']);
        $this->assertNotSame(
            League::SOFT_CAP_MAX + $existingOffer1,
            $result['amendedCapSpace'],
            'amendedCapSpace must not be inflated by the existing offer amount'
        );
    }

    // ── roster partition ────────────────────────────────────────

    public function testGetMainPageDataPartitionsRosterIntoContractedAndUnsigned(): void
    {
        $teamQueryRepo = $this->createStub(TeamQueryRepositoryInterface::class);

        $contractedPlayer = $this->getBasePlayerData();
        $contractedPlayer['pid'] = 10;
        $contractedPlayer['name'] = 'Contracted Player';
        $contractedPlayer['cy'] = 1;
        $contractedPlayer['salary_yr1'] = 500;
        $contractedPlayer['salary_yr2'] = 500;

        $freeAgentPlayer = $this->getBasePlayerData();
        $freeAgentPlayer['pid'] = 20;
        $freeAgentPlayer['name'] = 'Free Agent';
        $freeAgentPlayer['cy'] = 0;
        $freeAgentPlayer['salary_yr1'] = 0;
        $freeAgentPlayer['salary_yr2'] = 0;
        $freeAgentPlayer['salary_yr3'] = 0;
        $freeAgentPlayer['salary_yr4'] = 0;
        $freeAgentPlayer['salary_yr5'] = 0;
        $freeAgentPlayer['salary_yr6'] = 0;

        $teamQueryRepo->method('getRosterUnderContractOrderedByOrdinal')
            ->willReturn([$contractedPlayer, $freeAgentPlayer]);
        $teamQueryRepo->method('getFreeAgencyOffers')->willReturn([]);

        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn([]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $teamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);
        $season->phase = 'Free Agency';
        $season->endingYear = 2026;

        $result = $service->getMainPageData($team, $season);

        $this->assertCount(1, $result['playersUnderContract']);
        $this->assertCount(1, $result['unsignedFreeAgents']);
        $this->assertSame(10, $result['playersUnderContract'][0]->getPlayerID());
        $this->assertSame(20, $result['unsignedFreeAgents'][0]->getPlayerID());
    }

    public function testGetMainPageDataPreBuildsOfferPlayers(): void
    {
        $teamQueryRepo = $this->createStub(TeamQueryRepositoryInterface::class);
        $teamQueryRepo->method('getRosterUnderContractOrderedByOrdinal')->willReturn([]);
        $teamQueryRepo->method('getFreeAgencyOffers')->willReturn([
            ['pid' => 5, 'teamid' => 1, 'team' => 'Test', 'name' => 'Offered', 'offer1' => 400, 'offer2' => 350, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
        ]);

        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn([]);

        $this->mockDb->setMockData([$this->getBasePlayerData()]);

        $service = new FreeAgencyService($this->stubRepo, $this->stubDemandRepo, $this->mockDb, $teamQueryRepo);

        $team = $this->createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;

        $season = $this->createStub(\Season\Season::class);

        $result = $service->getMainPageData($team, $season);

        $this->assertCount(1, $result['offerPlayers']);
        $this->assertSame(400, $result['offerPlayers'][0]['offer']['offer1']);
        $this->assertSame(350, $result['offerPlayers'][0]['offer']['offer2']);
        $this->assertInstanceOf(\Player\Player::class, $result['offerPlayers'][0]['player']);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function getBasePlayerData(): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'firstname' => 'Test',
            'lastname' => 'Player',
            'nickname' => '',
            'teamname' => 'Test Team',
            'teamid' => 1,
            'pos' => 'G',
            'position' => 'G',
            'age' => 25,
            'ordinal' => 1,
            'height' => 75,
            'weight' => 200,
            'htft' => 6,
            'htin' => 3,
            'wt' => 200,
            'cy' => 0,
            'cyt' => 0,
            'salary_yr1' => 0,
            'salary_yr2' => 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
            'exp' => 3,
            'bird' => 0,
            'bird_years' => 0,
            'retired' => 0,
            'injured' => 0,
            'signed' => 0,
            'droptime' => 0,
            'fa_loyalty' => 50,
            'fa_playing_time' => 50,
            'fa_play_for_winner' => 50,
            'fa_tradition' => 50,
            'fa_security' => 50,
            'loyalty' => 50,
            'playing_time' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_3ga' => 50,
            'r_3gp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_tvr' => 50,
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
            'oo' => 50,
            'od' => 50,
            'r_drive_off' => 50,
            'dd' => 50,
            'po' => 50,
            'pd' => 50,
            'r_trans_off' => 50,
            'td' => 50,
            'clutch' => 50,
            'consistency' => 50,
            'talent' => 50,
            'skill' => 50,
            'intangibles' => 50,
            'ovr' => 75,
            'draftyear' => 2020,
            'draftround' => 1,
            'draftpickno' => 10,
            'draftedby' => 'Test Team',
            'draftedbycurrentname' => 'Test Team',
            'college' => 'Test University',
        ];
    }
}
