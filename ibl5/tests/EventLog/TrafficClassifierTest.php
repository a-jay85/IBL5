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

    public function testNullUserAgentAnonymousIsCrawler(): void
    {
        // No browser omits its UA, so a null UA on an anonymous request is a crawler signal.
        self::assertSame('crawler', TrafficClassifier::classify(null, null, null));
    }

    public function testEmptyUserAgentAnonymousIsCrawler(): void
    {
        // An empty UA is a crawler signal, same as a null UA.
        self::assertSame('crawler', TrafficClassifier::classify(null, '', null));
    }

    public function testWhitespaceOnlyUserAgentAnonymousIsCrawler(): void
    {
        foreach ([' ', "\t", "  \n "] as $userAgent) {
            self::assertSame('crawler', TrafficClassifier::classify(null, $userAgent, null));
        }
    }

    public function testAuthenticatedWithBlankUserAgentIsAuthenticated(): void
    {
        // Rule 2 (authenticated) must win over the blank-UA arm of rule 3.
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', null, null));
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', '', null));
        self::assertSame('authenticated', TrafficClassifier::classify('testgm', '   ', null));
    }

    public function testBlankUserAgentWithOpNewUserIsCrawler(): void
    {
        // Rule 3 (crawler) outranks rule 4 (spam), as it already does for a bot UA.
        self::assertSame('crawler', TrafficClassifier::classify(null, null, 'new_user'));
        self::assertSame('crawler', TrafficClassifier::classify(null, '', 'new_user'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('newCrawlerPatternProvider')]
    public function testNewCrawlerPatternIsCrawler(string $userAgent): void
    {
        self::assertSame('crawler', TrafficClassifier::classify(null, $userAgent, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function newCrawlerPatternProvider(): array
    {
        return [
            'go-http-client' => ['Go-http-client/1.1'],
            'axios' => ['axios/1.7.2'],
            'scrapy' => ['Scrapy/2.11.2 (+https://scrapy.org)'],
            'httpclient' => ['Apache-HttpClient/4.5.14 (Java/17.0.2)'],
            'java/' => ['Java/1.8.0_392'],
            'okhttp' => ['okhttp/4.12.0'],
            'node-fetch' => ['node-fetch/1.0 (+https://github.com/bitinn/node-fetch)'],
            'libwww' => ['libwww-perl/6.72'],
            'headless' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36'],
            'headless-generic' => ['SomeHeadless/1.0'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('realBrowserUaProvider')]
    public function testRealBrowserUaIsAnonymousHuman(string $userAgent): void
    {
        // False-positive guard: no mainstream browser UA may match a crawler pattern.
        self::assertSame('anonymous-human', TrafficClassifier::classify(null, $userAgent, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function realBrowserUaProvider(): array
    {
        return [
            'chrome-windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'],
            'safari-macos' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15'],
            'firefox-windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0'],
            'edge-windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0'],
            'safari-iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'],
            'chrome-android' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36'],
            'samsung-internet' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36'],
        ];
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
