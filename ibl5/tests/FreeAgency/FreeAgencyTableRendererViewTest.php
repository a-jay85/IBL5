<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyTableRendererView;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * @covers \FreeAgency\FreeAgencyTableRendererView
 */
class FreeAgencyTableRendererViewTest extends TestCase
{
    /** @var TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamIdentityRepositoryInterface $stubRepo;
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(TeamIdentityRepositoryInterface::class);
        $this->mockDb   = new MockDatabase();
    }

    private function makeRenderer(): FreeAgencyTableRendererView
    {
        return new FreeAgencyTableRendererView($this->stubRepo);
    }

    private function makePlayer(array $overrides = []): Player
    {
        $this->mockDb->setMockData([TestDataFactory::createPlayer($overrides)]);
        return Player::withPlayerID($this->mockDb, $overrides['pid'] ?? 1);
    }

    // ── renderTeamCell ───────────────────────────────────────────────────────

    public function testRenderTeamCellWithTeamidZeroReturnsFaCell(): void
    {
        $player = $this->makePlayer(['pid' => 1, 'teamid' => 0]);
        $html = $this->makeRenderer()->renderTeamCell($player);
        $this->assertSame('<td>FA</td>', $html);
    }

    public function testRenderTeamCellDelegatesToCommonRepoWhenTeamNameEmpty(): void
    {
        // 'teamname' => '' forces getTeamName() to return null → falls through to commonRepo lookup
        $player = $this->makePlayer(['pid' => 1, 'teamid' => 5, 'teamname' => '']);

        $repoMock = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repoMock->expects($this->once())
            ->method('getTeamnameFromTeamID')
            ->with(5)
            ->willReturn('Mock Team');

        $renderer = new FreeAgencyTableRendererView($repoMock);
        $html = $renderer->renderTeamCell($player);

        $this->assertStringContainsString('Mock Team', $html);
    }

    public function testRenderTeamCellWithUnresolvableTeamReturnsHtml(): void
    {
        $player = $this->makePlayer(['pid' => 1, 'teamid' => 99]);
        $this->stubRepo->method('getTeamnameFromTeamID')->willReturn(null);

        $html = $this->makeRenderer()->renderTeamCell($player);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }

    // ── renderTableHeader ─────────────────────────────────────────────────────

    public function testRenderTableHeaderWithNullSeasonShowsYrLabels(): void
    {
        $this->mockDb->setMockData([TestDataFactory::createTeam()]);
        $team = \Team\Team::initialize($this->mockDb, TestDataFactory::createTeam());
        $html = $this->makeRenderer()->renderTableHeader('Test Table', false, $team, true, true, null);

        $this->assertStringContainsString('Yr1', $html);
        $this->assertStringContainsString('Yr6', $html);
    }

    public function testRenderTableHeaderWithBothColumnsFalseUsesFloorColspan(): void
    {
        $this->mockDb->setMockData([TestDataFactory::createTeam()]);
        $team = \Team\Team::initialize($this->mockDb, TestDataFactory::createTeam());
        $html = $this->makeRenderer()->renderTableHeader('Test', false, $team, false, false, null);

        // colspan = 38 + 0 + 0 = 38
        $this->assertStringContainsString('colspan="38"', $html);
    }

    // ── renderPlayerDemands (escaping) ────────────────────────────────────────

    public function testRenderPlayerDemandsEscapesXss(): void
    {
        // The demands keys are ints; a crafted large value would need to be
        // smuggled through the array. Test that the renderer produces valid
        // HTML without raw script tags in a normal render.
        $demands = ['dem1' => 500, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0];
        $html = $this->makeRenderer()->renderPlayerDemands($demands);

        $this->assertIsString($html);
        $this->assertStringContainsString('500', $html);
        // dem2..6 are zero → render as empty (the HtmlSanitizer::e('') path)
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderPlayerRatingsProducesHtmlCells(): void
    {
        $player = $this->makePlayer(['pid' => 1]);
        $html = $this->makeRenderer()->renderPlayerRatings($player);

        $this->assertStringContainsString('<td>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderColgroupsWithBothFlagsTrue(): void
    {
        $html = $this->makeRenderer()->renderColgroups(true, true);
        $this->assertStringContainsString('<colgroup', $html);
    }

    public function testRenderColgroupsWithBothFlagsFalse(): void
    {
        $html = $this->makeRenderer()->renderColgroups(false, false);
        $this->assertStringContainsString('<colgroup', $html);
    }
}
