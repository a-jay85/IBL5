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
            + count(LighthouseUrls::SUB_PAGES);

        self::assertCount($expected, LighthouseUrls::fullSiteUrls(self::BASE));
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
