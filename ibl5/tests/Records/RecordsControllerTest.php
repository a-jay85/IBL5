<?php

declare(strict_types=1);

namespace Tests\Records;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Records\RecordsController;

final class RecordsControllerTest extends TestCase
{
    /** @var list<string> */
    private array $calls = [];

    /** @var array<string, mixed>|null */
    private ?array $receivedQuery = null;

    private function makeController(): RecordsController
    {
        $renderer = function (string $key, string $body): \Closure {
            return function (array $query) use ($key, $body): string {
                $this->calls[] = $key;
                $this->receivedQuery = $query;
                return $body;
            };
        };

        return new RecordsController([
            RecordsController::TAB_ALLTIME => $renderer('alltime', '<p>ALLTIME-BODY</p>'),
            RecordsController::TAB_BYFRANCHISE => $renderer('byfranchise', '<p>BYFRANCHISE-BODY</p>'),
            RecordsController::TAB_THISSEASON => $renderer('thisseason', '<p>THISSEASON-BODY</p>'),
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function whitelistedTabProvider(): array
    {
        return [
            'alltime' => ['alltime'],
            'byfranchise' => ['byfranchise'],
            'thisseason' => ['thisseason'],
        ];
    }

    #[DataProvider('whitelistedTabProvider')]
    public function testResolveTabAcceptsEachWhitelistedTab(string $tab): void
    {
        self::assertSame($tab, RecordsController::resolveTab($tab));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTabProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'uppercase key' => ['ALLTIME'],
            'module name' => ['records'],
            'array from tab[]=' => [['alltime']],
            'integer' => [7],
            'markup' => ['alltime"><script>'],
        ];
    }

    #[DataProvider('invalidTabProvider')]
    public function testResolveTabFallsBackToAllTimeForInvalidInput(mixed $rawTab): void
    {
        self::assertSame(RecordsController::TAB_ALLTIME, RecordsController::resolveTab($rawTab));
    }

    public function testRenderTabBarMarksOnlyTheActiveTab(): void
    {
        $html = $this->makeController()->render('byfranchise', []);

        self::assertSame(1, substr_count($html, 'ibl-tab--active'));
        self::assertSame(1, substr_count($html, 'aria-current="page"'));
        self::assertMatchesRegularExpression(
            '/<a class="ibl-tab ibl-tab--active" href="[^"]*tab=byfranchise" data-tab="byfranchise" aria-current="page">By Franchise<\/a>/',
            $html
        );
    }

    public function testRenderTabBarLinksEachTabInOrderWithEscapedHref(): void
    {
        $html = $this->makeController()->render('alltime', []);

        preg_match_all('/href="([^"]*)"/', $html, $matches);
        self::assertSame([
            'modules.php?name=Records&amp;tab=alltime',
            'modules.php?name=Records&amp;tab=byfranchise',
            'modules.php?name=Records&amp;tab=thisseason',
        ], $matches[1]);
        self::assertStringStartsWith('<div class="ibl-tab-card"><div class="ibl-tabs">', $html);
        self::assertStringContainsString('>All-Time</a>', $html);
        self::assertStringContainsString('>This Season</a>', $html);
    }

    public function testRenderInvokesOnlyTheActiveTabRenderer(): void
    {
        $html = $this->makeController()->render('thisseason', []);

        self::assertSame(['thisseason'], $this->calls);
        self::assertStringContainsString(
            '<div class="ibl-tab-panel" data-tab="thisseason"><p>THISSEASON-BODY</p></div>',
            $html
        );
        self::assertStringNotContainsString('ALLTIME-BODY', $html);
        self::assertStringNotContainsString('BYFRANCHISE-BODY', $html);
    }

    public function testRenderWrapsTabsAndPanelInOneCard(): void
    {
        $html = $this->makeController()->render('alltime', []);

        self::assertStringStartsWith('<div class="ibl-tab-card"><div class="ibl-tabs">', $html);
        self::assertStringEndsWith('<p>ALLTIME-BODY</p></div></div>', $html);
    }

    public function testRenderFallsBackToAllTimeRendererForUnknownTab(): void
    {
        $html = $this->makeController()->render('bogus"><script>', []);

        self::assertSame(['alltime'], $this->calls);
        self::assertStringContainsString('data-tab="alltime"><p>ALLTIME-BODY</p>', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testRenderPassesQueryToRenderer(): void
    {
        $this->makeController()->render('byfranchise', ['teamid' => '5']);

        self::assertSame(['byfranchise'], $this->calls);
        self::assertSame(['teamid' => '5'], $this->receivedQuery);
    }

    public function testConstructorRejectsRendererMapMissingATab(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecordsController([
            RecordsController::TAB_ALLTIME => static fn (array $q): string => '',
            RecordsController::TAB_BYFRANCHISE => static fn (array $q): string => '',
        ]);
    }

    public function testConstructorRejectsRendererMapWithExtraTab(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecordsController([
            RecordsController::TAB_ALLTIME => static fn (array $q): string => '',
            RecordsController::TAB_BYFRANCHISE => static fn (array $q): string => '',
            RecordsController::TAB_THISSEASON => static fn (array $q): string => '',
            'allstar' => static fn (array $q): string => '',
        ]);
    }

    public function testPageTitleUsesTabLabel(): void
    {
        self::assertSame('- Records: All-Time', RecordsController::pageTitle('alltime'));
        self::assertSame('- Records: By Franchise', RecordsController::pageTitle('byfranchise'));
        self::assertSame('- Records: This Season', RecordsController::pageTitle('thisseason'));
        self::assertSame('- Records: All-Time', RecordsController::pageTitle('bogus'));
    }

    public function testLegacyRedirectUrlMapsEachLegacyModule(): void
    {
        self::assertSame(
            'modules.php?name=Records&tab=alltime',
            RecordsController::legacyRedirectUrl('RecordHolders', [])
        );
        self::assertSame(
            'modules.php?name=Records&tab=alltime',
            RecordsController::legacyRedirectUrl('RecordHolders', ['op' => 'bogus'])
        );
        self::assertSame(
            'modules.php?name=AllStarAppearances',
            RecordsController::legacyRedirectUrl('RecordHolders', ['op' => 'allstar'])
        );
        self::assertSame(
            'modules.php?name=Records&tab=byfranchise',
            RecordsController::legacyRedirectUrl('FranchiseRecordBook', [])
        );
        self::assertSame(
            'modules.php?name=Records&tab=byfranchise&teamid=5',
            RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['teamid' => '5'])
        );
        self::assertSame(
            'modules.php?name=Records&tab=thisseason',
            RecordsController::legacyRedirectUrl('SeasonHighs', [])
        );
        self::assertSame(
            'modules.php?name=Records&tab=thisseason&seasonPhase=Playoffs',
            RecordsController::legacyRedirectUrl('SeasonHighs', ['seasonPhase' => 'Playoffs'])
        );
    }

    public function testLegacyRedirectUrlReturnsNullForFranchiseApiOp(): void
    {
        self::assertNull(RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['op' => 'api']));
        self::assertNull(RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['op' => 'api', 'teamid' => '5']));
    }

    public function testLegacyRedirectUrlReturnsNullForUnknownModule(): void
    {
        self::assertNull(RecordsController::legacyRedirectUrl('Standings', []));
        self::assertNull(RecordsController::legacyRedirectUrl('recordholders', []));
        self::assertNull(RecordsController::legacyRedirectUrl('', []));
    }

    public function testLegacyRedirectUrlDropsNonNumericTeamId(): void
    {
        $base = 'modules.php?name=Records&tab=byfranchise';

        self::assertSame($base, RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['teamid' => '5abc']));
        self::assertSame($base, RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['teamid' => '']));
        self::assertSame($base, RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['teamid' => ['5']]));
        self::assertSame($base, RecordsController::legacyRedirectUrl('FranchiseRecordBook', ['teamid' => 5]));
    }

    public function testLegacyRedirectUrlEncodesSeasonPhase(): void
    {
        $url = RecordsController::legacyRedirectUrl('SeasonHighs', ['seasonPhase' => "Reg\r\nSet-Cookie: x=1&tab=evil"]);

        self::assertSame(
            'modules.php?name=Records&tab=thisseason&seasonPhase=Reg%0D%0ASet-Cookie%3A%20x%3D1%26tab%3Devil',
            $url
        );
        self::assertStringNotContainsString("\r", $url);
        self::assertStringNotContainsString("\n", $url);
    }
}
