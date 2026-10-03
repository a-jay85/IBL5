<?php

declare(strict_types=1);

namespace Tests\DraftInfo;

use Module\ModuleRedirect;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DraftInfo redirect stubs via ModuleRedirect::passthroughUrl.
 *
 * No DraftInfoRedirect class exists; each stub calls ModuleRedirect::sendWithPassthrough
 * with a fixed target and whitelist. These tests assert the passthroughUrl logic
 * for the exact targets and whitelists the stubs use, plus structural file assertions.
 */
class DraftInfoRedirectTest extends TestCase
{
    private const HISTORY_TARGET = 'modules.php?name=DraftInfo&tab=history';
    private const ORDER_TARGET = 'modules.php?name=DraftInfo&tab=order';
    private const PICKS_TARGET = 'modules.php?name=DraftInfo&tab=picks';
    private const SAVE_ORDER_TARGET = 'modules.php?name=DraftInfo&op=save_order';

    /** @var array<string, callable(string): bool> */
    private array $historyValidators;

    protected function setUp(): void
    {
        $this->historyValidators = ['year' => 'ctype_digit', 'teamid' => 'ctype_digit'];
    }

    public function testProjectedDraftOrderRedirectsToOrderTab(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::ORDER_TARGET, [], []);
        $this->assertSame(self::ORDER_TARGET, $url);
    }

    public function testDraftPickLocatorRedirectsToPicksTab(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::PICKS_TARGET, [], []);
        $this->assertSame(self::PICKS_TARGET, $url);
    }

    public function testDraftHistoryRedirectsToHistoryTab(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::HISTORY_TARGET, ['year', 'teamid'], [], $this->historyValidators);
        $this->assertSame(self::HISTORY_TARGET, $url);
        $this->assertStringNotContainsString('year', $url);
        $this->assertStringNotContainsString('teamid', $url);
    }

    public function testDraftHistoryForwardsYearAndTeamId(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['year' => '2020', 'teamid' => '5'],
            $this->historyValidators
        );
        $this->assertSame(self::HISTORY_TARGET . '&year=2020&teamid=5', $url);
    }

    public function testDraftHistoryDropsNonWhitelistedParams(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['year' => '2020', 'name' => 'Evil', 'foo' => 'bar', 'op' => 'api'],
            $this->historyValidators
        );
        $this->assertStringContainsString('year=2020', $url);
        $this->assertStringNotContainsString('foo', $url);
        $this->assertStringNotContainsString('Evil', $url);
        $this->assertStringNotContainsString('op', $url);
    }

    public function testDraftHistoryDropsNonNumericYear(): void
    {
        // ctype_digit rejects 'abc' — value is dropped, not cast to 0
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['year' => 'abc'],
            $this->historyValidators
        );
        $this->assertSame(self::HISTORY_TARGET, $url);
        $this->assertStringNotContainsString('year', $url);
    }

    public function testDraftHistoryDropsNegativeYear(): void
    {
        // ctype_digit rejects '-5' (hyphen is not a digit)
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['year' => '-5'],
            $this->historyValidators
        );
        $this->assertSame(self::HISTORY_TARGET, $url);
        $this->assertStringNotContainsString('year', $url);
    }

    public function testArrayParamIsDropped(): void
    {
        // Non-string value is dropped before validator runs
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['year' => ['x']],
            $this->historyValidators
        );
        $this->assertSame(self::HISTORY_TARGET, $url);
        $this->assertStringNotContainsString('year', $url);
    }

    public function testForwardedValueCannotInjectHeaderOrParams(): void
    {
        // CRLF and extra params are percent-encoded by http_build_query (RFC3986)
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['teamid' => "1&name=Evil\r\nX-Injected: 1"],
            $this->historyValidators
        );
        // ctype_digit rejects the value — it is dropped entirely
        $this->assertStringNotContainsString('teamid', $url);
        $this->assertStringNotContainsString('Evil', $url);
        $this->assertStringNotContainsString("\r", $url);
        $this->assertStringNotContainsString("\n", $url);
    }

    public function testSaveOrderForwardsWith307(): void
    {
        $path = __DIR__ . '/../../modules/ProjectedDraftOrder/index.php';
        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString("'modules.php?name=DraftInfo&op=save_order'", $content);
        $this->assertStringContainsString('307', $content);
    }

    public function testSaveOrderOpOnOtherLegacyModuleIsIgnored(): void
    {
        // DraftHistory stub passes only year/teamid whitelist, never op
        $url = ModuleRedirect::passthroughUrl(
            self::HISTORY_TARGET,
            ['year', 'teamid'],
            ['op' => 'save_order'],
            $this->historyValidators
        );
        $this->assertSame(self::HISTORY_TARGET, $url);
        $this->assertStringNotContainsString('op', $url);
    }

    public function testEveryLocationIsRelativeDraftInfoPath(): void
    {
        $cases = [
            [self::ORDER_TARGET, [], []],
            [self::PICKS_TARGET, [], []],
            [self::HISTORY_TARGET, ['year', 'teamid'], ['year' => '2020', 'teamid' => '5']],
            [self::HISTORY_TARGET, ['year', 'teamid'], ["1&name=Evil\r\n" => '1']],
        ];
        foreach ($cases as [$target, $whitelist, $request]) {
            $url = ModuleRedirect::passthroughUrl($target, $whitelist, $request, $this->historyValidators);
            $this->assertStringStartsWith('modules.php?name=DraftInfo', $url);
        }
    }

    public function testLegacyStubsDelegateToRedirectBuilder(): void
    {
        $stubs = [
            [__DIR__ . '/../../modules/DraftHistory/index.php', 'DraftInfo&tab=history'],
            [__DIR__ . '/../../modules/DraftPickLocator/index.php', 'DraftInfo&tab=picks'],
            [__DIR__ . '/../../modules/ProjectedDraftOrder/index.php', 'DraftInfo&tab=order'],
        ];
        foreach ($stubs as [$path, $target]) {
            $content = file_get_contents($path);
            $this->assertIsString($content);
            $this->assertStringContainsString('sendWithPassthrough', $content, "Stub $path must use sendWithPassthrough");
            $this->assertStringContainsString($target, $content, "Stub $path must target $target");
            $this->assertStringNotContainsString("header('Location", $content, "Stub $path must not use raw header()");
            $this->assertStringNotContainsString('PageLayout', $content, "Stub $path must not render a page");
            $this->assertStringNotContainsString('<html', $content, "Stub $path must not echo HTML");
        }
    }
}
