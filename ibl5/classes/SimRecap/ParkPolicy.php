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

        $counters = [
            'flaky_failures' => $row['flaky_failures'],
            'transport_failures' => $row['transport_failures'],
            'usage_waits' => $row['usage_waits'],
            'auth_failed_hosts' => $row['auth_failed_hosts'],
            'attempts_refund' => 0,
        ];
        $windowEnd = $row['created_epoch'] + self::WINDOW_HOURS * 3600;
        $route = $row['route'];
        $priorAuthHosts = [];

        if ($cause === 'flaky') {
            $counters['flaky_failures']++;
        } elseif ($cause === 'transport') {
            $counters['transport_failures']++;
        } elseif ($cause === 'usage_limit') {
            $counters['usage_waits']++;
            $counters['attempts_refund'] = 1;
        } elseif ($cause === 'auth') {
            $hosts = $row['auth_failed_hosts'] === '' ? [] : explode(',', $row['auth_failed_hosts']);
            $priorAuthHosts = $hosts;
            $hosts[] = $host;
            $counters['auth_failed_hosts'] = implode(',', array_values(array_intersect(self::HOSTS, $hosts)));
        }

        $failed = static fn (): array => [
            'outcome' => 'failed',
            'route' => $route,
            'blockedUntilEpoch' => null,
            'counters' => $counters,
        ];

        if ($nowEpoch >= $windowEnd) {
            return $failed();
        }
        if ($cause !== 'usage_limit' && $row['attempts'] >= self::ATTEMPT_BACKSTOP) {
            return $failed();
        }

        switch ($cause) {
            case 'prompt':
                return $failed();

            case 'auth':
                if (in_array($other, $priorAuthHosts, true)) {
                    return $failed();
                }

                return ['outcome' => 'handed', 'route' => $other, 'blockedUntilEpoch' => null, 'counters' => $counters];

            case 'transport':
                $n = $counters['transport_failures'];
                if ($n <= 3) {
                    return ['outcome' => 'handed', 'route' => $other, 'blockedUntilEpoch' => null, 'counters' => $counters];
                }
                if ($n <= 7) {
                    return self::backoff($nowEpoch, $counters);
                }

                return $failed();

            case 'flaky':
                $n = $counters['flaky_failures'];
                if ($n <= 2) {
                    return ['outcome' => 'retry_now', 'route' => $host, 'blockedUntilEpoch' => null, 'counters' => $counters];
                }
                if ($n === 3) {
                    return ['outcome' => 'handed', 'route' => $other, 'blockedUntilEpoch' => null, 'counters' => $counters];
                }
                if ($n <= 5) {
                    return self::backoff($nowEpoch, $counters);
                }

                return $failed();

            default: // usage_limit
                $latestReset = $nowEpoch + self::USAGE_MAX_RESET_HOURS * 3600;
                $blocked = ($resetEpoch !== null && $resetEpoch > $nowEpoch && $resetEpoch <= $latestReset)
                    ? $resetEpoch + self::USAGE_RESET_SLACK_SECONDS
                    : $nowEpoch + self::USAGE_DEFAULT_WAIT_MINUTES * 60;
                if ($blocked > $windowEnd) {
                    return $failed();
                }

                return ['outcome' => 'waiting', 'route' => $route, 'blockedUntilEpoch' => $blocked, 'counters' => $counters];
        }
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
