<?php

declare(strict_types=1);

namespace Tests\FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewViewInterface;
use FreeAgencyPreview\FreeAgencyPreviewView;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FreeAgencyPreview\FreeAgencyPreviewView
 */
class FreeAgencyPreviewViewTest extends TestCase
{
    private FreeAgencyPreviewView $view;

    protected function setUp(): void
    {
        $this->view = new FreeAgencyPreviewView();
    }

    public function testImplementsViewInterface(): void
    {
        $this->assertInstanceOf(FreeAgencyPreviewViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $html = $this->view->render(2025, [self::createFreeAgent()]);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsSeasonYear(): void
    {
        $html = $this->view->render(2025, []);

        $this->assertStringContainsString('2025', $html);
        $this->assertStringContainsString('Free Agent Preview', $html);
    }

    public function testRenderShowsPlayerName(): void
    {
        $html = $this->view->render(2025, [self::createFreeAgent(['name' => 'Kevin Durant'])]);

        $this->assertStringContainsString('Kevin Durant', $html);
    }

    public function testRenderShowsRatings(): void
    {
        $html = $this->view->render(2025, [self::createFreeAgent(['r_fga' => 75, 'oo' => 80])]);

        $this->assertStringContainsString('>75<', $html);
        $this->assertStringContainsString('>80<', $html);
    }

    public function testRenderShowsTableHeaders(): void
    {
        $html = $this->view->render(2025, [self::createFreeAgent()]);

        $this->assertStringContainsString('Player', $html);
        $this->assertStringContainsString('Team', $html);
        $this->assertStringContainsString('Pos', $html);
        $this->assertStringContainsString('Age', $html);
    }

    public function testRenderIncludesSortableClass(): void
    {
        $html = $this->view->render(2025, []);

        $this->assertStringContainsString('sortable', $html);
    }

    public function testRenderHandlesEmptyFreeAgents(): void
    {
        $html = $this->view->render(2025, []);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    /**
     * @return array{pid: int, tid: int, name: string, teamname: string, team_city: string, color1: string, color2: string, pos: string, age: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_tga: int, r_tgp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_to: int, r_foul: int, oo: int, do: int, po: int, to: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playingTime: int, security: int, tradition: int}
     */
    private static function createFreeAgent(array $overrides = []): array
    {
        /** @var array{pid: int, tid: int, name: string, teamname: string, team_city: string, color1: string, color2: string, pos: string, age: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_tga: int, r_tgp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_to: int, r_foul: int, oo: int, do: int, po: int, to: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playingTime: int, security: int, tradition: int} */
        return array_merge([
            'pid' => 1,
            'tid' => 1,
            'name' => 'Test Player',
            'teamname' => 'Hawks',
            'team_city' => 'Atlanta',
            'color1' => 'FF0000',
            'color2' => '000000',
            'pos' => 'G',
            'age' => 25,
            'r_fga' => 50,
            'r_fgp' => 50,
            'r_fta' => 50,
            'r_ftp' => 50,
            'r_tga' => 50,
            'r_tgp' => 50,
            'r_orb' => 50,
            'r_drb' => 50,
            'r_ast' => 50,
            'r_stl' => 50,
            'r_blk' => 50,
            'r_to' => 50,
            'r_foul' => 50,
            'oo' => 50,
            'do' => 50,
            'po' => 50,
            'to' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'td' => 50,
            'loyalty' => 50,
            'winner' => 50,
            'playingTime' => 50,
            'security' => 50,
            'tradition' => 50,
        ], $overrides);
    }
}
