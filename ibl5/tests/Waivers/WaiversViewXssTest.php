<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversView;

class WaiversViewXssTest extends TestCase
{
    private WaiversView $view;

    protected function setUp(): void
    {
        $this->view = new WaiversView();
    }

    public function testRenderWaiverFormEscapesTeamName(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $html = $this->view->renderWaiverForm(
            $xssPayload,
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderWaiverFormEscapesAction(): void
    {
        $xssPayload = '"><script>alert(1)</script>';

        $html = $this->view->renderWaiverForm(
            'Test Team',
            1,
            $xssPayload,
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testBuildPlayerOptionEscapesPlayerName(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $optionHtml = $this->view->buildPlayerOption(
            1,
            $xssPayload,
            '$100/2yr'
        );

        $this->assertStringNotContainsString('<script>', $optionHtml);
        $this->assertStringContainsString('&lt;script&gt;', $optionHtml);
    }

    public function testBuildPlayerOptionEscapesContract(): void
    {
        $xssPayload = '<img src=x onerror=alert(1)>';

        $optionHtml = $this->view->buildPlayerOption(
            1,
            'Safe Player',
            $xssPayload
        );

        $this->assertStringNotContainsString('<img', $optionHtml);
        $this->assertStringContainsString('&lt;img', $optionHtml);
    }

    public function testRenderWaiverFormEscapesPlayerOptions(): void
    {
        $view = new WaiversView();
        $xssPayload = '<script>alert("xss")</script>';

        $option = $view->buildPlayerOption(1, $xssPayload, '$100');

        $html = $view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [$option],
            5,
            5
        );

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
    }
}
