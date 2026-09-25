<?php

declare(strict_types=1);

namespace Tests\ApiKeys;

use ApiKeys\ApiKeysView;
use PHPUnit\Framework\TestCase;

class ApiKeysViewTest extends TestCase
{
    private ApiKeysView $view;

    protected function setUp(): void
    {
        $this->view = new ApiKeysView();
    }

    public function testRenderNoKeyStateContainsGenerateButton(): void
    {
        // CsrfGuard requires session — start one for test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $html = $this->view->renderNoKeyState();

        $this->assertStringContainsString('Generate API Key', $html);
        $this->assertStringContainsString('op=generate', $html);
        $this->assertStringContainsString("don't have an API key yet", $html);
    }

    public function testRenderNewKeyStateContainsRawKey(): void
    {
        $rawKey = 'ibl_abcdef1234567890abcdef1234567890';

        $html = $this->view->renderNewKeyState($rawKey);

        $this->assertStringContainsString($rawKey, $html);
        $this->assertStringContainsString("won't be shown again", $html);
        $this->assertStringContainsString('IMPORTDATA', $html);
    }

    public function testRenderNewKeyStateWarnsThatUrlKeysAreLogged(): void
    {
        $html = $this->view->renderNewKeyState('ibl_abcdef1234567890abcdef1234567890');

        $this->assertStringContainsString('Your key is part of this URL.', $html);
        $this->assertStringContainsString('X-API-Key', $html);
    }

    public function testRenderExportGuideWarnsThatUrlKeysAreLogged(): void
    {
        $html = $this->view->renderExportGuide();

        $this->assertStringContainsString('Your key is part of this URL.', $html);
        $this->assertStringContainsString('X-API-Key', $html);
    }

    public function testRenderNewKeyStateEscapesKey(): void
    {
        // Key with characters that could be XSS if not escaped
        $rawKey = 'ibl_test<script>alert(1)</script>';

        $html = $this->view->renderNewKeyState($rawKey);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderActiveKeyStateContainsPrefix(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $keyStatus = [
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2026-01-15 10:30:00',
            'last_used_at' => '2026-03-20 14:00:00',
        ];

        $html = $this->view->renderActiveKeyState($keyStatus);

        $this->assertStringContainsString('ibl_test', $html);
        $this->assertStringContainsString('Revoke Key', $html);
        $this->assertStringContainsString('op=revoke', $html);
        $this->assertStringContainsString('2026-01-15 10:30:00', $html);
        $this->assertStringContainsString('2026-03-20 14:00:00', $html);
    }

    public function testRenderActiveKeyStateShowsNeverWhenNotUsed(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $keyStatus = [
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2026-01-15 10:30:00',
            'last_used_at' => null,
        ];

        $html = $this->view->renderActiveKeyState($keyStatus);

        $this->assertStringContainsString('Never', $html);
    }

    public function testRenderActiveKeyStateHasNoRetiredGuideLink(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $keyStatus = [
            'key_prefix' => 'ibl_test',
            'permission_level' => 'public',
            'rate_limit_tier' => 'standard',
            'is_active' => 1,
            'created_at' => '2026-01-15 10:30:00',
            'last_used_at' => null,
        ];

        $html = $this->view->renderActiveKeyState($keyStatus);

        // The guide now renders below this card, so the old cross-module button is gone.
        $this->assertStringNotContainsString('PlayerExportGuide', $html);
    }

    public function testRenderExportGuideContainsColumnReferenceTable(): void
    {
        $html = $this->view->renderExportGuide();

        $this->assertStringContainsString('Column Reference', $html);
        $this->assertStringContainsString('<table', $html);
    }

    public function testRenderExportGuideHasNoSelfLinkToApiKeys(): void
    {
        $html = $this->view->renderExportGuide();

        $this->assertStringNotContainsString('name=ApiKeys', $html);
    }
}
