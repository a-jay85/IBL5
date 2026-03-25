<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversView;
use Waivers\Contracts\WaiversViewInterface;

/**
 * WaiversViewTest - Tests for WaiversView
 */
class WaiversViewTest extends TestCase
{
    private WaiversView $view;

    protected function setUp(): void
    {
        $this->view = new WaiversView();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $view = new WaiversView();

        $this->assertInstanceOf(WaiversView::class, $view);
    }

    public function testImplementsInterface(): void
    {
        $view = new WaiversView();

        $this->assertInstanceOf(WaiversViewInterface::class, $view);
    }

    // ============================================
    // RENDER WAIVER FORM TESTS
    // ============================================

    public function testRenderWaiverFormReturnsContent(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertNotEmpty($output);
    }

    public function testRenderWaiverFormContainsTeamName(): void
    {
        $output = $this->view->renderWaiverForm(
            'Boston Celtics',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('Boston Celtics', $output);
    }

    public function testRenderWaiverFormContainsForm(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('<form', $output);
    }

    public function testRenderWaiverFormContainsRosterSpots(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            3,
            2
        );

        $this->assertStringContainsString('3 OPEN SPOTS', $output);
        $this->assertStringContainsString('2 HEALTHY SPOTS', $output);
    }

    public function testRenderWaiverFormShowsErrorMessage(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            null,
            'Player cannot be dropped'
        );

        $this->assertStringContainsString('Player cannot be dropped', $output);
        $this->assertStringContainsString('ibl-alert--error', $output);
    }

    public function testRenderWaiverFormShowsSuccessAddBanner(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'add',
            [],
            5,
            5,
            'player_added'
        );

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully signed from waivers.', $output);
    }

    public function testRenderWaiverFormShowsSuccessDropBanner(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            'player_dropped'
        );

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully dropped to waivers.', $output);
    }

    public function testRenderWaiverFormEscapesTeamName(): void
    {
        $output = $this->view->renderWaiverForm(
            '<script>alert("xss")</script>',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderWaiverFormUsesDesignSystemClasses(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('ibl-card', $output);
        $this->assertStringContainsString('ibl-card__header', $output);
        $this->assertStringContainsString('ibl-card__body', $output);
        $this->assertStringContainsString('ibl-select', $output);
        $this->assertStringContainsString('ibl-card__title', $output);
    }

    public function testRenderWaiverFormNoCustomWaiversClasses(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('waivers-form-card', $output);
        $this->assertStringNotContainsString('waivers-form-header', $output);
        $this->assertStringNotContainsString('waivers-form-body', $output);
        $this->assertStringNotContainsString('waivers-select', $output);
        $this->assertStringNotContainsString('waivers-team-title', $output);
    }

    // ============================================
    // RENDER WAIVERS CLOSED TESTS
    // ============================================

    public function testRenderWaiversClosedReturnsString(): void
    {
        $output = $this->view->renderWaiversClosed();

        $this->assertIsString($output);
        $this->assertStringContainsString('waivers', strtolower($output));
    }
}
