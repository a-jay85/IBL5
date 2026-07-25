<?php

declare(strict_types=1);

namespace EventLog;

/**
 * Classifies a web request into one of five traffic segments.
 *
 * Pure: no I/O, no globals. Returns a hardcoded literal from a closed set so
 * the output is always index-safe and never contains attacker-controlled bytes.
 *
 * NEVER use the returned value for authorization or rate-limiting — it is a
 * reporting label only.
 */
final class TrafficClassifier
{
    /** @var string[] */
    private const CRAWLER_PATTERNS = [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'curl/',
        'wget',
        'python-requests',
        'headlesschrome',
        'Nexus 5 Build/MRA58N',
    ];

    /**
     * Classify the request. Precedence is ordered and load-bearing — see D2.
     *
     * 1. smoke-test  — UA contains IBL5-smoke/ (our own synthetic traffic)
     * 2. authenticated — username is not null
     * 3. crawler     — UA matches a known bot pattern
     * 4. spam        — anonymous request with op=new_user
     * 5. anonymous-human — default
     */
    public static function classify(?string $username, ?string $userAgent, ?string $op): string
    {
        // Rule 1: smoke-test traffic (checked before authenticated so our own
        // synthetic traffic is not filed in the product-signal segment).
        if ($userAgent !== null && stripos($userAgent, 'IBL5-smoke/') !== false) {
            return 'smoke-test';
        }

        // Rule 2: authenticated user (checked before crawler so a real GM on an
        // unusual UA is never mis-classified as a bot).
        if ($username !== null) {
            return 'authenticated';
        }

        // Rule 3: known crawler/bot UA.
        if ($userAgent !== null && $userAgent !== '' && self::isCrawler($userAgent)) {
            return 'crawler';
        }

        // Rule 4: spam — anonymous hit to the registration-form route.
        if ($op === 'new_user') {
            return 'spam';
        }

        // Rule 5: default.
        return 'anonymous-human';
    }

    private static function isCrawler(string $userAgent): bool
    {
        foreach (self::CRAWLER_PATTERNS as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}
