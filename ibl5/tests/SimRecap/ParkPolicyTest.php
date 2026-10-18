<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\ParkPolicy;

/**
 * @phpstan-type Row array{route: string, attempts: int, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, created_epoch: int}
 * @phpstan-type Decision array{outcome: string, route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
 *
 * @covers \SimRecap\ParkPolicy
 */
final class ParkPolicyTest extends TestCase
{
    private const CREATED = 1_000_000;          // created_epoch for every row
    private const NOW = self::CREATED + 3_600;  // one hour into the 24h window

    /**
     * @return Row
     */
    private static function row(
        string $route = 'mac',
        int $attempts = 0,
        int $flaky = 0,
        int $transport = 0,
        int $usageWaits = 0,
        string $authFailedHosts = '',
        int $createdEpoch = self::CREATED,
    ): array {
        return [
            'route' => $route,
            'attempts' => $attempts,
            'flaky_failures' => $flaky,
            'transport_failures' => $transport,
            'usage_waits' => $usageWaits,
            'auth_failed_hosts' => $authFailedHosts,
            'created_epoch' => $createdEpoch,
        ];
    }

    /**
     * @return Decision
     */
    private static function expected(
        string $outcome,
        string $route,
        ?int $blockedUntil,
        int $flaky = 0,
        int $transport = 0,
        int $usageWaits = 0,
        string $authFailedHosts = '',
        int $refund = 0,
    ): array {
        return [
            'outcome' => $outcome,
            'route' => $route,
            'blockedUntilEpoch' => $blockedUntil,
            'counters' => [
                'flaky_failures' => $flaky,
                'transport_failures' => $transport,
                'usage_waits' => $usageWaits,
                'auth_failed_hosts' => $authFailedHosts,
                'attempts_refund' => $refund,
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Whitelists and validators
    // ---------------------------------------------------------------

    public function testWhitelistConstantsArePinned(): void
    {
        // array_map keeps PHPStan from folding the constant into the literal it is compared with.
        $strings = static fn (array $values): array => array_map(static fn (string $v): string => $v, $values);

        self::assertSame(['flaky', 'usage_limit', 'auth', 'transport', 'prompt'], $strings(ParkPolicy::CAUSES));
        self::assertSame(['mac', 'actions'], $strings(ParkPolicy::HOSTS));
        self::assertSame(
            ['retrying', 'waiting', 'handed_mac', 'handed_actions', 'posted', 'recovered', 'unavailable'],
            $strings(ParkPolicy::NOTIFY_STATES),
        );
    }

    #[DataProvider('knownCauseProvider')]
    public function testAssertCauseAcceptsKnownCause(string $cause): void
    {
        $this->expectNotToPerformAssertions();

        ParkPolicy::assertCause($cause);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownCauseProvider(): array
    {
        return [
            'flaky' => ['flaky'],
            'usage_limit' => ['usage_limit'],
            'auth' => ['auth'],
            'transport' => ['transport'],
            'prompt' => ['prompt'],
        ];
    }

    #[DataProvider('unknownCauseProvider')]
    public function testAssertCauseRejectsUnknownCause(string $cause): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap failure cause$/');

        ParkPolicy::assertCause($cause);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownCauseProvider(): array
    {
        return [
            'empty string' => [''],
            'wrong case' => ['FLAKY'],
            'unlisted cause' => ['timeout'],
        ];
    }

    #[DataProvider('knownHostProvider')]
    public function testAssertHostAcceptsKnownHost(string $host): void
    {
        $this->expectNotToPerformAssertions();

        ParkPolicy::assertHost($host);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownHostProvider(): array
    {
        return [
            'mac' => ['mac'],
            'actions' => ['actions'],
        ];
    }

    #[DataProvider('unknownHostProvider')]
    public function testAssertHostRejectsUnknownHost(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap host$/');

        ParkPolicy::assertHost($host);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownHostProvider(): array
    {
        return [
            'empty string' => [''],
            'wrong case' => ['Mac'],
            'unlisted host' => ['github'],
        ];
    }

    #[DataProvider('knownNotifyStateProvider')]
    public function testAssertNotifyStateAcceptsKnownState(string $state): void
    {
        $this->expectNotToPerformAssertions();

        ParkPolicy::assertNotifyState($state);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownNotifyStateProvider(): array
    {
        return [
            'retrying' => ['retrying'],
            'waiting' => ['waiting'],
            'handed_mac' => ['handed_mac'],
            'handed_actions' => ['handed_actions'],
            'posted' => ['posted'],
            'recovered' => ['recovered'],
            'unavailable' => ['unavailable'],
        ];
    }

    #[DataProvider('unknownNotifyStateProvider')]
    public function testAssertNotifyStateRejectsUnknownState(string $state): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap notify state$/');

        ParkPolicy::assertNotifyState($state);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownNotifyStateProvider(): array
    {
        return [
            'empty string' => [''],
            'outcome name, not a state' => ['handed'],
            'done event, not a state' => ['done'],
        ];
    }

    #[DataProvider('otherHostProvider')]
    public function testOtherHostSwapsHosts(string $host, string $expected): void
    {
        self::assertSame($expected, ParkPolicy::otherHost($host));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function otherHostProvider(): array
    {
        return [
            'mac to actions' => ['mac', 'actions'],
            'actions to mac' => ['actions', 'mac'],
        ];
    }

    public function testOtherHostRejectsUnknownHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap host$/');

        ParkPolicy::otherHost('github');
    }

    public function testDecideRejectsUnknownCauseBeforeHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap failure cause$/');

        ParkPolicy::decide('timeout', 'github', self::row(), null, self::NOW);
    }

    public function testDecideRejectsUnknownHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown sim recap host$/');

        ParkPolicy::decide('flaky', 'github', self::row(), null, self::NOW);
    }

    // ---------------------------------------------------------------
    // decide(): window end, attempt backstop, per-cause routing
    // ---------------------------------------------------------------

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('windowEdgeProvider')]
    public function testDecideWindowEdge(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function windowEdgeProvider(): array
    {
        return [
            'one second before window end' => [
                'flaky', 'mac', self::row(route: 'any'),
                self::CREATED + 24 * 3600 - 1,
                self::expected('retry_now', 'mac', null, flaky: 1),
            ],
            'exactly at window end' => [
                'flaky', 'mac', self::row(route: 'any'),
                self::CREATED + 24 * 3600,
                self::expected('failed', 'any', null, flaky: 1),
            ],
            'one second after window end' => [
                'flaky', 'mac', self::row(route: 'any'),
                self::CREATED + 24 * 3600 + 1,
                self::expected('failed', 'any', null, flaky: 1),
            ],
        ];
    }

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('attemptBackstopProvider')]
    public function testDecideAttemptBackstop(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function attemptBackstopProvider(): array
    {
        return [
            'transport at 19 attempts is still routed' => [
                'transport', 'mac', self::row(route: 'any', attempts: 19), self::NOW,
                self::expected('handed', 'actions', null, transport: 1),
            ],
            'transport at 20 attempts fails' => [
                'transport', 'mac', self::row(route: 'any', attempts: 20), self::NOW,
                self::expected('failed', 'any', null, transport: 1),
            ],
            'flaky at 21 attempts fails' => [
                'flaky', 'mac', self::row(route: 'any', attempts: 21), self::NOW,
                self::expected('failed', 'any', null, flaky: 1),
            ],
            'usage_limit ignores the backstop' => [
                'usage_limit', 'mac', self::row(route: 'any', attempts: 20), self::NOW,
                self::expected('waiting', 'any', self::NOW + 60 * 60, usageWaits: 1, refund: 1),
            ],
        ];
    }

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('gatesKeepCauseCountersProvider')]
    public function testDecideGatesKeepCauseCounters(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function gatesKeepCauseCountersProvider(): array
    {
        $windowEnd = self::CREATED + 24 * 3600;

        return [
            'window end: auth keeps the appended host' => [
                'auth', 'mac', self::row(route: 'any'), $windowEnd,
                self::expected('failed', 'any', null, authFailedHosts: 'mac'),
            ],
            // Append order would emit 'actions,mac'; the canonical HOSTS order is 'mac,actions'.
            'window end: auth re-emits prior and appended hosts in canonical order' => [
                'auth', 'mac', self::row(route: 'any', authFailedHosts: 'actions'), $windowEnd,
                self::expected('failed', 'any', null, authFailedHosts: 'mac,actions'),
            ],
            'window end: transport keeps the incremented count' => [
                'transport', 'mac', self::row(route: 'any', transport: 2), $windowEnd,
                self::expected('failed', 'any', null, transport: 3),
            ],
            'window end: usage_limit keeps the wait and refund' => [
                'usage_limit', 'mac', self::row(route: 'any'), $windowEnd,
                self::expected('failed', 'any', null, usageWaits: 1, refund: 1),
            ],
            'window end: prompt passes counters through unchanged' => [
                'prompt', 'mac', self::row(route: 'any', flaky: 2, transport: 1), $windowEnd,
                self::expected('failed', 'any', null, flaky: 2, transport: 1),
            ],
            'attempt backstop: auth keeps the appended host' => [
                'auth', 'mac', self::row(route: 'any', attempts: 20), self::NOW,
                self::expected('failed', 'any', null, authFailedHosts: 'mac'),
            ],
            'attempt backstop: prompt passes counters through unchanged' => [
                'prompt', 'mac', self::row(route: 'any', attempts: 20, flaky: 2), self::NOW,
                self::expected('failed', 'any', null, flaky: 2),
            ],
        ];
    }

    public function testDecidePromptAlwaysFails(): void
    {
        $row = self::row(route: 'any', flaky: 2, transport: 1, usageWaits: 1, authFailedHosts: 'actions');

        self::assertSame(
            self::expected('failed', 'any', null, flaky: 2, transport: 1, usageWaits: 1, authFailedHosts: 'actions'),
            ParkPolicy::decide('prompt', 'mac', $row, null, self::NOW),
        );
    }

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('authRoutingProvider')]
    public function testDecideAuthRouting(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function authRoutingProvider(): array
    {
        return [
            'mac fails first, handed to actions' => [
                'auth', 'mac', self::row(route: 'any', authFailedHosts: ''), self::NOW,
                self::expected('handed', 'actions', null, authFailedHosts: 'mac'),
            ],
            'actions fails first, handed to mac' => [
                'auth', 'actions', self::row(route: 'any', authFailedHosts: ''), self::NOW,
                self::expected('handed', 'mac', null, authFailedHosts: 'actions'),
            ],
            'mac fails after actions failed' => [
                'auth', 'mac', self::row(route: 'any', authFailedHosts: 'actions'), self::NOW,
                self::expected('failed', 'any', null, authFailedHosts: 'mac,actions'),
            ],
            'actions fails after mac failed' => [
                'auth', 'actions', self::row(route: 'any', authFailedHosts: 'mac'), self::NOW,
                self::expected('failed', 'any', null, authFailedHosts: 'mac,actions'),
            ],
            'mac fails twice, other host still untried' => [
                'auth', 'mac', self::row(route: 'any', authFailedHosts: 'mac'), self::NOW,
                self::expected('handed', 'actions', null, authFailedHosts: 'mac'),
            ],
            'unknown token in prior set is dropped' => [
                'auth', 'mac', self::row(route: 'any', authFailedHosts: 'bogus'), self::NOW,
                self::expected('handed', 'actions', null, authFailedHosts: 'mac'),
            ],
        ];
    }

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('transportThresholdProvider')]
    public function testDecideTransportThresholds(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function transportThresholdProvider(): array
    {
        return [
            'n=1 handed' => [
                'transport', 'mac', self::row(route: 'any', transport: 0), self::NOW,
                self::expected('handed', 'actions', null, transport: 1),
            ],
            'n=1 from actions handed to mac' => [
                'transport', 'actions', self::row(route: 'any', transport: 0), self::NOW,
                self::expected('handed', 'mac', null, transport: 1),
            ],
            'n=3 last handoff' => [
                'transport', 'mac', self::row(route: 'any', transport: 2), self::NOW,
                self::expected('handed', 'actions', null, transport: 3),
            ],
            'n=4 first backoff' => [
                'transport', 'mac', self::row(route: 'any', transport: 3), self::NOW,
                self::expected('backoff', 'any', self::NOW + 30 * 60, transport: 4),
            ],
            'n=7 last backoff' => [
                'transport', 'mac', self::row(route: 'any', transport: 6), self::NOW,
                self::expected('backoff', 'any', self::NOW + 30 * 60, transport: 7),
            ],
            'n=8 fails' => [
                'transport', 'mac', self::row(route: 'any', transport: 7), self::NOW,
                self::expected('failed', 'any', null, transport: 8),
            ],
            'n=4 backoff leaves other counters alone' => [
                'transport', 'mac',
                self::row(route: 'any', transport: 3, flaky: 2, usageWaits: 1, authFailedHosts: 'actions'),
                self::NOW,
                self::expected('backoff', 'any', self::NOW + 30 * 60, flaky: 2, transport: 4, usageWaits: 1, authFailedHosts: 'actions'),
            ],
        ];
    }

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('flakyThresholdProvider')]
    public function testDecideFlakyThresholds(string $cause, string $host, array $row, int $now, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide($cause, $host, $row, null, $now));
    }

    /**
     * @return array<string, array{string, string, Row, int, Decision}>
     */
    public static function flakyThresholdProvider(): array
    {
        return [
            'n=1 retry on claiming host' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 0), self::NOW,
                self::expected('retry_now', 'mac', null, flaky: 1),
            ],
            'n=1 retry stays on actions' => [
                'flaky', 'actions', self::row(route: 'any', flaky: 0), self::NOW,
                self::expected('retry_now', 'actions', null, flaky: 1),
            ],
            'n=2 last retry' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 1), self::NOW,
                self::expected('retry_now', 'mac', null, flaky: 2),
            ],
            'n=3 handed' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 2), self::NOW,
                self::expected('handed', 'actions', null, flaky: 3),
            ],
            'n=3 from actions handed to mac' => [
                'flaky', 'actions', self::row(route: 'any', flaky: 2), self::NOW,
                self::expected('handed', 'mac', null, flaky: 3),
            ],
            'n=4 first backoff' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 3), self::NOW,
                self::expected('backoff', 'any', self::NOW + 30 * 60, flaky: 4),
            ],
            'n=5 last backoff' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 4), self::NOW,
                self::expected('backoff', 'any', self::NOW + 30 * 60, flaky: 5),
            ],
            'n=6 fails' => [
                'flaky', 'mac', self::row(route: 'any', flaky: 5), self::NOW,
                self::expected('failed', 'any', null, flaky: 6),
            ],
        ];
    }

    // ---------------------------------------------------------------
    // decide(): usage_limit wait
    // ---------------------------------------------------------------

    /**
     * @param Row $row
     * @param Decision $expected
     */
    #[DataProvider('usageLimitProvider')]
    public function testDecideUsageLimitWait(?int $reset, int $now, array $row, array $expected): void
    {
        self::assertSame($expected, ParkPolicy::decide('usage_limit', 'mac', $row, $reset, $now));
    }

    /**
     * @return array<string, array{?int, int, Row, Decision}>
     */
    public static function usageLimitProvider(): array
    {
        return [
            'null reset uses default wait' => [
                null, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 60 * 60, usageWaits: 1, refund: 1),
            ],
            'future reset inside horizon adds slack' => [
                self::NOW + 1_800, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 1_800 + 120, usageWaits: 1, refund: 1),
            ],
            'reset equal to now is not future' => [
                self::NOW, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 60 * 60, usageWaits: 1, refund: 1),
            ],
            'reset in the past falls back' => [
                self::NOW - 60, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 60 * 60, usageWaits: 1, refund: 1),
            ],
            'reset exactly at horizon is trusted' => [
                self::NOW + 6 * 3600, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 6 * 3600 + 120, usageWaits: 1, refund: 1),
            ],
            'reset one second past horizon falls back' => [
                self::NOW + 6 * 3600 + 1, self::NOW, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::NOW + 60 * 60, usageWaits: 1, refund: 1),
            ],
            'prior waits increment and other counters pass through' => [
                null, self::NOW, self::row(route: 'actions', usageWaits: 4, flaky: 2, transport: 1),
                self::expected('waiting', 'actions', self::NOW + 60 * 60, flaky: 2, transport: 1, usageWaits: 5, refund: 1),
            ],
            'default wait landing exactly on window end still waits' => [
                null, self::CREATED + 24 * 3600 - 3600, self::row(route: 'actions'),
                self::expected('waiting', 'actions', self::CREATED + 24 * 3600, usageWaits: 1, refund: 1),
            ],
            'default wait one second past window end fails' => [
                null, self::CREATED + 24 * 3600 - 3600 + 1, self::row(route: 'actions'),
                self::expected('failed', 'actions', null, usageWaits: 1, refund: 1),
            ],
            'reset slack pushes past window end and fails' => [
                self::CREATED + 24 * 3600 - 100, self::CREATED + 24 * 3600 - 1_000, self::row(route: 'actions'),
                self::expected('failed', 'actions', null, usageWaits: 1, refund: 1),
            ],
        ];
    }

    // ---------------------------------------------------------------
    // notifyStateFor()
    // ---------------------------------------------------------------

    #[DataProvider('notifyStateProvider')]
    public function testNotifyStateFor(string $outcome, string $route, ?string $prior, string $expected): void
    {
        self::assertSame($expected, ParkPolicy::notifyStateFor($outcome, $route, $prior));
    }

    /**
     * @return array<string, array{string, string, ?string, string}>
     */
    public static function notifyStateProvider(): array
    {
        return [
            'retry_now is retrying' => ['retry_now', 'mac', null, 'retrying'],
            'backoff is retrying' => ['backoff', 'any', null, 'retrying'],
            'waiting is waiting' => ['waiting', 'actions', null, 'waiting'],
            'handed to mac' => ['handed', 'mac', null, 'handed_mac'],
            'handed to actions' => ['handed', 'actions', null, 'handed_actions'],
            'handed to any non-mac route' => ['handed', 'any', null, 'handed_actions'],
            'failed is unavailable' => ['failed', 'mac', null, 'unavailable'],
            'done with no prior is posted' => ['done', 'mac', null, 'posted'],
            'done after posted is posted' => ['done', 'mac', 'posted', 'posted'],
            'done after recovered is posted' => ['done', 'mac', 'recovered', 'posted'],
            'done after retrying recovers' => ['done', 'mac', 'retrying', 'recovered'],
            'done after waiting recovers' => ['done', 'mac', 'waiting', 'recovered'],
            'done after handed_mac recovers' => ['done', 'mac', 'handed_mac', 'recovered'],
            'done after handed_actions recovers' => ['done', 'mac', 'handed_actions', 'recovered'],
            'done after unavailable recovers' => ['done', 'mac', 'unavailable', 'recovered'],
        ];
    }

    #[DataProvider('unknownOutcomeProvider')]
    public function testNotifyStateForRejectsUnknownOutcome(string $outcome): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Unknown park outcome$/');

        ParkPolicy::notifyStateFor($outcome, 'mac', null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownOutcomeProvider(): array
    {
        return [
            'empty string' => [''],
            'wrong case' => ['Handed'],
            'notify state, not an outcome' => ['posted'],
        ];
    }
}
