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
        $this->view = new DraftView();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $view = new DraftView();

        $this->assertInstanceOf(DraftView::class, $view);
    }

    public function testImplementsInterface(): void
    {
        $view = new DraftView();

        $this->assertInstanceOf(DraftViewInterface::class, $view);
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

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasRenderPlayerTableMethod(): void
    {
        $this->assertTrue(method_exists($this->view, 'renderPlayerTable'));
    }
}
