<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source-content guard for the canonical-domain redirect block in
 * ibl5/.htaccess (the anti-mirroring guard, lines 18-23). Mirrors the
 * source-content-guard shape of SimRecapQueueGuardTest: it file_get_contents
 * the target and asserts with preg_match, never shelling out to Apache.
 *
 * The block is three consecutive `RewriteCond %{HTTP_HOST} !<pattern> [NC]`
 * lines followed by a single `RewriteRule ^ https://iblhoops.net%{REQUEST_URI}
 * [R=301,L]`. mod_rewrite ANDs consecutive RewriteConds, and each cond here is
 * negated, so a host is redirected iff it matches NONE of the three patterns
 * (i.e. it is on the whitelist iff it matches AT LEAST ONE). This test models
 * exactly that rule: it extracts the three patterns from the live file rather
 * than hardcoding copies, so a future edit that adds, drops, or weakens a cond
 * is caught here instead of silently changing the guard.
 *
 * What this test cannot see: a mod_rewrite PARSE failure. That is covered by
 * the ephemeral AllowOverride-All Apache container in the plan's Phase 3.3 —
 * the worktree Docker stack sets AllowOverride None (Dockerfile:37) and never
 * reads this file at all.
 */
final class HtaccessCanonicalDomainGuardTest extends TestCase
{
    /** @var list<string> PCRE patterns extracted from the three HTTP_HOST conds. */
    private array $patterns;

    protected function setUp(): void
    {
        // From tests/WideUnit/Scripts/ this resolves to ibl5/.htaccess — the
        // same relative walk the sibling guards use.
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../.htaccess');

        // Extract every `RewriteCond %{HTTP_HOST} !<pattern> [NC]`. The `!` is
        // the mod_rewrite negation; strip it and apply the `i` flag for [NC].
        preg_match_all(
            '/^RewriteCond\s+%\{HTTP_HOST\}\s+!(\S+)\s+\[NC\]/m',
            $htaccess,
            $matches
        );

        $this->patterns = array_map(
            static fn (string $raw): string => '#' . $raw . '#i',
            $matches[1]
        );
    }

    /** True when the host would be 301-redirected (matches none of the conds). */
    private function isRedirected(string $host): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $host) === 1) {
                return false; // matched a whitelist cond → not redirected
            }
        }

        return true;
    }

    public function testExactlyThreeHostCondsAreExtracted(): void
    {
        // Pins the extraction contract: the redirect fires iff all three
        // negated conds match. A count other than three means the rule chain
        // changed and this test's model is stale — fail loudly.
        self::assertCount(3, $this->patterns, 'Expected exactly three %{HTTP_HOST} RewriteConds');
    }

    #[DataProvider('allowedHostProvider')]
    public function testWhitelistedHostsAreNotRedirected(string $host): void
    {
        self::assertFalse($this->isRedirected($host), "$host must be whitelisted (not redirected)");
    }

    #[DataProvider('redirectedHostProvider')]
    public function testUnauthorizedHostsAreRedirected(string $host): void
    {
        self::assertTrue($this->isRedirected($host), "$host must be redirected to the canonical domain");
    }

    /**
     * Branch-specific negative-path assertion for this change: the `ibl6` arm
     * was removed from the apex cond (`((www|ibl6)\.)?` -> `(www\.)?`) because
     * production evidence proved ibl6.iblhoops.net is served by its own vhost,
     * never by these rules. The load-bearing proof was the boxscore fingerprint
     * `curl -sI https://ibl6.iblhoops.net/2008-03-10-game-7/boxscore` ->
     * `location: https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-03-10&game=7`,
     * a Location only /home/iblhoops/ibl6.iblhoops.net/.htaccess can emit.
     * A request bearing Host: ibl6.iblhoops.net that reaches the MAIN vhost is
     * now 301'd to the canonical host — strictly safer than serving it the app.
     */
    public function testIbl6SubdomainIsNoLongerWhitelisted(): void
    {
        self::assertTrue(
            $this->isRedirected('ibl6.iblhoops.net'),
            'ibl6.iblhoops.net must now be redirected — the ibl6 whitelist arm was removed'
        );
    }

    /** @return array<string, array{string}> */
    public static function allowedHostProvider(): array
    {
        return [
            'apex'            => ['iblhoops.net'],
            'www'             => ['www.iblhoops.net'],
            'uppercase apex'  => ['IBLHOOPS.NET'],
            'localhost'       => ['localhost'],
            'main.localhost'  => ['main.localhost'],
            'loopback IP'     => ['127.0.0.1'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function redirectedHostProvider(): array
    {
        return [
            'foreign domain'        => ['evil.com'],
            'known mirror domain'   => ['zeidconsulting.com'],
            'apex suffix spoof'     => ['iblhoops.net.evil.com'],
            'missing dot after www' => ['wwwiblhoops.net'],
            'www suffix spoof'      => ['www.iblhoops.net.evil.com'],
            'extra label prefix'    => ['sub.www.iblhoops.net'],
        ];
    }
}
