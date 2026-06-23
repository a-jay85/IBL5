// Pure changed-files → VR-manifest coverage mapper. No I/O, no git — all inputs
// are passed in so the logic is unit-testable in isolation (tests/unit/vr-coverage-map.test.ts).
// The bin/vr-changed-coverage wrapper supplies the manifest, the changed-file
// list, and the global-change glob set; this module decides which VR rows a
// change touches and which changed paths fall into the silent-coverage gap.
//
// Mirrors the module→URL mapping concept of Cli\LighthouseUrls::moduleUrl() and
// the sweeping/global fallback of Cli\LighthouseUrls::representativeUrls().
import type { VrRow } from './vr-manifest';

/**
 * Translate a glob into an anchored RegExp.
 *   `**`  matches any characters including `/` (and the following `/` is optional,
 *         so `a/**` matches `a/b/c` and `**\/*.css` matches `x/y.css` and `y.css`).
 *   `*`   matches any characters except `/` (a single path segment).
 */
function globToRegExp(glob: string): RegExp {
  let re = '';
  for (let i = 0; i < glob.length; i++) {
    const c = glob[i];
    if (c === '*') {
      if (glob[i + 1] === '*') {
        re += '.*';
        i++;
        // Consume a following slash so `dir/**` matches `dir/x` and `**/x` matches `x`.
        if (glob[i + 1] === '/') i++;
      } else {
        re += '[^/]*';
      }
    } else if ('\\^$.|?+()[]{}'.includes(c)) {
      re += '\\' + c;
    } else {
      re += c;
    }
  }
  return new RegExp('^' + re + '$');
}

function matchesGlob(path: string, glob: string): boolean {
  return globToRegExp(glob).test(path);
}

/**
 * Derive the default source glob from a VR row's `url`.
 *   `modules.php?name=Standings` → `ibl5/modules/Standings/**`
 *   `index.php` (no `name=`)     → null (the row must declare `sourceGlobs`)
 */
export function deriveModuleGlob(url: string): string | null {
  const m = url.match(/[?&]name=([A-Za-z0-9_]+)/);
  return m ? `ibl5/modules/${m[1]}/**` : null;
}

/**
 * The globs whose change implies a row's page changed: an explicit `sourceGlobs`
 * override wins; otherwise the glob derived from the URL (empty when none derives).
 */
export function rowGlobs(row: VrRow): string[] {
  if (row.sourceGlobs && row.sourceGlobs.length > 0) return row.sourceGlobs;
  const derived = deriveModuleGlob(row.url);
  return derived ? [derived] : [];
}

/** True when any changed path matches a global glob (CSS / design / themes / shared classes). */
export function isGlobalChange(changedPaths: string[], globalGlobs: string[]): boolean {
  return changedPaths.some((p) => globalGlobs.some((g) => matchesGlob(p, g)));
}

/**
 * A changed path is "website-affecting" (so an uncovered one must be reported,
 * never silently un-reviewed) unless it is docs / rules / host bin / markdown.
 * Mirrors the deny-set of `bin/website-affecting`.
 */
const NON_WEBSITE = /(\.md$|^docs\/|^ibl5\/docs\/|^\.claude\/|^bin\/)/;

function isWebsiteAffecting(path: string): boolean {
  return !NON_WEBSITE.test(path);
}

export type CoverageResult = {
  coveredRows: string[];
  uncoveredChangedPaths: string[];
  globalChange: boolean;
};

/**
 * Map a changed-file list onto VR-manifest coverage.
 *  - A global change ⇒ every row is covered (fan-out) and `globalChange: true`.
 *  - Otherwise each changed path is intersected against every row's globs; a
 *    website-affecting path that matches no row lands in `uncoveredChangedPaths`
 *    (the silent-coverage gap a reviewer must be told about).
 */
export function classifyChangedFiles({
  manifest,
  changedPaths,
  globalGlobs,
}: {
  manifest: VrRow[];
  changedPaths: string[];
  globalGlobs: string[];
}): CoverageResult {
  if (isGlobalChange(changedPaths, globalGlobs)) {
    return {
      coveredRows: manifest.map((r) => r.name),
      uncoveredChangedPaths: [],
      globalChange: true,
    };
  }

  const covered = new Set<string>();
  const uncovered = new Set<string>();

  for (const path of changedPaths) {
    let matched = false;
    for (const row of manifest) {
      if (rowGlobs(row).some((g) => matchesGlob(path, g))) {
        covered.add(row.name);
        matched = true;
      }
    }
    if (!matched && isWebsiteAffecting(path)) {
      uncovered.add(path);
    }
  }

  return {
    coveredRows: [...covered],
    uncoveredChangedPaths: [...uncovered],
    globalChange: false,
  };
}
