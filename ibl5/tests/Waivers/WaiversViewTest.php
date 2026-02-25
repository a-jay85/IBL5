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

    public function testRenderWaiverFormOutputsContent(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    public function testRenderWaiverFormContainsTeamName(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Boston Celtics',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('Boston Celtics', $output);
    }

    public function testRenderWaiverFormContainsForm(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    public function testRenderWaiverFormContainsRosterSpots(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            3,
            2
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('3 OPEN SPOTS', $output);
        $this->assertStringContainsString('2 HEALTHY SPOTS', $output);
    }

    public function testRenderWaiverFormShowsErrorMessage(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            null,
            'Player cannot be dropped'
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('Player cannot be dropped', $output);
        $this->assertStringContainsString('ibl-alert--error', $output);
    }

    public function testRenderWaiverFormShowsSuccessAddBanner(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'add',
            [],
            5,
            5,
            'player_added'
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully signed from waivers.', $output);
    }

    public function testRenderWaiverFormShowsSuccessDropBanner(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            'player_dropped'
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully dropped to waivers.', $output);
    }

    public function testRenderWaiverFormEscapesTeamName(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            '<script>alert("xss")</script>',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderWaiverFormUsesDesignSystemClasses(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('ibl-card', $output);
        $this->assertStringContainsString('ibl-card__header', $output);
        $this->assertStringContainsString('ibl-card__body', $output);
        $this->assertStringContainsString('ibl-select', $output);
        $this->assertStringContainsString('ibl-card__title', $output);
    }

    public function testRenderWaiverFormNoCustomWaiversClasses(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );
        $output = ob_get_clean();

        $this->assertStringNotContainsString('waivers-form-card', $output);
        $this->assertStringNotContainsString('waivers-form-header', $output);
        $this->assertStringNotContainsString('waivers-form-body', $output);
        $this->assertStringNotContainsString('waivers-select', $output);
        $this->assertStringNotContainsString('waivers-team-title', $output);
    }
}
