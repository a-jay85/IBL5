<?php

declare(strict_types=1);

namespace Tests\DraftHistory;

use DraftHistory\Contracts\DraftHistoryViewInterface;
use DraftHistory\DraftHistoryView;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DraftHistory\DraftHistoryView
 */
#[AllowMockObjectsWithoutExpectations]
class DraftHistoryViewTest extends TestCase
{
    private DraftHistoryView $view;

    protected function setUp(): void
    {
        $this->view = new DraftHistoryView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(DraftHistoryViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $html = $this->view->render(2024, 1988, 2024, [self::createYearPick()]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsYearDropdown(): void
    {
        $html = $this->view->render(2024, 2022, 2024, []);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('2024', $html);
        $this->assertStringContainsString('2023', $html);
        $this->assertStringContainsString('2022', $html);
    }

    public function testRenderSelectedYearIsMarked(): void
    {
        $html = $this->view->render(2023, 2022, 2024, []);

        $this->assertStringContainsString('value="2023" selected', $html);
    }

    public function testRenderShowsNoDataMessageWhenEmpty(): void
    {
        $html = $this->view->render(2024, 1988, 2024, []);

        $this->assertStringContainsString('select a draft year', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $picks = [self::createYearPick(['name' => 'Kevin Durant'])];

        $html = $this->view->render(2024, 1988, 2024, $picks);

        $this->assertStringContainsString('Kevin Durant', $html);
    }

    public function testRenderShowsRoundAndPickNumber(): void
    {
        $picks = [self::createYearPick(['draftround' => 1, 'draftpickno' => 7])];

        $html = $this->view->render(2024, 1988, 2024, $picks);

        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('>7<', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render(2024, 1988, 2024, [self::createYearPick()]);

        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('Player', $html);
        $this->assertStringContainsString('Pos', $html);
        $this->assertStringContainsString('College', $html);
    }

    public function testRenderLinksToPlayerProfile(): void
    {
        $picks = [self::createYearPick(['pid' => 99])];

        $html = $this->view->render(2024, 1988, 2024, $picks);

        $this->assertStringContainsString('pid=99', $html);
    }

    public function testRenderTeamHistoryShowsTeamName(): void
    {
        $team = $this->createMockTeam('Hawks', 1);

        $html = $this->view->renderTeamHistory($team, []);

        $this->assertStringContainsString('Hawks', $html);
        $this->assertStringContainsString('Draft History', $html);
    }

    public function testRenderTeamHistoryShowsTeamLogo(): void
    {
        $team = $this->createMockTeam('Hawks', 5);

        $html = $this->view->renderTeamHistory($team, []);

        $this->assertStringContainsString('images/logo/5.jpg', $html);
    }

    public function testRenderTeamHistoryShowsNoDataWhenEmpty(): void
    {
        $team = $this->createMockTeam('Hawks', 1);

        $html = $this->view->renderTeamHistory($team, []);

        $this->assertStringContainsString('No draft history found', $html);
    }

    public function testRenderTeamHistoryShowsRetiredBadge(): void
    {
        $team = $this->createMockTeam('Hawks', 1);
        $picks = [self::createTeamPick(['retired' => 1])];

        $html = $this->view->renderTeamHistory($team, $picks);

        $this->assertStringContainsString('(ret.)', $html);
    }

    public function testRenderTeamHistoryShowsDraftYear(): void
    {
        $team = $this->createMockTeam('Hawks', 1);
        $picks = [self::createTeamPick(['draftyear' => 2005])];

        $html = $this->view->renderTeamHistory($team, $picks);

        $this->assertStringContainsString('2005', $html);
    }

    /**
     * @return \Team&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createMockTeam(string $name, int $teamId): \Team
    {
        $team = $this->createMock(\Team::class);
        $team->name = $name;
        $team->teamID = $teamId;

        return $team;
    }

    /**
     * @return array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftedby: string, college: string, teamid: int|null, team_city: string|null, color1: string|null, color2: string|null}
     */
    private static function createYearPick(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftedby: string, college: string, teamid: int|null, team_city: string|null, color1: string|null, color2: string|null} */
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'G',
            'draftround' => 1,
            'draftpickno' => 1,
            'draftedby' => 'Hawks',
            'college' => 'Duke',
            'teamid' => 1,
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
        ], $overrides);
    }

    /**
     * @return array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftyear: int, college: string, retired: int}
     */
    private static function createTeamPick(array $overrides = []): array
    {
        /** @var array{pid: int, name: string, pos: string, draftround: int, draftpickno: int, draftyear: int, college: string, retired: int} */
        return array_merge([
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'G',
            'draftround' => 1,
            'draftpickno' => 1,
            'draftyear' => 2024,
            'college' => 'Duke',
            'retired' => 0,
        ], $overrides);
    }
}
