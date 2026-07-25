<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * The single source of the sim-recap viewer route.
 *
 * Pure and stateless: no DB, no config lookup. The absolute-URL host arrives as
 * a parameter rather than being read here, so this class is fully unit-testable
 * and both call sites — the updater-page notification (unit 3b, Phase 2) and the
 * Discord message (Phase 3) — share one route definition and cannot drift.
 *
 * The route targets unit 2's admin-gated viewer; neither method bypasses that
 * gate — they only compose the path the gate already protects.
 */
final class SimSummaryLink
{
    /**
     * The root-absolute viewer path for a given sim, e.g.
     * `/ibl5/simSummaries.php?sim=689`.
     *
     * Root-absolute so it resolves independently of the rendering page's own
     * directory depth and of whether that page emits a `<base href>`. The
     * viewer lives at the `ibl5/` root, but the updater page that links to it
     * is served from `ibl5/scripts/` and builds a `<head>` with no `<base>`, so
     * a bare relative path resolved to `/ibl5/scripts/simSummaries.php` and 404'd.
     */
    public static function path(int $sim): string
    {
        return '/ibl5/simSummaries.php?sim=' . $sim;
    }

    /**
     * The absolute viewer URL for a given sim on a bare canonical host.
     *
     * `$host` is a bare hostname with no scheme and no trailing slash (e.g.
     * `iblhoops.net`); this method supplies the scheme, and `path()` already
     * carries the leading `/ibl5/`.
     */
    public static function absolute(int $sim, string $host): string
    {
        return 'https://' . $host . self::path($sim);
    }
}
