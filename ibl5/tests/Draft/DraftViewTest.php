<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftView;
use Draft\Contracts\DraftViewInterface;

/**
 * DraftViewTest - Tests for DraftView
 */
class DraftViewTest extends TestCase
{
    private DraftView $view;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->view = new DraftView();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ============================================
    // RENDER VALIDATION ERROR TESTS
    // ============================================

    public function testRenderValidationErrorReturnsString(): void
    {
        $result = $this->view->renderValidationError('Test error');

        $this->assertIsString($result);
    }

    public function testRenderValidationErrorContainsErrorMessage(): void
    {
        $result = $this->view->renderValidationError('Player not found');

        $this->assertStringContainsString('Player not found', $result);
    }

    public function testRenderValidationErrorContainsDraftModuleLink(): void
    {
        $result = $this->view->renderValidationError('Test error');

        $this->assertStringContainsString('modules.php?name=Draft', $result);
    }

    public function testRenderValidationErrorEscapesHtml(): void
    {
        $result = $this->view->renderValidationError('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result);
    }

    // ============================================
    // RENDER DRAFT INTERFACE TESTS
    // ============================================

    public function testRenderDraftInterfaceReturnsString(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertIsString($result);
    }

    public function testRenderDraftInterfaceAcceptsSeasonYear(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        // seasonYear is accepted as a parameter but not rendered in the view output
        $this->assertIsString($result);
    }

    public function testRenderDraftInterfaceContainsTeamLogo(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 5);

        $this->assertStringContainsString('images/logo/5.jpg', $result);
    }

    public function testRenderDraftInterfaceContainsForm(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('</form>', $result);
    }

    public function testRenderDraftInterfaceContainsHiddenFields(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        $this->assertStringContainsString("type='hidden'", $result);
        $this->assertStringContainsString('draft_round', $result);
        $this->assertStringContainsString('draft_pick', $result);
    }

    public function testRenderDraftInterfaceContainsCsrfToken(): void
    {
        $players = [];

        $result = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);

        // The draft form must carry a CSRF token so the gated op=select handler
        // (modules/Draft/index.php) can validate the submission.
        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderDraftInterfaceAcceptsNullPickState(): void
    {
        $players = [[
            'id' => 1, 'name' => 'Rookie One', 'pos' => 'PG', 'age' => 20, 'team' => 'Nowhere',
            'fga' => 1, 'fgp' => 1, 'fta' => 1, 'ftp' => 1, 'r_3ga' => 1, 'r_3gp' => 1,
            'orb' => 1, 'drb' => 1, 'ast' => 1, 'stl' => 1, 'tvr' => 1, 'blk' => 1,
            'oo' => 1, 'r_drive_off' => 1, 'po' => 1, 'r_trans_off' => 1, 'od' => 1,
            'dd' => 1, 'pd' => 1, 'td' => 1, 'talent' => 1, 'skill' => 1, 'intangibles' => 1,
            'ranking' => null, 'invite' => null, 'drafted' => 0, 'sta' => null,
            'team_tid' => null, 'color1' => null, 'color2' => null,
        ]];

        $owned = $this->view->renderDraftInterface($players, 'TestTeam', 'TestTeam', 1, 1, 2025, 1);
        $this->assertStringContainsString('Draft Player', $owned);

        $noPick = $this->view->renderDraftInterface($players, 'TestTeam', null, null, null, 2025, 1);
        $this->assertStringNotContainsString('Draft Player', $noPick);
    }
}
