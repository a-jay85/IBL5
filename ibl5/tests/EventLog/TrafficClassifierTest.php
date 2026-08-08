<?php

declare(strict_types=1);

namespace Tests\EventLog;

use EventLog\TrafficClassifier;
use PHPUnit\Framework\TestCase;

final class TrafficClassifierTest extends TestCase
{
    // --- Happy path: one test per class ---

    public function testSmokeTestUaReturnsSmokeTest(): void
    {
        self::assertSame('smoke-test', TrafficClassifier::classify(null, 'IBL5-smoke/1.0', null));
    }

    public function testAuthenticatedUsernameReturnsAuthenticated(): void
    {
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', 'Mozilla/5.0', null));
    }

    public function testCrawlerUaReturnsCrawler(): void
    {
        self::assertSame('crawler', TrafficClassifier::classify(null, 'Googlebot/2.1', null));
    }

    public function testAnonymousOpNewUserReturnsSpam(): void
    {
        self::assertSame('spam', TrafficClassifier::classify(null, 'Mozilla/5.0', 'new_user'));
    }

    public function testDefaultReturnsAnonymousHuman(): void
    {
        self::assertSame('anonymous-human', TrafficClassifier::classify(null, 'Mozilla/5.0', null));
    }

    // --- Precedence negative paths ---

    public function testAuthenticatedUserWithCrawlerUaIsAuthenticated(): void
    {
        // Rule 2 (authenticated) must win over rule 3 (crawler).
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', 'Googlebot/2.1', null));
    }

    public function testSmokeTestWithUsernameIsSmokeTest(): void
    {
        // Rule 1 (smoke-test) must win over rule 2 (authenticated).
        self::assertSame('smoke-test', TrafficClassifier::classify('testgm', 'IBL5-smoke/2.0', null));
    }

    public function testAuthenticatedWithOpNewUserIsAuthenticated(): void
    {
        // Rule 2 (authenticated) must win over rule 4 (spam).
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', 'Mozilla/5.0', 'new_user'));
    }

    public function testNullUserAgentAnonymousIsAnonymousHuman(): void
    {
        // A null UA cannot match crawler patterns — defaults to anonymous-human.
        self::assertSame('anonymous-human', TrafficClassifier::classify(null, null, null));
    }

    public function testEmptyUserAgentAnonymousIsAnonymousHuman(): void
    {
        // An empty UA cannot match crawler patterns — defaults to anonymous-human.
        self::assertSame('anonymous-human', TrafficClassifier::classify(null, '', null));
    }

    // --- Crawler pattern coverage ---

    public function testNexus5BuildIsCrawler(): void
    {
        self::assertSame('crawler', TrafficClassifier::classify(null, 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N)', null));
    }

    public function testCurlUaIsCrawler(): void
    {
        // Must match 'curl/' not 'curly' — the slash is the discriminator.
        self::assertSame('crawler', TrafficClassifier::classify(null, 'curl/7.88.1', null));
    }

    public function testWordContainingCurlIsNotMatchedAsCrawler(): void
    {
        // 'curly' does NOT contain 'curl/' — should not be classified as crawler.
        self::assertSame('anonymous-human', TrafficClassifier::classify(null, 'curly-browser/1.0', null));
    }

    // --- Case-insensitivity ---

    public function testSmokeTestUaIsCaseInsensitive(): void
    {
        self::assertSame('smoke-test', TrafficClassifier::classify(null, 'ibl5-smoke/9.9', null));
    }

    public function testCrawlerUaIsCaseInsensitive(): void
    {
        self::assertSame('crawler', TrafficClassifier::classify(null, 'GOOGLEBOT/2.1', null));
    }

    // --- Closed-set / adversarial ---

    public function testAdversarialUaReturnsClosedSetLiteral(): void
    {
        $adversarialUa = "<script>alert(1)</script>'; DROP TABLE ibl_events;--";
        $result = TrafficClassifier::classify(null, $adversarialUa, null);

        self::assertContains($result, ['smoke-test', 'authenticated', 'crawler', 'spam', 'anonymous-human']);
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringNotContainsString('DROP', $result);
    }
}
