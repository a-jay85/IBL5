<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\FreeAgencyService;
use FreeAgency\FreeAgencyView;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \FreeAgency\FreeAgencyView
 */
class FreeAgencyViewTest extends TestCase
{
    /** @var FreeAgencyRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FreeAgencyRepositoryInterface $stubRepo;
    /** @var FreeAgencyDemandRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private FreeAgencyDemandRepositoryInterface $stubDemandRepo;
    /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamIdentityRepositoryInterface $stubCommonRepo;
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(FreeAgencyRepositoryInterface::class);
        $this->stubRepo->method('getAllPlayersExcludingTeam')->willReturn([]);
        $this->stubDemandRepo = self::createStub(FreeAgencyDemandRepositoryInterface::class);
        $this->stubCommonRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->mockDb = new MockDatabase();
    }

    /**
     * Characterization across the service→view seam. The contract-action
     * decision (rookie option vs none) moves from the View into the Service,
     * but the rendered Players-Under-Contract table must stay byte-identical.
     * The golden was captured before the move; it pipes
     * getMainPageData() → render() so the internal data-shape change is
     * invisible to the test.
     *
     * Only the rookie-option and no-action arms are exercised here: an
     * extension-eligible player is necessarily a free agent (its next
     * contract year is empty), so it is partitioned into the unsigned-FA
     * list and never reaches this table. The preserved 'extension' arm is
     * locked separately in {@see testExtensionActionArmRendersNegotiateLink}.
     */
    public function testUnderContractTableGoldenUnchanged(): void
    {
        $rookie = $this->getBasePlayerData();
        $rookie['pid'] = 101;
        $rookie['name'] = 'Rookie Optionee';
        $rookie['cy'] = 1;
        $rookie['draftround'] = 1;
        $rookie['exp'] = 3;
        $rookie['salary_yr1'] = 500;
        $rookie['salary_yr2'] = 510;
        $rookie['salary_yr3'] = 520;
        $rookie['salary_yr4'] = 0;
        $rookie['salary_yr5'] = 0;
        $rookie['salary_yr6'] = 0;

        $neither = $this->getBasePlayerData();
        $neither['pid'] = 102;
        $neither['name'] = 'Plain Contract';
        $neither['cy'] = 2;
        $neither['draftround'] = 0;
        $neither['exp'] = 5;
        $neither['salary_yr1'] = 700;
        $neither['salary_yr2'] = 720;
        $neither['salary_yr3'] = 740;
        $neither['salary_yr4'] = 0;
        $neither['salary_yr5'] = 0;
        $neither['salary_yr6'] = 0;

        $mainPageData = $this->buildMainPageData([$rookie, $neither]);
        $view = new FreeAgencyView($this->stubCommonRepo);
        $html = $view->render($mainPageData);

        $goldenPath = __DIR__ . '/fixtures/fa-under-contract.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    /**
     * Lock the preserved 'extension' arm. It is structurally unreachable
     * through the Service partition (see {@see testUnderContractTableGoldenUnchanged}),
     * so we drive the View directly with a synthesized entry to prove the arm
     * still renders the negotiate link + "Contract Extension" label byte-for-byte
     * with the pre-extraction markup.
     */
    public function testExtensionActionArmRendersNegotiateLink(): void
    {
        // A fully-contracted player (non-empty next contract year) so the
        // View's free-agent guard renders the row. The Player comes from the
        // real Service; we then override its contractAction to 'extension' —
        // the Service never emits that for a contracted player (see class
        // doc), so this is the only way to exercise the preserved View arm.
        $row = $this->getBasePlayerData();
        $row['pid'] = 201;
        $row['name'] = 'Extension Candidate';
        $row['cy'] = 3;
        $row['salary_yr1'] = 600;
        $row['salary_yr2'] = 600;
        $row['salary_yr3'] = 600;
        $row['salary_yr4'] = 600;
        $row['salary_yr5'] = 600;
        $row['salary_yr6'] = 600;

        $mainPageData = $this->buildMainPageData([$row]);
        $this->assertCount(1, $mainPageData['playersUnderContract']);
        $player = $mainPageData['playersUnderContract'][0]['player'];
        $mainPageData['playersUnderContract'] = [
            ['player' => $player, 'contractAction' => 'extension'],
        ];

        $view = new FreeAgencyView($this->stubCommonRepo);
        $html = $view->render($mainPageData);

        $this->assertStringContainsString(
            '<a href="modules.php?name=Player&amp;pa=negotiate&amp;pid=201" class="contract-hint-link" data-no-abbreviate>Contract Extension</a>',
            $html
        );
        $this->assertStringNotContainsString('pa=rookieoption', $html);
    }

    /**
     * Build a getMainPageData() result from raw player rows, exercising the
     * real Service partition + contract-action computation.
     *
     * @param list<array<string, mixed>> $rosterRows
     * @return array{capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}, team: Team, season: \Season\Season, allOtherPlayers: list<Player>, teamColorsByTeamId: array<int, array{color1: string, color2: string}>, playersUnderContract: list<array{player: Player, contractAction: 'rookie_option'|'extension'|null}>, unsignedFreeAgents: list<Player>, offerPlayers: list<array{player: Player, offer: array<string, int>}>, cashPlayers: list<array{player: Player, label: string}>}
     */
    private function buildMainPageData(array $rosterRows): array
    {
        $teamQueryRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $teamQueryRepo->method('getRosterUnderContractOrderedByOrdinal')->willReturn($rosterRows);
        $teamQueryRepo->method('getFreeAgencyOffers')->willReturn([]);

        $service = new FreeAgencyService(
            $this->stubRepo,
            $this->stubDemandRepo,
            $this->mockDb,
            $teamQueryRepo
        );

        $team = self::createStub(Team::class);
        $team->name = 'Test Team';
        $team->teamid = 1;
        $team->color1 = '112233';
        $team->color2 = '445566';

        $season = self::createStub(\Season\Season::class);
        $season->phase = 'Preseason';
        $season->endingYear = 2026;

        return $service->getMainPageData($team, $season);
    }

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
