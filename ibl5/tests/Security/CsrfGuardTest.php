<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Security\CsrfGuard;
use Tests\Clock\FixedClock;

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
        CsrfGuard::setTestClock(null);
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
    // SESSION START
    // ============================================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGenerateRawTokenStartsSession(): void
    {
        $this->assertCallStartsSession(static fn () => CsrfGuard::generateRawToken('form'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGenerateTokenStartsSession(): void
    {
        $this->assertCallStartsSession(static fn () => CsrfGuard::generateToken('form'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateTokenStartsSession(): void
    {
        $this->assertCallStartsSession(static fn () => CsrfGuard::validateToken('not-a-real-token', 'form'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testClearTokensStartsSession(): void
    {
        $this->assertCallStartsSession(static fn () => CsrfGuard::clearTokens('form'));
    }

    /**
     * Runs $call in a fresh process with no active session and asserts the
     * call itself started one. Cookies and cache headers are off so a CLI
     * session_start() emits nothing.
     */
    private function assertCallStartsSession(callable $call): void
    {
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        $statusBefore = session_status();
        $this->assertSame(PHP_SESSION_NONE, $statusBefore);

        try {
            $call();
            $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
        }
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

    public function testGenerateTokenStoresTokenThatValidates(): void
    {
        CsrfGuard::setTestClock(new FixedClock(1_000_000));

        $html = CsrfGuard::generateToken('testform');

        $this->assertSame(1, preg_match('/value="([0-9a-f]{64})"/', $html, $matches));
        $this->assertSame($matches[1], $_SESSION['_csrf_tokens']['testform'][0]['token']);
        $this->assertTrue(CsrfGuard::validateToken($matches[1], 'testform'));
    }

    /**
     * Negative path: an empty submitted token is rejected before the stored
     * list is scanned, even when a stored entry's token is also empty.
     */
    public function testEmptyTokenRejectedEvenWhenStoredTokenIsEmpty(): void
    {
        CsrfGuard::setTestClock(new FixedClock(1_000_000));
        CsrfGuard::generateRawToken('form');
        $_SESSION['_csrf_tokens']['form'][0]['token'] = '';

        $this->assertFalse(CsrfGuard::validateToken('', 'form'));
        $this->assertCount(1, $_SESSION['_csrf_tokens']['form']);
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

    /**
     * Boundary accept: a token whose `expires` equals the validation `now`
     * is still valid (the check is `now > expires`, strict). Injecting a
     * FixedClock pins the exact second deterministically.
     */
    public function testTokenValidAtExactExpiryBoundary(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);

        $token = CsrfGuard::generateRawToken('form'); // expires = 1_000_000 + 14400
        $clock->setNow(1_000_000 + 14400);            // now == expires

        $this->assertTrue(CsrfGuard::validateToken($token, 'form'));
    }

    /**
     * Boundary reject (negative path): one second past expiry the token is
     * rejected.
     */
    public function testTokenRejectedOneSecondPastExpiry(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);

        $token = CsrfGuard::generateRawToken('form');
        $clock->setNow(1_000_000 + 14400 + 1);        // now == expires + 1

        $this->assertFalse(CsrfGuard::validateToken($token, 'form'));
    }

    /**
     * An expired token earlier in the list is skipped; the scan continues
     * and still accepts a valid token stored after it.
     */
    public function testValidTokenAfterExpiredTokenInSameFormStillValidates(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);

        CsrfGuard::generateRawToken('form');              // index 0, expires 1_014_400
        $clock->setNow(1_000_100);
        $valid = CsrfGuard::generateRawToken('form');     // index 1, expires 1_014_500
        $this->assertCount(2, $_SESSION['_csrf_tokens']['form']);

        $clock->setNow(1_014_401);                        // index 0 expired, index 1 valid
        $this->assertTrue(CsrfGuard::validateToken($valid, 'form'));
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

    /**
     * Negative path: storing a new token drops same-form tokens already past
     * their expiry.
     */
    public function testStoreTokenDropsExpiredTokensForSameForm(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);
        CsrfGuard::generateRawToken('form');                  // expires 1_014_400
        $clock->setNow(1_014_401);

        $fresh = CsrfGuard::generateRawToken('form');

        $this->assertSame([$fresh], array_column($_SESSION['_csrf_tokens']['form'], 'token'));
    }

    /**
     * Boundary: cleanup keeps a token whose expiry equals the current second.
     */
    public function testStoreTokenKeepsTokenAtExactExpiry(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);
        $old = CsrfGuard::generateRawToken('form');           // expires 1_014_400
        $clock->setNow(1_014_400);

        $fresh = CsrfGuard::generateRawToken('form');

        $this->assertSame([$old, $fresh], array_column($_SESSION['_csrf_tokens']['form'], 'token'));
    }

    public function testCleanupRepacksKeysAfterDroppingExpiredToken(): void
    {
        $clock = new FixedClock(1_000_000);
        CsrfGuard::setTestClock($clock);
        CsrfGuard::generateRawToken('form');
        $clock->setNow(1_014_401);

        CsrfGuard::generateRawToken('form');

        $this->assertSame([0], array_keys($_SESSION['_csrf_tokens']['form']));
    }

    /**
     * Boundary: exactly MAX_TOKENS tokens are all kept, in order.
     */
    public function testTenTokensAreAllKept(): void
    {
        CsrfGuard::setTestClock(new FixedClock(1_000_000));
        $generated = [];
        for ($i = 0; $i < 10; $i++) {
            $generated[] = CsrfGuard::generateRawToken('form');
        }

        $this->assertSame($generated, array_column($_SESSION['_csrf_tokens']['form'], 'token'));
    }

    /**
     * Boundary: the 11th token pushes out only the oldest one.
     */
    public function testCapKeepsTenNewestTokens(): void
    {
        CsrfGuard::setTestClock(new FixedClock(1_000_000));
        $generated = [];
        for ($i = 0; $i < 11; $i++) {
            $generated[] = CsrfGuard::generateRawToken('form');
        }

        $this->assertSame(array_slice($generated, 1), array_column($_SESSION['_csrf_tokens']['form'], 'token'));
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

    /**
     * Negative path: clearing one form on a session with no token store
     * returns before writing anything.
     */
    public function testClearTokensForSpecificFormOnEmptySessionWritesNothing(): void
    {
        CsrfGuard::clearTokens('formA');

        $this->assertSame([], $_SESSION);
    }

    public function testValidatedTokenRemovalRepacksKeys(): void
    {
        CsrfGuard::setTestClock(new FixedClock(1_000_000));
        $first = CsrfGuard::generateRawToken('form');
        $second = CsrfGuard::generateRawToken('form');

        $this->assertTrue(CsrfGuard::validateToken($first, 'form'));

        $this->assertSame([0], array_keys($_SESSION['_csrf_tokens']['form']));
        $this->assertSame($second, $_SESSION['_csrf_tokens']['form'][0]['token']);
    }
}
