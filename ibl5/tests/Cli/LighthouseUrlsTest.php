<?php

declare(strict_types=1);

namespace Tests\Cli;

use Cli\LighthouseUrls;
use Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('cli')]
final class LighthouseUrlsTest extends TestCase
{
    private const BASE = 'http://localhost:8080';

    public function testFullSiteUrlsStartsWithIndex(): void
    {
        self::assertSame(
            self::BASE . '/ibl5/index.php',
            LighthouseUrls::fullSiteUrls(self::BASE)[0]
        );
    }

    public function testFullSiteUrlsCount(): void
    {
        $expected = 1
            + count(ModuleRegistry::getAllModules())
            + count(LighthouseUrls::SUB_PAGES)
            - count(LighthouseUrls::PARAM_REQUIRED_MODULES);

        self::assertCount($expected, LighthouseUrls::fullSiteUrls(self::BASE));
    }

    /**
     * Regression: GameBoxscore was registered in ModuleRegistry with no
     * SUB_PAGES entry, so fullSiteUrls() emitted a bare `?name=GameBoxscore`
     * URL. That page 404s without date+game, and Lighthouse fails the whole
     * run on a >= 400 document (ERRORED_DOCUMENT_REQUEST).
     */
    public function testParamRequiredModuleEmitsNoBareUrl(): void
    {
        $fullSet = LighthouseUrls::fullSiteUrls(self::BASE);

        foreach (LighthouseUrls::PARAM_REQUIRED_MODULES as $module) {
            self::assertNotContains(
                self::BASE . '/ibl5/modules.php?name=' . $module,
                $fullSet,
                "'$module' requires query params — its bare URL must not be audited"
            );
        }
    }

    /**
     * Suppressing the bare URL must not drop the module from the audit set —
     * that would trade a loud 404 for silent coverage loss.
     */
    public function testEveryParamRequiredModuleHasASubPage(): void
    {
        foreach (LighthouseUrls::PARAM_REQUIRED_MODULES as $module) {
            self::assertArrayHasKey(
                $module,
                LighthouseUrls::SUB_PAGES,
                "'$module' suppresses its bare URL, so it MUST have a SUB_PAGES entry"
            );
        }
    }

    /**
     * Deliberately does NOT route through PARAM_REQUIRED_MODULES: dropping
     * 'GameBoxscore' from that const would empty the loops above and leave them
     * asserting nothing, while the bare 404 URL returns to the audit set.
     */
    public function testGameBoxscoreBareUrlIsNeverAudited(): void
    {
        self::assertNotContains(
            self::BASE . '/ibl5/modules.php?name=GameBoxscore',
            LighthouseUrls::fullSiteUrls(self::BASE),
            'GameBoxscore 404s without date+game — its bare URL hard-fails the Lighthouse run'
        );
    }

    public function testGameBoxscoreIsAuditedWithParameters(): void
    {
        self::assertContains(
            self::BASE . '/ibl5/modules.php?name=GameBoxscore&date=2026-02-20&game=1',
            LighthouseUrls::fullSiteUrls(self::BASE)
        );
    }

    public function testModuleUrlAppliesSubPage(): void
    {
        self::assertSame(
            self::BASE . '/ibl5/modules.php?name=Team&op=team&teamid=1',
            LighthouseUrls::moduleUrl('Team', self::BASE)
        );
    }

    public function testModuleUrlWithoutSubPage(): void
    {
        self::assertSame(
            self::BASE . '/ibl5/modules.php?name=Standings',
            LighthouseUrls::moduleUrl('Standings', self::BASE)
        );
    }

    public function testEveryModuleUrlIsInFullSiteSet(): void
    {
        $fullSet = LighthouseUrls::fullSiteUrls(self::BASE);

        foreach (ModuleRegistry::getAllModules() as $module) {
            self::assertContains(
                LighthouseUrls::moduleUrl($module, self::BASE),
                $fullSet,
                "moduleUrl('$module') must exist in the baseline full-site set"
            );
        }
    }

    public function testEveryRepresentativeUrlIsInFullSiteSet(): void
    {
        $fullSet = LighthouseUrls::fullSiteUrls(self::BASE);

        foreach (LighthouseUrls::representativeUrls(self::BASE) as $url) {
            self::assertContains(
                $url,
                $fullSet,
                "representative URL '$url' must exist in the baseline full-site set"
            );
        }
    }

    public function testRepresentativeUrlsPinsTheConstant(): void
    {
        self::assertSame(
            [
                self::BASE . '/ibl5/index.php',
                self::BASE . '/ibl5/modules.php?name=Standings',
                self::BASE . '/ibl5/modules.php?name=Team&op=team&teamid=1',
                self::BASE . '/ibl5/modules.php?name=Player&pa=showpage&pid=1',
                self::BASE . '/ibl5/modules.php?name=SeasonLeaderboards',
            ],
            LighthouseUrls::representativeUrls(self::BASE)
        );
    }

    public function testBaseUrlTrailingSlashNormalized(): void
    {
        self::assertSame(
            self::BASE . '/ibl5/index.php',
            LighthouseUrls::fullSiteUrls(self::BASE . '/')[0]
        );
    }

    public function testLighthouseRcUrlsMatchRepresentativeConstant(): void
    {
        $rcPath = __DIR__ . '/../../.lighthouserc.json';
        $raw = file_get_contents($rcPath);
        self::assertIsString($raw, '.lighthouserc.json must be readable');

        $decoded = json_decode($raw, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('ci', $decoded);
        self::assertIsArray($decoded['ci']);
        self::assertArrayHasKey('collect', $decoded['ci']);
        self::assertIsArray($decoded['ci']['collect']);
        self::assertArrayHasKey('url', $decoded['ci']['collect']);

        self::assertSame(
            LighthouseUrls::representativeUrls(self::BASE),
            $decoded['ci']['collect']['url'],
            '.lighthouserc.json collect.url must stay equal to LighthouseUrls::REPRESENTATIVE_PATHS'
        );
    }
}
