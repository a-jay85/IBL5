<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\CsrfGuard;

class CsrfGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    // ============================================
    // TOKEN GENERATION
    // ============================================

    public function testGenerateRawTokenReturns64CharHex(): void
    {
        $token = CsrfGuard::generateRawToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateRawTokenStoresInSession(): void
    {
        $token = CsrfGuard::generateRawToken('myform');

        $this->assertArrayHasKey('_csrf_tokens', $_SESSION);
        $this->assertArrayHasKey('myform', $_SESSION['_csrf_tokens']);
        $this->assertCount(1, $_SESSION['_csrf_tokens']['myform']);
        $this->assertSame($token, $_SESSION['_csrf_tokens']['myform'][0]['token']);
    }

    public function testGenerateTokenReturnsHtmlHiddenInput(): void
    {
        $html = CsrfGuard::generateToken('testform');

        $this->assertStringStartsWith('<input type="hidden" name="_csrf_token" value="', $html);
        $this->assertStringEndsWith('">', $html);
    }

    public function testGenerateTokenUsesDefaultFormName(): void
    {
        CsrfGuard::generateRawToken();

        $this->assertArrayHasKey('default', $_SESSION['_csrf_tokens']);
    }

    public function testGenerateMultipleTokensForSameForm(): void
    {
        $token1 = CsrfGuard::generateRawToken('form');
        $token2 = CsrfGuard::generateRawToken('form');

        $this->assertNotSame($token1, $token2);
        $this->assertCount(2, $_SESSION['_csrf_tokens']['form']);
    }

    // ============================================
    // TOKEN VALIDATION
    // ============================================

    public function testValidTokenSucceeds(): void
    {
        $token = CsrfGuard::generateRawToken('login');

        $this->assertTrue(CsrfGuard::validateToken($token, 'login'));
    }

    public function testTokenIsSingleUse(): void
    {
        $token = CsrfGuard::generateRawToken('login');

        $this->assertTrue(CsrfGuard::validateToken($token, 'login'));
        $this->assertFalse(CsrfGuard::validateToken($token, 'login'));
    }

    public function testWrongFormNameFails(): void
    {
        $token = CsrfGuard::generateRawToken('formA');

        $this->assertFalse(CsrfGuard::validateToken($token, 'formB'));
    }

    public function testEmptyTokenFails(): void
    {
        CsrfGuard::generateRawToken();

        $this->assertFalse(CsrfGuard::validateToken(''));
    }

    public function testInvalidTokenFails(): void
    {
        CsrfGuard::generateRawToken();

        $this->assertFalse(CsrfGuard::validateToken('not-a-real-token'));
    }

    // ============================================
    // TOKEN EXPIRATION
    // ============================================

    public function testExpiredTokenIsRejected(): void
    {
        $token = CsrfGuard::generateRawToken('form');

        // Manually expire the token by setting its expiration to the past
        $_SESSION['_csrf_tokens']['form'][0]['expires'] = time() - 1;

        $this->assertFalse(CsrfGuard::validateToken($token, 'form'));
    }

    // ============================================
    // MAX TOKENS LIMIT
    // ============================================

    public function testMaxTokensKeepsOnlyLast10(): void
    {
        for ($i = 0; $i < 12; $i++) {
            CsrfGuard::generateRawToken('form');
        }

        $this->assertCount(10, $_SESSION['_csrf_tokens']['form']);
    }

    // ============================================
    // CONVENIENCE METHODS
    // ============================================

    public function testGetSubmittedTokenReadsFromPost(): void
    {
        $_POST['_csrf_token'] = 'test-token-value';

        $this->assertSame('test-token-value', CsrfGuard::getSubmittedToken());
    }

    public function testGetSubmittedTokenReturnsEmptyWhenMissing(): void
    {
        $this->assertSame('', CsrfGuard::getSubmittedToken());
    }

    public function testValidateSubmittedTokenCombinesGetAndValidate(): void
    {
        $token = CsrfGuard::generateRawToken('form');
        $_POST['_csrf_token'] = $token;

        $this->assertTrue(CsrfGuard::validateSubmittedToken('form'));
    }

    public function testValidateSubmittedTokenFailsWithNoPost(): void
    {
        CsrfGuard::generateRawToken('form');

        $this->assertFalse(CsrfGuard::validateSubmittedToken('form'));
    }

    // ============================================
    // CLEANUP
    // ============================================

    public function testClearTokensAllClearsEverything(): void
    {
        CsrfGuard::generateRawToken('formA');
        CsrfGuard::generateRawToken('formB');

        CsrfGuard::clearTokens('all');

        $this->assertArrayNotHasKey('_csrf_tokens', $_SESSION);
    }

    public function testClearTokensSpecificFormClearsOnlyThatForm(): void
    {
        CsrfGuard::generateRawToken('formA');
        CsrfGuard::generateRawToken('formB');

        CsrfGuard::clearTokens('formA');

        $this->assertArrayNotHasKey('formA', $_SESSION['_csrf_tokens']);
        $this->assertArrayHasKey('formB', $_SESSION['_csrf_tokens']);
    }

    public function testClearTokensOnEmptySessionIsNoOp(): void
    {
        CsrfGuard::clearTokens('all');

        $this->assertArrayNotHasKey('_csrf_tokens', $_SESSION);
    }
}
