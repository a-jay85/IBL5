<?php

declare(strict_types=1);

namespace Tests\GoogleSheets;

use GoogleSheets\GoogleOAuthState;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

class GoogleOAuthStateTest extends TestCase
{
    private FixedClock $clock;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->clock = new FixedClock(1_000_000);
        GoogleOAuthState::setTestClock($this->clock);
    }

    protected function tearDown(): void
    {
        GoogleOAuthState::setTestClock(null);
        $_SESSION = [];
    }

    public function testIssueStoresStateBoundToUserWithTenMinuteExpiry(): void
    {
        $state = GoogleOAuthState::issue(7);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $state);
        self::assertSame(
            ['value' => $state, 'user_id' => 7, 'expires_at' => 1_000_600],
            $_SESSION[GoogleOAuthState::SESSION_KEY]
        );
    }

    public function testConsumeAcceptsMatchingStateExactlyOnce(): void
    {
        $state = GoogleOAuthState::issue(7);

        self::assertTrue(GoogleOAuthState::consume($state, 7));
        self::assertFalse(GoogleOAuthState::consume($state, 7));
        self::assertArrayNotHasKey(GoogleOAuthState::SESSION_KEY, $_SESSION);
    }

    public function testConsumeRejectsStateIssuedForAnotherUser(): void
    {
        $state = GoogleOAuthState::issue(7);

        self::assertFalse(GoogleOAuthState::consume($state, 8));
    }

    public function testConsumeRejectsExpiredState(): void
    {
        $state = GoogleOAuthState::issue(7);
        $this->clock->setNow(1_000_601);

        self::assertFalse(GoogleOAuthState::consume($state, 7));
    }

    public function testConsumeAcceptsStateJustBeforeExpiry(): void
    {
        $state = GoogleOAuthState::issue(7);
        $this->clock->setNow(1_000_599);

        self::assertTrue(GoogleOAuthState::consume($state, 7));
    }

    public function testConsumeRejectsMismatchedEmptyAndNonStringValues(): void
    {
        foreach (['x', '', null, ['a'], 0] as $submitted) {
            GoogleOAuthState::issue(7);
            self::assertFalse(GoogleOAuthState::consume($submitted, 7), var_export($submitted, true));
        }
    }

    public function testConsumeRemovesStateEvenWhenRejected(): void
    {
        $state = GoogleOAuthState::issue(7);

        self::assertFalse(GoogleOAuthState::consume('wrong', 7));
        self::assertFalse(GoogleOAuthState::consume($state, 7));
    }

    public function testIssueReplacesPreviousState(): void
    {
        $first = GoogleOAuthState::issue(7);
        $second = GoogleOAuthState::issue(7);

        self::assertNotSame($first, $second);
        self::assertFalse(GoogleOAuthState::consume($first, 7));

        $third = GoogleOAuthState::issue(7);
        self::assertTrue(GoogleOAuthState::consume($third, 7));
    }
}
