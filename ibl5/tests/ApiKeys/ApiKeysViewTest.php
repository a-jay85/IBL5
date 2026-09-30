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

    /**
     * @return array{status: string, broken_reason: ?string, spreadsheet_url: string, last_refresh_at: ?string, last_refresh_status: ?string, last_error: ?string}
     */
    private static function googleSummary(string $status = 'active', ?string $reason = null): array
    {
        return [
            'status' => $status,
            'broken_reason' => $reason,
            'spreadsheet_url' => 'https://docs.google.com/spreadsheets/d/sheet-1/edit',
            'last_refresh_at' => '2026-09-01 12:00:00',
            'last_refresh_status' => 'ok',
            'last_error' => null,
        ];
    }

    public function testGoogleCardNotConfiguredShowsNoticeAndNoForms(): void
    {
        $html = $this->view->renderGoogleSheetCard(null, false);

        $this->assertStringContainsString('id="google-sheet-card"', $html);
        $this->assertStringContainsString('not configured on this server', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function testGoogleCardNoConnectionRendersConnectFormWithCsrfToken(): void
    {
        $html = $this->view->renderGoogleSheetCard(null, true);

        $this->assertStringContainsString('op=google_start', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('id="google-sheet-connect"', $html);
        $this->assertStringNotContainsString('id="google-sheet-open"', $html);
        $this->assertStringNotContainsString('id="google-sheet-refresh"', $html);
    }

    public function testGoogleCardActiveRendersOpenRefreshDisconnectAndNoReconnect(): void
    {
        $html = $this->view->renderGoogleSheetCard(self::googleSummary(), true);

        $this->assertStringContainsString('id="google-sheet-open" class="ibl-btn" href="https://docs.google.com/spreadsheets/d/sheet-1/edit"', $html);
        $this->assertStringContainsString('op=google_refresh', $html);
        $this->assertStringContainsString('id="google-sheet-refresh"', $html);
        $this->assertStringContainsString('id="google-sheet-disconnect"', $html);
        $this->assertStringContainsString('2026-09-01 12:00:00', $html);
        $this->assertStringNotContainsString('id="google-sheet-reconnect"', $html);
        $this->assertStringNotContainsString('id="google-sheet-connect"', $html);
        $this->assertStringNotContainsString('ibl-alert--warning', $html);
    }

    public function testGoogleCardActiveWithErrorShowsWarning(): void
    {
        $summary = self::googleSummary();
        $summary['last_refresh_status'] = 'error';
        $summary['last_error'] = '429 RESOURCE_EXHAUSTED';

        $html = $this->view->renderGoogleSheetCard($summary, true);

        $this->assertStringContainsString('ibl-alert--warning', $html);
        $this->assertStringContainsString('429 RESOURCE_EXHAUSTED', $html);
    }

    public function testGoogleCardBrokenRendersReconnectReasonTextAndNoRefresh(): void
    {
        $expected = [
            'invalid_grant' => 'Google access was revoked or expired.',
            'sheet_missing' => 'The spreadsheet was deleted from Drive.',
            'key_unavailable' => 'The server encryption key changed.',
            'something_new' => 'Please reconnect.',
        ];
        foreach ($expected as $reason => $text) {
            $html = $this->view->renderGoogleSheetCard(self::googleSummary('broken', $reason), true);

            $this->assertStringContainsString('ibl-alert--warning', $html, $reason);
            $this->assertStringContainsString($text, $html, $reason);
            $this->assertStringContainsString('id="google-sheet-reconnect"', $html, $reason);
            $this->assertStringContainsString('id="google-sheet-disconnect"', $html, $reason);
            $this->assertStringNotContainsString('id="google-sheet-refresh"', $html, $reason);
            $this->assertStringNotContainsString('op=google_refresh', $html, $reason);
        }
    }

    public function testGoogleCardBrokenSheetMissingOmitsOpenLink(): void
    {
        $missing = $this->view->renderGoogleSheetCard(self::googleSummary('broken', 'sheet_missing'), true);
        $revoked = $this->view->renderGoogleSheetCard(self::googleSummary('broken', 'invalid_grant'), true);

        $this->assertStringNotContainsString('id="google-sheet-open"', $missing);
        $this->assertStringContainsString('id="google-sheet-open"', $revoked);
    }

    public function testGoogleCardRejectsNonGoogleUrlAndEscapesError(): void
    {
        $summary = self::googleSummary();
        $summary['spreadsheet_url'] = 'javascript:alert(1)';
        $summary['last_refresh_status'] = 'error';
        $summary['last_error'] = '<b>x</b>';

        $html = $this->view->renderGoogleSheetCard($summary, true);

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function testViewSourceNeverReferencesRefreshTokenColumn(): void
    {
        $file = (new \ReflectionClass(\ApiKeys\ApiKeysView::class))->getFileName();
        $this->assertIsString($file);
        $source = (string) file_get_contents($file);

        $this->assertStringNotContainsString('refresh_token', $source);
    }

    public function testRenderFlashEscapesTextAndReturnsEmptyForNull(): void
    {
        $this->assertSame('', $this->view->renderFlash(null));

        $html = $this->view->renderFlash(['type' => 'error', 'text' => '<script>x</script>']);
        $this->assertStringContainsString('id="apikeys-flash"', $html);
        $this->assertStringContainsString('ibl-alert--error', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $success = $this->view->renderFlash(['type' => 'success', 'text' => 'Done']);
        $this->assertStringContainsString('ibl-alert--success', $success);
    }
}
