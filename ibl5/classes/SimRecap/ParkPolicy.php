<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Pure routing table for a parked sim recap: given the failure cause, the
 * claiming host, and the row's counters, decide what happens next.
 *
 * No DB handle and no I/O. SimSummaryRepository reads the row, asks decide(),
 * and writes the answer in one guarded UPDATE. The whitelists here mirror the
 * enum/set column types added by migration 195, so a value outside them is
 * rejected twice: here, and again by the schema under strict mode.
 *
 * Rows are checked top to bottom; the first match wins (H = claiming host,
 * O = the other host):
 *   older than WINDOW_HOURS                     -> failed
 *   attempts >= ATTEMPT_BACKSTOP (not usage)    -> failed
 *   prompt                                      -> failed
 *   auth, O already failed auth                 -> failed
 *   auth                                        -> handed to O
 *   transport n <= 3 / 4..7 / >= 8              -> handed to O / backoff / failed
 *   flaky n <= 2 / 3 / 4..5 / >= 6              -> retry_now on H / handed to O / backoff / failed
 *   usage_limit                                 -> waiting until the reset (never handed, never counted)
 */
final class ParkPolicy
{
    public const CAUSES = ['flaky', 'usage_limit', 'auth', 'transport', 'prompt'];

    public const HOSTS = ['mac', 'actions'];

    public const NOTIFY_STATES = ['retrying', 'waiting', 'handed_mac', 'handed_actions', 'posted', 'recovered', 'unavailable'];

    /** A recap for tonight's sim stops being useful by the next sim. */
    public const WINDOW_HOURS = 24;

    /** Protection against a policy bug; per-cause budgets normally end a row first. */
    public const ATTEMPT_BACKSTOP = 20;

    public const BACKOFF_MINUTES = 30;

    /** Usage-limit wait when the reset time is missing or implausible. */
    public const USAGE_DEFAULT_WAIT_MINUTES = 60;

    /** A parsed reset further out than this is treated as unparseable. */
    public const USAGE_MAX_RESET_HOURS = 6;

    /** Slack after the reported reset before the retry. */
    public const USAGE_RESET_SLACK_SECONDS = 120;

    /** Notify states that mean an earlier problem was reported for this sim. */
    private const FAILURE_NOTIFY_STATES = ['retrying', 'waiting', 'handed_mac', 'handed_actions', 'unavailable'];

    public static function assertCause(string $cause): void
    {
        if (!in_array($cause, self::CAUSES, true)) {
            throw new \InvalidArgumentException('Unknown sim recap failure cause');
        }
    }

    public static function assertHost(string $host): void
    {
        if (!in_array($host, self::HOSTS, true)) {
            throw new \InvalidArgumentException('Unknown sim recap host');
        }
    }

    public static function assertNotifyState(string $state): void
    {
        if (!in_array($state, self::NOTIFY_STATES, true)) {
            throw new \InvalidArgumentException('Unknown sim recap notify state');
        }
    }

    public static function otherHost(string $host): string
    {
        self::assertHost($host);

        return $host === 'mac' ? 'actions' : 'mac';
    }

    /**
     * @param array{route: string, attempts: int, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, created_epoch: int} $row
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    public static function decide(string $cause, string $host, array $row, ?int $resetEpoch, int $nowEpoch): array
    {
        self::assertCause($cause);
        self::assertHost($host);
        $other = self::otherHost($host);
        $counters = self::countFailure($cause, $host, $row);
        $windowEnd = $row['created_epoch'] + self::WINDOW_HOURS * 3600;
        $route = $row['route'];

        if ($nowEpoch >= $windowEnd) {
            return self::outcome('failed', $route, null, $counters);
        }
        if ($cause !== 'usage_limit' && $row['attempts'] >= self::ATTEMPT_BACKSTOP) {
            return self::outcome('failed', $route, null, $counters);
        }

        // assertCause() admits only the five CAUSES, so the default arm is usage_limit.
        return match ($cause) {
            'prompt' => self::decidePrompt($route, $counters),
            'auth' => self::decideAuth($other, $row['auth_failed_hosts'], $route, $counters),
            'transport' => self::decideTransport($other, $nowEpoch, $route, $counters),
            'flaky' => self::decideFlaky($host, $other, $nowEpoch, $route, $counters),
            default => self::decideUsageLimit($resetEpoch, $nowEpoch, $windowEnd, $route, $counters),
        };
    }

    /**
     * The row's counters after this failure. Increments happen once, here, before any gate.
     *
     * @param array{route: string, attempts: int, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, created_epoch: int} $row
     * @return array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}
     */
    private static function countFailure(string $cause, string $host, array $row): array
    {
        $counters = [
            'flaky_failures' => $row['flaky_failures'],
            'transport_failures' => $row['transport_failures'],
            'usage_waits' => $row['usage_waits'],
            'auth_failed_hosts' => $row['auth_failed_hosts'],
            'attempts_refund' => 0,
        ];

        if ($cause === 'flaky') {
            $counters['flaky_failures']++;
        } elseif ($cause === 'transport') {
            $counters['transport_failures']++;
        } elseif ($cause === 'usage_limit') {
            $counters['usage_waits']++;
            $counters['attempts_refund'] = 1;
        } elseif ($cause === 'auth') {
            $hosts = self::parseHosts($row['auth_failed_hosts']);
            $hosts[] = $host;
            $counters['auth_failed_hosts'] = implode(',', array_values(array_intersect(self::HOSTS, $hosts)));
        }

        return $counters;
    }

