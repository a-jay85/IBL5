<?php

declare(strict_types=1);

namespace Cli;

use Module\ModuleRegistry;

final class LighthouseUrls
{
    /** @var array<string, string> */
    public const SUB_PAGES = [
        'Team'                => '&op=team&teamid=1',
        'Player'              => '&pa=showpage&pid=1',
        'Schedule'            => '&teamid=1',
        'DraftHistory'        => '&year=2025',
        'FranchiseRecordBook' => '&op=team&teamid=1',
        'SeasonArchive'       => '&year=2025',
        'Injuries'            => '&teamid=1',
        'OneOnOneGame'        => '&gameid=1',
    ];

    /** @var list<string> */
    public const REPRESENTATIVE_PATHS = [
        '/ibl5/index.php',
        '/ibl5/modules.php?name=Standings',
        '/ibl5/modules.php?name=Team&op=team&teamid=1',
        '/ibl5/modules.php?name=Player&pa=showpage&pid=1',
        '/ibl5/modules.php?name=SeasonLeaderboards',
    ];

    /**
     * The full-site audit URL set: index first, then for every registered
     * module its bare `name=<Module>` URL plus, for sub-paged modules, the
     * additional sub-page variant (two entries for those). This is the exact
     * loop the original `bin/lighthouse-audit-urls` ran; do not change its
     * shape without re-pinning the characterization test.
     *
     * @return list<string>
     */
    public static function fullSiteUrls(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        $urls = [];
        $urls[] = $baseUrl . '/ibl5/index.php';

        foreach (ModuleRegistry::getAllModules() as $module) {
            $urls[] = $baseUrl . '/ibl5/modules.php?name=' . $module;

            if (isset(self::SUB_PAGES[$module])) {
                $urls[] = $baseUrl . '/ibl5/modules.php?name=' . $module . self::SUB_PAGES[$module];
            }
        }

        return $urls;
    }

    /**
     * The single best URL for one module: its `name=<module>` URL plus the
     * sub-page suffix if the module has one. Deliberately differs from
     * fullSiteUrls(): for a sub-paged module this returns ONLY the sub-page
     * variant (one URL), whereas fullSiteUrls() emits both.
     */
    public static function moduleUrl(string $module, string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        return $baseUrl . '/ibl5/modules.php?name=' . $module . (self::SUB_PAGES[$module] ?? '');
    }

    /**
     * The curated representative fallback set, mapped onto $baseUrl. Used when
     * a PR's changes are global/sweeping and a module-scoped selection would
     * either miss the blast radius or exceed the cap.
     *
     * @return list<string>
     */
    public static function representativeUrls(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        return array_map(
            static fn (string $path): string => $baseUrl . $path,
            self::REPRESENTATIVE_PATHS
        );
    }
}
