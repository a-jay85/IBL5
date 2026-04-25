<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use FreeAgency\FreeAgencyAdminProcessor;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

class FreeAgencyAdminProcessorTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    // ============================================
    // executeSignings() — delegates to repository
    // ============================================

    public function testExecuteSigningsAllSucceed(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 600, 0, 0, 0, 0, 2, false, false),
        ];

        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('executeSigningsTransactionally')
            ->with($signings, 'FA Day 1', 'Home text', 'Body text')
            ->willReturn(['successCount' => 3, 'errorCount' => 0]);

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', 'Home text', 'Body text');

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['successCount']);
        $this->assertSame(0, $result['errorCount']);
    }

    public function testExecuteSigningsPartialFailure(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 600, 0, 0, 0, 0, 2, false, false),
        ];

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('executeSigningsTransactionally')
            ->willReturn(['successCount' => 1, 'errorCount' => 1]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', 'Home text', 'Body text');

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['errorCount']);
    }

    public function testExecuteSigningsDelegatesEmptyNewsText(): void
    {
        $signings = [
            $this->makeSigning(1, 10, 'Miami', 500, 0, 0, 0, 0, 0, 1, false, false),
        ];

        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())
            ->method('executeSigningsTransactionally')
            ->with($signings, 'FA Day 1', '', '')
            ->willReturn(['successCount' => 1, 'errorCount' => 0]);

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->executeSignings(1, $signings, 'FA Day 1', '', '');

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['successCount']);
    }

    public function testExecuteSigningsNoOperations(): void
    {
        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);

        $result = $processor->executeSignings(1, [], 'FA Day 1', '', '');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['successCount']);
        $this->assertStringContainsString('No operations', $result['message']);
    }

    // ============================================
    // clearOffers()
    // ============================================

    public function testClearOffersReturnsSuccess(): void
    {
        $mock = $this->createMock(FreeAgencyAdminRepositoryInterface::class);
        $mock->expects($this->once())->method('clearAllOffers');

        $processor = new FreeAgencyAdminProcessor($mock, $this->mockDb);
        $result = $processor->clearOffers();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('cleared', $result['message']);
    }

    // ============================================
    // processDay() — empty offers
    // ============================================

    public function testProcessDayEmptyOffersReturnsEmptyResults(): void
    {
        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([]);
        $stub->method('getPlayerDemandsBatch')->willReturn([]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertSame([], $result['signings']);
        $this->assertSame([], $result['rejections']);
        $this->assertSame([], $result['autoRejections']);
        $this->assertSame([], $result['allOffers']);
        $this->assertSame('', $result['newsHomeText']);
        $this->assertSame('', $result['newsBodyText']);
        $this->assertSame('', $result['discordText']);
    }

    // ============================================
    // processDay() — auto-rejection (perceived value <= demands/2)
    // ============================================

    public function testProcessDayAutoRejectsLowOffers(): void
    {
        $offer = $this->makeOfferRow('Player A', 100, 'Miami', 1, 200, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        // Demands: dem1=1000, rest 0 → total=1000, years=1
        // day 1: demands = (1000/1)*((11-1)/10) = 1000
        // perceived value 1.0 <= 1000/2 = 500 → auto-reject
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 1000, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['autoRejections']);
        $this->assertSame('Player A', $result['autoRejections'][0]['playerName']);
        $this->assertCount(0, $result['signings']);
        $this->assertCount(0, $result['rejections']);
    }


    // ============================================
    // processDay() — successful signing (perceived value > demands)
    // ============================================

    public function testProcessDaySuccessfulSigning(): void
    {
        // Offer: 3yr deal, offer avg = (500+550+600)/3 = 550
        // Demands: dem1=200, dem2=200, dem3=200 → total=600, years=3
        // Day 1: demandValue = (600/3) * ((11-1)/10) = 200 * 1.0 = 200.0
        // perceivedValue 800.0 > 200.0 → signing
        $offer = $this->makeOfferRow('Star Player', 100, 'Miami', 1, 500, 550, 600, 0, 0, 0, 0, 0, 0, 0, 800.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 200, 'dem2' => 200, 'dem3' => 200, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['signings']);
        $this->assertSame('Star Player', $result['signings'][0]['playerName']);
        $this->assertSame(100, $result['signings'][0]['playerId']);
        $this->assertSame('Miami', $result['signings'][0]['teamName']);
        $this->assertSame(3, $result['signings'][0]['offerYears']);
        $this->assertSame(16.5, $result['signings'][0]['offerTotal']);
        $this->assertFalse($result['signings'][0]['usedMle']);
        $this->assertFalse($result['signings'][0]['usedLle']);
        $this->assertCount(0, $result['rejections']);
        $this->assertCount(0, $result['autoRejections']);
        $this->assertStringContainsString('accepts', $result['newsHomeText']);
    }

    // ============================================
    // processDay() — rejection (demands/2 < perceived value <= demands)
    // ============================================

    public function testProcessDayRejectedOffer(): void
    {
        // Demands: dem1=200 → total=200, years=1
        // Day 1: demandValue = (200/1) * 1.0 = 200.0
        // perceivedValue 150.0: auto-reject threshold = 200/2 = 100
        // 150 > 100 → not auto-rejected; 150 <= 200 → rejected
        $offer = $this->makeOfferRow('Player B', 200, 'Chicago', 2, 400, 0, 0, 0, 0, 0, 0, 0, 0, 0, 150.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            200 => ['dem1' => 200, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['rejections']);
        $this->assertSame('Player B', $result['rejections'][0]['playerName']);
        $this->assertSame('Best offer did not meet player demands', $result['rejections'][0]['reason']);
        $this->assertCount(0, $result['signings']);
        $this->assertCount(0, $result['autoRejections']);
    }

    // ============================================
    // processDay() — multiple offers for same player
    // ============================================

    public function testProcessDayMultipleOffersSamePlayer(): void
    {
        // Two offers for same player (highest perceived value first, per ORDER BY)
        // Only first should trigger signing decision
        $offer1 = $this->makeOfferRow('Star Player', 100, 'Miami', 1, 500, 550, 600, 0, 0, 0, 0, 0, 0, 0, 800.0);
        $offer2 = $this->makeOfferRow('Star Player', 100, 'Chicago', 2, 400, 450, 500, 0, 0, 0, 0, 0, 0, 0, 600.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer1, $offer2]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 200, 'dem2' => 200, 'dem3' => 200, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['signings']);
        $this->assertSame('Miami', $result['signings'][0]['teamName']);
        $this->assertCount(2, $result['allOffers']);
        $this->assertCount(0, $result['rejections']);
    }

    // ============================================
    // processDay() — Discord text: offer sorting and acceptance/rejection placement
    // ============================================

    public function testProcessDayDiscordOffersAlphabeticalWithAcceptanceAtEnd(): void
    {
        // Three offers for same player, ordered by perceived value descending (repo ORDER BY).
        // Miami wins (highest). Alphabetical order: Boston < Chicago < Miami.
        $offerMiami = $this->makeOfferRow('Star Player', 100, 'Miami', 1, 500, 550, 600, 0, 0, 0, 0, 0, 0, 0, 800.0);
        $offerChicago = $this->makeOfferRow('Star Player', 100, 'Chicago', 2, 400, 450, 500, 0, 0, 0, 0, 0, 0, 0, 600.0);
        $offerBoston = $this->makeOfferRow('Star Player', 100, 'Boston', 3, 300, 350, 400, 0, 0, 0, 0, 0, 0, 0, 500.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offerMiami, $offerChicago, $offerBoston]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 200, 'dem2' => 200, 'dem3' => 200, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $discord = $result['discordText'];

        // Offers sorted alphabetically by team name
        $bostonPos = strpos($discord, 'Boston - ');
        $chicagoPos = strpos($discord, 'Chicago - ');
        $miamiPos = strpos($discord, 'Miami - ');
        $this->assertNotFalse($bostonPos);
        $this->assertNotFalse($chicagoPos);
        $this->assertNotFalse($miamiPos);
        $this->assertLessThan($chicagoPos, $bostonPos, 'Boston should appear before Chicago');
        $this->assertLessThan($miamiPos, $chicagoPos, 'Chicago should appear before Miami');

        // Acceptance line appears after all offer lines
        $acceptsPos = strpos($discord, 'Star Player accepts');
        $this->assertNotFalse($acceptsPos);
        $this->assertGreaterThan($miamiPos, $acceptsPos, 'Acceptance line should appear after all offers');
    }

    public function testProcessDayDiscordOffersAlphabeticalWithRejectionAtEnd(): void
    {
        // Two offers for same player, both below demands → first is rejected (not auto-rejected).
        // Demands: dem1=800 → day 1 demandValue = 800. perceivedValue 600 > 400 threshold, ≤ 800 → rejected.
        // Alphabetical order: Chicago < Miami.
        $offerMiami = $this->makeOfferRow('Player B', 200, 'Miami', 1, 400, 0, 0, 0, 0, 0, 0, 0, 0, 0, 600.0);
        $offerChicago = $this->makeOfferRow('Player B', 200, 'Chicago', 2, 300, 0, 0, 0, 0, 0, 0, 0, 0, 0, 500.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offerMiami, $offerChicago]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            200 => ['dem1' => 800, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $discord = $result['discordText'];

        // Offers sorted alphabetically
        $chicagoPos = strpos($discord, 'Chicago - ');
        $miamiPos = strpos($discord, 'Miami - ');
        $this->assertNotFalse($chicagoPos);
        $this->assertNotFalse($miamiPos);
        $this->assertLessThan($miamiPos, $chicagoPos, 'Chicago should appear before Miami');

        // REJECTED appears after all offer lines
        $rejectedPos = strpos($discord, '**REJECTED**');
        $this->assertNotFalse($rejectedPos);
        $this->assertGreaterThan($miamiPos, $rejectedPos, 'REJECTED should appear after all offers');
    }

    // ============================================
    // processDay() — day adjustment affects demand threshold
    // ============================================

    public function testProcessDayDemandsDayAdjustment(): void
    {
        // Demands: dem1=2000 → total=2000, years=1
        // perceivedValue = 500.0
        //
        // Day 1:  demandValue = (2000/1) * ((11-1)/10) = 2000
        //         autoReject threshold = 2000/2 = 1000
        //         500.0 <= 1000 → auto-reject
        //
        // Day 10: demandValue = (2000/1) * ((11-10)/10) = 200
        //         autoReject threshold = 200/2 = 100
        //         500.0 > 100 → not auto-reject; 500 > 200 → signing
        $offer = $this->makeOfferRow('Player C', 300, 'Miami', 1, 400, 0, 0, 0, 0, 0, 0, 0, 0, 0, 500.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            300 => ['dem1' => 2000, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        // Day 1: auto-reject
        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $resultDay1 = $processor->processDay(1);
        $this->assertCount(1, $resultDay1['autoRejections']);
        $this->assertCount(0, $resultDay1['signings']);

        // Day 10: signing (demands shrink to 10%)
        $processor2 = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $resultDay10 = $processor2->processDay(10);
        $this->assertCount(1, $resultDay10['signings']);
        $this->assertCount(0, $resultDay10['autoRejections']);
    }

    // ============================================
    // processDay() — player with no demands
    // ============================================

    public function testProcessDayPlayerWithNoDemands(): void
    {
        // Player has no demands entry → calculateDemandValue(null, day) returns 0.0
        // Any perceivedValue > 0 → signing
        $offer = $this->makeOfferRow('No Demand Player', 400, 'Miami', 1, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 50.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offer]);
        $stub->method('getPlayerDemandsBatch')->willReturn([]); // empty — no demands for pid 400

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['signings']);
        $this->assertSame('No Demand Player', $result['signings'][0]['playerName']);
        $this->assertCount(0, $result['autoRejections']);
    }

    // ============================================
    // processDay() — mixed outcomes (signing + rejection + auto-reject)
    // ============================================

    public function testProcessDayMixedOutcomes(): void
    {
        // Player A: perceivedValue=800 vs demands=200 → signing
        // Player B: perceivedValue=150 vs demands=200 → rejection (150 > 100 threshold, but <= 200)
        // Player C: perceivedValue=1 vs demands=2000 → auto-reject (1 <= 1000 threshold)
        $offerA = $this->makeOfferRow('Player A', 100, 'Miami', 1, 500, 550, 600, 0, 0, 0, 0, 0, 0, 0, 800.0);
        $offerB = $this->makeOfferRow('Player B', 200, 'Chicago', 2, 400, 0, 0, 0, 0, 0, 0, 0, 0, 0, 150.0);
        $offerC = $this->makeOfferRow('Player C', 300, 'Boston', 3, 100, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1.0);

        $stub = $this->createStub(FreeAgencyAdminRepositoryInterface::class);
        $stub->method('getAllOffersWithBirdYears')->willReturn([$offerA, $offerB, $offerC]);
        $stub->method('getPlayerDemandsBatch')->willReturn([
            100 => ['dem1' => 200, 'dem2' => 200, 'dem3' => 200, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            200 => ['dem1' => 200, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            300 => ['dem1' => 2000, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
        ]);

        $this->configureMockDbForProcessDay();

        $processor = new FreeAgencyAdminProcessor($stub, $this->mockDb);
        $result = $processor->processDay(1);

        $this->assertCount(1, $result['signings']);
        $this->assertSame('Player A', $result['signings'][0]['playerName']);
        $this->assertCount(1, $result['rejections']);
        $this->assertSame('Player B', $result['rejections'][0]['playerName']);
        $this->assertCount(1, $result['autoRejections']);
        $this->assertSame('Player C', $result['autoRejections'][0]['playerName']);
        $this->assertCount(3, $result['allOffers']);
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * Configure MockDatabase with team and player rows for processDay() tests.
     * Team::initialize() and Player::withPlayerID() query the DB directly.
     */
    private function configureMockDbForProcessDay(): void
    {
        // Minimal team row for Team::initialize() — matched by ibl_team_info handler
        $teamRow = [
            'teamid' => 1,
            'team_city' => 'Test City',
            'team_name' => 'Test Team',
            'color1' => '#000000',
            'color2' => '#FFFFFF',
            'arena' => 'Test Arena',
            'capacity' => 20000,
            'owner_name' => 'Owner',
            'owner_email' => 'owner@test.com',
            'discord_id' => null,
            'used_extension_this_chunk' => 0,
            'used_extension_this_season' => 0,
            'has_mle' => 0,
            'has_lle' => 0,
            'contract_wins' => 40,
            'contract_losses' => 42,
            'contract_avg_w' => 40,
            'contract_avg_l' => 42,
            'league_record' => '40-42',
        ];
        $this->mockDb->setMockTeamData([$teamRow]);

        // Minimal player row for Player::withPlayerID() — matched by pid filter
        $playerRow = [
            'pid' => 100,
            'ordinal' => 1,
            'name' => 'Test Player',
            'nickname' => null,
            'age' => 25,
            'peak' => 28,
            'teamid' => 0,
            'pos' => 'PF',
            'stamina' => 5,
            'oo' => 50, 'od' => 50, 'r_drive_off' => 50, 'dd' => 50,
            'po' => 50, 'pd' => 50, 'r_trans_off' => 50, 'td' => 50,
            'clutch' => 3, 'consistency' => 3,
            'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 5, 'c_depth' => 0,
            'dc_pg_depth' => 0, 'dc_sg_depth' => 0, 'dc_sf_depth' => 0, 'dc_pf_depth' => 5, 'dc_c_depth' => 0,
            'dc_can_play_in_game' => 1, 'dc_minutes' => 30,
            'dc_of' => 0, 'dc_df' => 0, 'dc_oi' => 0, 'dc_di' => 0, 'dc_bh' => 0,
            'active' => 1,
            'talent' => 50, 'skill' => 50, 'intangibles' => 50, 'coach' => 0,
            'loyalty' => 3, 'playing_time' => 3, 'winner' => 3, 'tradition' => 3, 'security' => 3,
            'exp' => 5, 'bird' => 1,
            'cy' => 0, 'cyt' => 0, 'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0, 'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
            'fa_signing_flag' => 0,
            'stats_gs' => 0, 'stats_gm' => 0, 'stats_min' => 0,
            'stats_fgm' => 0, 'stats_fga' => 0, 'stats_ftm' => 0, 'stats_fta' => 0,
            'stats_3gm' => 0, 'stats_3ga' => 0,
            'stats_orb' => 0, 'stats_drb' => 0, 'stats_ast' => 0,
            'stats_stl' => 0, 'stats_tvr' => 0, 'stats_blk' => 0, 'stats_pf' => 0,
            'r_fga' => 50, 'r_fgp' => 50, 'r_fta' => 50, 'r_ftp' => 50,
            'r_3ga' => 50, 'r_3gp' => 50, 'r_orb' => 50, 'r_drb' => 50,
            'r_ast' => 50, 'r_stl' => 50, 'r_tvr' => 50, 'r_blk' => 50, 'r_foul' => 50,
            'draftround' => 1, 'draftedby' => 'MIA', 'draftedbycurrentname' => 'Miami',
            'draftyear' => 2020, 'draftpickno' => 1,
            'injured' => 0, 'htft' => 6, 'htin' => 8, 'wt' => 220,
            'retired' => 0, 'college' => 'Test University',
            'sh_pts' => 0, 'sh_reb' => 0, 'sh_ast' => 0, 'sh_stl' => 0, 'sh_blk' => 0,
            's_dd' => 0, 's_td' => 0,
            'sp_pts' => 0, 'sp_reb' => 0, 'sp_ast' => 0, 'sp_stl' => 0, 'sp_blk' => 0,
            'ch_pts' => 0, 'ch_reb' => 0, 'ch_ast' => 0, 'ch_stl' => 0, 'ch_blk' => 0,
            'c_dd' => 0, 'c_td' => 0,
            'cp_pts' => 0, 'cp_reb' => 0, 'cp_ast' => 0, 'cp_stl' => 0, 'cp_blk' => 0,
            'car_gm' => 0, 'car_min' => 0,
            'car_fgm' => 0, 'car_fga' => 0, 'car_ftm' => 0, 'car_fta' => 0,
            'car_tgm' => 0, 'car_tga' => 0,
            'car_orb' => 0, 'car_drb' => 0, 'car_reb' => 0,
            'car_ast' => 0, 'car_stl' => 0, 'car_to' => 0, 'car_blk' => 0,
            'car_pf' => 0, 'car_pts' => 0,
            'car_playoff_min' => 0, 'car_preseason_min' => 0,
            'droptime' => 0,
        ];
        // Route player queries via onQuery (highest priority) — needed because
        // PlayerRepository::loadByID() JOINs ibl_team_info, which would otherwise
        // trigger MockDatabase's built-in team handler and return team data
        $this->mockDb->onQuery('FROM ibl_plr', [$playerRow]);
    }

    /**
     * @return array{playerId: int, teamId: int, teamName: string, offers: array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int}, offerYears: int, offerTotal: float, usedMle: bool, usedLle: bool}
     */
    private function makeSigning(
        int $playerId,
        int $teamId,
        string $teamName,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        int $offerYears,
        bool $usedMle,
        bool $usedLle
    ): array {
        return [
            'playerId' => $playerId,
            'teamId' => $teamId,
            'teamName' => $teamName,
            'offers' => [
                'offer1' => $offer1,
                'offer2' => $offer2,
                'offer3' => $offer3,
                'offer4' => $offer4,
                'offer5' => $offer5,
                'offer6' => $offer6,
            ],
            'offerYears' => $offerYears,
            'offerTotal' => ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100,
            'usedMle' => $usedMle,
            'usedLle' => $usedLle,
        ];
    }

    /**
     * @return array{name: string, pid: int, team: string, teamid: int, offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, bird: int, MLE: int, LLE: int, random: int, perceivedvalue: float}
     */
    private function makeOfferRow(
        string $name,
        int $pid,
        string $team,
        int $teamid,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        int $bird,
        int $mle,
        int $lle,
        int $random,
        float $perceivedvalue
    ): array {
        return [
            'name' => $name,
            'pid' => $pid,
            'team' => $team,
            'teamid' => $teamid,
            'offer1' => $offer1,
            'offer2' => $offer2,
            'offer3' => $offer3,
            'offer4' => $offer4,
            'offer5' => $offer5,
            'offer6' => $offer6,
            'bird' => $bird,
            'mle' => $mle,
            'lle' => $lle,
            'random' => $random,
            'perceivedvalue' => $perceivedvalue,
        ];
    }
}
