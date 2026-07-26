<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\FreeAgencyContractOffersSectionView;
use FreeAgency\FreeAgencyOtherFreeAgentsSectionView;
use FreeAgency\FreeAgencyTableRendererView;
use FreeAgency\FreeAgencyTeamFreeAgentsSectionView;
use FreeAgency\FreeAgencyUnderContractSectionView;
use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Season\Season;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * @covers \FreeAgency\FreeAgencyUnderContractSectionView
 * @covers \FreeAgency\FreeAgencyContractOffersSectionView
 * @covers \FreeAgency\FreeAgencyTeamFreeAgentsSectionView
 * @covers \FreeAgency\FreeAgencyOtherFreeAgentsSectionView
 */
class FreeAgencySectionViewTest extends TestCase
{
    private MockDatabase $mockDb;
    /** @var FreeAgencyTableRendererInterface&\PHPUnit\Framework\MockObject\Stub */
    private FreeAgencyTableRendererInterface $stubRenderer;
    private Team $team;
    private Season $season;

    protected function setUp(): void
    {
        $this->mockDb      = new MockDatabase();
        $this->stubRenderer = self::createStub(FreeAgencyTableRendererInterface::class);
        $this->stubRenderer->method('renderColgroups')->willReturn('');
        $this->stubRenderer->method('renderTableHeader')->willReturn('');
        $this->stubRenderer->method('renderTeamCell')->willReturn('<td>FA</td>');
        $this->stubRenderer->method('renderPlayerRatings')->willReturn('');
        $this->stubRenderer->method('renderPlayerPreferences')->willReturn('');
        $this->stubRenderer->method('renderPlayerDemands')->willReturn('');
        $this->stubRenderer->method('renderCapSpaceFooter')->willReturn('');

        $this->mockDb->setMockData([TestDataFactory::createTeam()]);
        $this->team = Team::initialize($this->mockDb, TestDataFactory::createTeam());

        $this->mockDb->setMockData([TestDataFactory::createSeason()]);
        $this->season = new Season($this->mockDb);
    }

    private function emptyCapMetrics(): array
    {
        return [
            'totalSalaries' => [0 => 0],
            'softCapSpace'  => [0 => 5000],
            'hardCapSpace'  => [0 => 7000],
            'rosterSpots'   => [0 => 15],
        ];
    }

    // ── Empty-collection branch (absent from every golden) ───────────────────

    public function testUnderContractSectionRendersEmptyCollectionWithoutError(): void
    {
        $section = new FreeAgencyUnderContractSectionView($this->stubRenderer);
        $html    = $section->render($this->team, $this->season, $this->emptyCapMetrics(), [], []);

        $this->assertIsString($html);
    }

    public function testContractOffersSectionRendersEmptyCollectionWithoutError(): void
    {
        $section = new FreeAgencyContractOffersSectionView($this->stubRenderer);
        $html    = $section->render($this->team, $this->season, $this->emptyCapMetrics(), []);

        $this->assertIsString($html);
    }

    public function testTeamFreeAgentsSectionRendersEmptyCollectionWithoutError(): void
    {
        $section = new FreeAgencyTeamFreeAgentsSectionView($this->stubRenderer);
        $html    = $section->render($this->team, $this->season, $this->emptyCapMetrics(), []);

        $this->assertIsString($html);
    }

    public function testOtherFreeAgentsSectionRendersEmptyCollectionWithoutError(): void
    {
        $section = new FreeAgencyOtherFreeAgentsSectionView($this->stubRenderer);
        $html    = $section->render($this->team, $this->season, [], []);

        $this->assertIsString($html);
    }

    // ── Section delegates to injected renderer (seam is live) ────────────────

    public function testUnderContractSectionDelegatesToInjectedTableRenderer(): void
    {
        $mockRenderer = $this->createMock(FreeAgencyTableRendererInterface::class);
        $mockRenderer->expects($this->atLeastOnce())
            ->method('renderColgroups')
            ->willReturn('');
        $mockRenderer->method('renderTableHeader')->willReturn('');
        $mockRenderer->method('renderCapSpaceFooter')->willReturn('');

        $section = new FreeAgencyUnderContractSectionView($mockRenderer);
        $section->render($this->team, $this->season, $this->emptyCapMetrics(), [], []);
    }

    public function testOtherFreeAgentsSectionWithMissingTeamColorsFallsBack(): void
    {
        $stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $stubRepo->method('getTeamnameFromTeamID')->willReturn('Test Team');
        $renderer = new FreeAgencyTableRendererView($stubRepo);

        // cy=3, salary_yr4=0 (default) → isPlayerFreeAgent() returns true
        $this->mockDb->setMockData([TestDataFactory::createPlayer(['pid' => 1, 'teamid' => 7, 'cy' => 3])]);
        $player = \Player\Player::withPlayerID($this->mockDb, 1);

        $section = new FreeAgencyOtherFreeAgentsSectionView($renderer);
        // teamColorsByTeamId is empty — teamid 7 has no color entry → falls back to defaults
        $html = $section->render($this->team, $this->season, [$player], []);

        $this->assertIsString($html);
        $this->assertStringContainsString('<td', $html);
    }
}
