<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountView;

/**
 * Characterization test for backlog 1.15 (split YourAccountView into
 * per-flow sub-views). Snapshots every renderXPage() output and asserts
 * byte-identical equality against the pre-refactor fixture, guaranteeing
 * the delegating facade produces unchanged HTML.
 *
 * @see /Users/ajaynicolas/.claude/plans/extract-youraccountview-page-variants.md
 */
final class YourAccountViewCharacterizationTest extends TestCase
{
    private YourAccountView $view;

    /** @var array<string, string> */
    private array $fixtures;

    protected function setUp(): void
    {
        $this->view = new YourAccountView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $fixturePath = __DIR__ . '/Fixtures/your-account-view-snapshots.json';
        $decoded = json_decode((string) file_get_contents($fixturePath), true);
        self::assertIsArray($decoded, 'Fixture file must decode to an array');
        /** @var array<string, string> $decoded */
        $this->fixtures = $decoded;
    }

    /**
     * CSRF tokens are cryptographically random per render call, so a raw
     * string comparison would fail even between two renders of the SAME
     * unmodified code. Normalize the token value out before comparing.
     */
    private static function normalizeCsrfToken(string $html): string
    {
        return (string) preg_replace(
            '/name="_csrf_token" value="[0-9a-f]{64}"/',
            'name="_csrf_token" value="{TOKEN}"',
            $html
        );
    }

    private function assertRenderIsByteIdentical(string $fixtureKey, string $actual): void
    {
        self::assertSame(
            self::normalizeCsrfToken($this->fixtures[$fixtureKey]),
            self::normalizeCsrfToken($actual)
        );
    }

    public function testRenderLoginPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical('renderLoginPage_null', $this->view->renderLoginPage(null));
    }

    public function testRenderLoginPageWithErrorIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical('renderLoginPage_error', $this->view->renderLoginPage('Login failed'));
    }

    public function testRenderRegisterPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical('renderRegisterPage', $this->view->renderRegisterPage());
    }

    public function testRenderRegistrationCompletePageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderRegistrationCompletePage',
            $this->view->renderRegistrationCompletePage('IBL Hoops')
        );
    }

    public function testRenderRegistrationErrorPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderRegistrationErrorPage',
            $this->view->renderRegistrationErrorPage('Username is taken')
        );
    }

    public function testRenderForgotPasswordPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical('renderForgotPasswordPage', $this->view->renderForgotPasswordPage());
    }

    public function testRenderResetEmailSentPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical('renderResetEmailSentPage', $this->view->renderResetEmailSentPage());
    }

    public function testRenderResetPasswordPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderResetPasswordPage',
            $this->view->renderResetPasswordPage('abc123', 'token456')
        );
    }

    public function testRenderPasswordResetSuccessPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderPasswordResetSuccessPage',
            $this->view->renderPasswordResetSuccessPage()
        );
    }

    public function testRenderPasswordResetErrorPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderPasswordResetErrorPage',
            $this->view->renderPasswordResetErrorPage('Token has expired')
        );
    }

    public function testRenderActivationSuccessPageIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderActivationSuccessPage',
            $this->view->renderActivationSuccessPage('NewPlayer')
        );
    }

    public function testRenderActivationErrorPageMismatchIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderActivationErrorPage_mismatch',
            $this->view->renderActivationErrorPage('mismatch')
        );
    }

    public function testRenderActivationErrorPageExpiredIsByteIdentical(): void
    {
        $this->assertRenderIsByteIdentical(
            'renderActivationErrorPage_expired',
            $this->view->renderActivationErrorPage('expired')
        );
    }
}
