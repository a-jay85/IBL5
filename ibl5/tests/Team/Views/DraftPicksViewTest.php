<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\DraftPicksView;

class DraftPicksViewTest extends TestCase
{
    private DraftPicksView $view;

    protected function setUp(): void
    {
        $this->view = new DraftPicksView();
    }

    public function testRendersListItems(): void
    {
        $picks = [
            [
                'originalTeamID' => 1,
                'originalTeamCity' => 'Atlanta',
                'originalTeamName' => 'Hawks',
                'year' => '2025',
                'round' => 1,
                'notes' => null,
            ],
            [
                'originalTeamID' => 2,
                'originalTeamCity' => 'Boston',
                'originalTeamName' => 'Celtics',
                'year' => '2026',
                'round' => 2,
                'notes' => null,
            ],
        ];

        $html = $this->view->render($picks);

        $this->assertSame(2, substr_count($html, 'draft-picks-list__item'));
        $this->assertStringContainsString('2025 R1 Atlanta Hawks', $html);
        $this->assertStringContainsString('2026 R2 Boston Celtics', $html);
    }

    public function testShowsNotesWhenPresent(): void
    {
        $picks = [
            [
                'originalTeamID' => 1,
                'originalTeamCity' => 'Atlanta',
                'originalTeamName' => 'Hawks',
                'year' => '2025',
                'round' => 1,
                'notes' => 'Top-10 protected',
            ],
        ];

        $html = $this->view->render($picks);

        $this->assertStringContainsString('draft-picks-list__notes', $html);
        $this->assertStringContainsString('Top-10 protected', $html);
    }

    public function testRendersEmptyListWhenNoPicks(): void
    {
        $html = $this->view->render([]);

        $this->assertStringContainsString('draft-picks-list', $html);
        $this->assertStringNotContainsString('draft-picks-list__item', $html);
    }

    public function testEscapesHtmlInNotes(): void
    {
        $picks = [
            [
                'originalTeamID' => 1,
                'originalTeamCity' => 'Atlanta',
                'originalTeamName' => 'Hawks',
                'year' => '2025',
                'round' => 1,
                'notes' => '<script>alert(1)</script>',
            ],
        ];

        $html = $this->view->render($picks);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
