<?php

declare(strict_types=1);

namespace Auth;

/**
 * Fail-closed token resolution and authorization for the demo-login endpoint.
 *
 * The demo-login magic link (demo-login.php) grants a read-only "Warriors GM"
 * session. Historically the expected token was the trivial literal 'demo',
 * hardcoded in the untracked config.php — anyone reading the source could
 * authenticate. This gate closes that hole:
 *
 * 1. The expected token is sourced from the DEMO_LOGIN_TOKEN environment
 *    variable first, falling back to the DEMO_LOGIN_TOKEN constant only if the
 *    env var is unset. This lets deployments configure a strong secret without
 *    editing the legacy config.php.
 * 2. Demo login is DISABLED (fails closed) when the resolved token is empty or
 *    equals the known-weak literal 'demo' — even if a stale config.php still
 *    defines 'demo'.
 * 3. Otherwise the supplied token is compared with the constant-time
 *    hash_equals().
 *
 * Pure logic, no I/O beyond getenv() — unit-testable without a session or DB.
 */
final class DemoLoginGate
{
    /**
     * The historical guessable default. Treated as "demo login disabled".
     */
    public const WEAK_TOKEN = 'demo';

    /**
     * Resolve the expected demo-login token: env var first, constant fallback.
     */
    public static function resolveExpectedToken(): string
    {
        $envToken = getenv('DEMO_LOGIN_TOKEN');
        if (is_string($envToken) && $envToken !== '') {
            return $envToken;
        }

        if (defined('DEMO_LOGIN_TOKEN')) {
            $constToken = constant('DEMO_LOGIN_TOKEN');
            if (is_string($constToken)) {
                return $constToken;
            }
        }

        return '';
    }

    /**
     * Whether demo login is enabled — i.e. a non-empty, non-weak token is
     * configured. An empty or 'demo' token means the feature is fail-closed.
     */
    public static function isEnabled(string $expectedToken): bool
    {
        return $expectedToken !== '' && !hash_equals(self::WEAK_TOKEN, $expectedToken);
    }

    /**
     * Whether the supplied token authorizes a demo session. Returns false when
     * demo login is disabled, regardless of what was supplied.
     */
    public static function isAuthorized(string $expectedToken, string $providedToken): bool
    {
        if (!self::isEnabled($expectedToken)) {
            return false;
        }

        return hash_equals($expectedToken, $providedToken);
    }
}