    /**
     * @return list<string>
     */
    private static function parseHosts(string $csv): array
    {
        return $csv === '' ? [] : explode(',', $csv);
    }

    /**
     * @param 'retry_now'|'handed'|'backoff'|'waiting'|'failed' $outcome
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function outcome(string $outcome, string $route, ?int $blockedUntilEpoch, array $counters): array
    {
        return [
            'outcome' => $outcome,
            'route' => $route,
            'blockedUntilEpoch' => $blockedUntilEpoch,
            'counters' => $counters,
        ];
    }

    /**
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function decidePrompt(string $route, array $counters): array
    {
        return self::outcome('failed', $route, null, $counters);
    }

    /**
     * Fail when the other host already failed auth for this row, else hand to it.
     *
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function decideAuth(string $other, string $priorAuthFailedHosts, string $route, array $counters): array
    {
        if (in_array($other, self::parseHosts($priorAuthFailedHosts), true)) {
            return self::outcome('failed', $route, null, $counters);
        }

        return self::outcome('handed', $other, null, $counters);
    }

    /**
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function decideTransport(string $other, int $nowEpoch, string $route, array $counters): array
    {
        $n = $counters['transport_failures'];
        if ($n <= 3) {
            return self::outcome('handed', $other, null, $counters);
        }
        if ($n <= 7) {
            return self::backoff($nowEpoch, $counters);
        }

        return self::outcome('failed', $route, null, $counters);
    }

    /**
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function decideFlaky(string $host, string $other, int $nowEpoch, string $route, array $counters): array
    {
        $n = $counters['flaky_failures'];
        if ($n <= 2) {
            return self::outcome('retry_now', $host, null, $counters);
        }
        if ($n === 3) {
            return self::outcome('handed', $other, null, $counters);
        }
        if ($n <= 5) {
            return self::backoff($nowEpoch, $counters);
        }

        return self::outcome('failed', $route, null, $counters);
    }

    /**
     * Wait for the reported reset when it is plausible, else the default wait; fail when that
     * wait would end after the window.
     *
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'retry_now'|'handed'|'backoff'|'waiting'|'failed', route: string, blockedUntilEpoch: ?int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function decideUsageLimit(?int $resetEpoch, int $nowEpoch, int $windowEnd, string $route, array $counters): array
    {
        $latestReset = $nowEpoch + self::USAGE_MAX_RESET_HOURS * 3600;
        $blocked = ($resetEpoch !== null && $resetEpoch > $nowEpoch && $resetEpoch <= $latestReset)
            ? $resetEpoch + self::USAGE_RESET_SLACK_SECONDS
            : $nowEpoch + self::USAGE_DEFAULT_WAIT_MINUTES * 60;
        if ($blocked > $windowEnd) {
            return self::outcome('failed', $route, null, $counters);
        }

        return self::outcome('waiting', $route, $blocked, $counters);
    }

    /**
     * Map a park outcome (or the 'done' event) to the #admin-chat state it announces.
     */
    public static function notifyStateFor(string $outcome, string $route, ?string $prior): string
    {
        return match ($outcome) {
            'retry_now', 'backoff' => 'retrying',
            'waiting' => 'waiting',
            'handed' => $route === 'mac' ? 'handed_mac' : 'handed_actions',
            'failed' => 'unavailable',
            'done' => in_array($prior, self::FAILURE_NOTIFY_STATES, true) ? 'recovered' : 'posted',
            default => throw new \InvalidArgumentException('Unknown park outcome'),
        };
    }

    /**
     * @param array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int} $counters
     * @return array{outcome: 'backoff', route: string, blockedUntilEpoch: int, counters: array{flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, attempts_refund: int}}
     */
    private static function backoff(int $nowEpoch, array $counters): array
    {
        return [
            'outcome' => 'backoff',
            'route' => 'any',
            'blockedUntilEpoch' => $nowEpoch + self::BACKOFF_MINUTES * 60,
            'counters' => $counters,
        ];
    }
}
