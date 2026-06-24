// Pure builder for the sticky `visual-review` PR comment. No I/O — the
// bin/vr-review-comment wrapper reads the Playwright JSON + coverage JSON and
// calls these functions, so the markup is unit-testable (tests/ts-unit/vr-review-comment.test.ts).
//
// Models the "script writes a .md consumed by marocchino" pattern of
// bin/lighthouse-comment (consumed in .github/workflows/lighthouse.yml).
import type { VrRow, Viewport } from './vr-manifest';

export const COMMENT_HEADER = '🖼️ Visual review';

export type DiffCell = {
  /** Manifest row name (module group), e.g. `standings`. */
  module: string;
  viewport: Viewport;
  /** The Playwright test title (snapshot filename without .png), e.g. `standings-mobile`. */
  title: string;
  /**
   * True when this cell's baseline snapshot is NOT committed to the git index
   * (a brand-new view rendered in Playwright `missing` mode — no prior baseline
   * to diff against). Set by classifyCells from the wrapper's git ls-files
   * result; absent/false ⇒ a real pixel regression against a committed baseline.
   */
  isNew?: boolean;
};

// ── Playwright JSON report (only the fields we read) ─────────────────────────
type PwSpec = { title?: string; ok?: boolean };
type PwSuite = { specs?: PwSpec[]; suites?: PwSuite[] };
type PwReport = { suites?: PwSuite[] };

/** Flatten the nested suite tree into a flat spec list. */
function collectSpecs(suite: PwSuite, out: PwSpec[]): void {
  for (const s of suite.specs ?? []) out.push(s);
  for (const child of suite.suites ?? []) collectSpecs(child, out);
}

/**
 * Map a test title to its manifest row name. Row names may contain hyphens, so
 * the longest row name that the title equals or is prefixed by (`name` or
 * `name-…`) wins.
 */
export function titleToModule(title: string, manifest: VrRow[]): string | null {
  let best: string | null = null;
  for (const row of manifest) {
    if (title === row.name || title.startsWith(row.name + '-')) {
      if (best === null || row.name.length > best.length) best = row.name;
    }
  }
  return best;
}

/** The trailing `-mobile` segment of a snapshot filename encodes the viewport. */
function titleViewport(title: string): Viewport {
  return title.endsWith('-mobile') ? 'mobile' : 'desktop';
}

/** Parse the Playwright JSON report into the failing (diffing) cells. */
export function extractDiffCells(report: PwReport, manifest: VrRow[]): DiffCell[] {
  const specs: PwSpec[] = [];
  for (const suite of report.suites ?? []) collectSpecs(suite, specs);

  const cells: DiffCell[] = [];
  for (const spec of specs) {
    if (spec.ok !== false) continue; // only failed screenshot comparisons
    const title = spec.title;
    if (!title) continue;
    const module = titleToModule(title, manifest);
    if (module === null) continue; // not a VR cell (defensive)
    cells.push({ module, viewport: titleViewport(title), title });
  }
  return cells;
}

/**
 * Classify each diff cell NEW vs CHANGED by whether its baseline snapshot is in
 * the git tracked index. `trackedTitles` is the set of titles whose snapshot
 * .png IS committed (the wrapper computes this via `git ls-files`, keeping this
 * function pure). NEW ⇔ the title is NOT tracked.
 */
export function classifyCells(cells: DiffCell[], trackedTitles: Set<string>): DiffCell[] {
  return cells.map((cell) => ({ ...cell, isNew: !trackedTitles.has(cell.title) }));
}

export type BuildCommentInput = {
  diffCells: DiffCell[];
  uncoveredChangedPaths: string[];
  globalChange: boolean;
  /** Per-SHA Pages base URL, e.g. https://a-jay85.github.io/IBL5/<sha>/visual-review/ */
  pagesUrl: string;
};

function reportLink(pagesUrl: string, title: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return `${base}#?q=${encodeURIComponent(title)}`;
}

/**
 * Build the sticky-comment markdown. Three load-bearing properties:
 *  - diffing cells are grouped per module with desktop + mobile entries linking
 *    into the per-SHA Playwright HTML report (before/after/diff slider);
 *  - a non-empty `uncoveredChangedPaths` ALWAYS renders a "⚠️ Changed but NOT
 *    covered" section (a coverage gap can never masquerade as "nothing to review");
 *  - the zero-diff branch produces a safe body with no broken links.
 */
export function buildComment(input: BuildCommentInput): string {
  const { diffCells, uncoveredChangedPaths, globalChange, pagesUrl } = input;
  const lines: string[] = [];

  lines.push(`## ${COMMENT_HEADER}`);
  lines.push('');
  lines.push(
    'This PR changed pixels. Review the before/after/diff below, then **apply the ' +
      '`update-baselines` label** to approve — that regenerates the baselines and ' +
      'the auto-commit is the durable sign-off. The VR check stays red until then.',
  );
  lines.push('');

  if (globalChange) {
    lines.push(
      '> 🌐 **Global change detected** (shared CSS / theme / class) — every VR row ' +
        'is treated as potentially affected; review broadly.',
    );
    lines.push('');
  }

  const changedCells = diffCells.filter((c) => !c.isNew);
  const newCells = diffCells.filter((c) => c.isNew);

  // Render one "grouped by module" block under a section heading.
  const renderModuleSection = (heading: string, cells: DiffCell[]): void => {
    const byModule = new Map<string, DiffCell[]>();
    for (const cell of cells) {
      const arr = byModule.get(cell.module) ?? [];
      arr.push(cell);
      byModule.set(cell.module, arr);
    }
    lines.push(`### ${heading.replace('{n}', String(cells.length)).replace('{m}', String(byModule.size))}`);
    lines.push('');
    for (const module of [...byModule.keys()].sort()) {
      const group = byModule.get(module)!;
      lines.push(`<details><summary><strong>${module}</strong> — ${group.length} view(s)</summary>`);
      lines.push('');
      for (const cell of group.sort((a, b) => a.title.localeCompare(b.title))) {
        lines.push(`- [${cell.title} — ${cell.viewport}](${reportLink(pagesUrl, cell.title)})`);
      }
      lines.push('');
      lines.push('</details>');
      lines.push('');
    }
  };

  if (changedCells.length > 0) {
    renderModuleSection('{n} changed view(s) across {m} module(s)', changedCells);
  }

  if (newCells.length > 0) {
    lines.push('### 🆕 New views (no prior baseline — review the render)');
    lines.push('');
    lines.push(
      'These views have **no committed baseline** — Playwright wrote the first ' +
        'render and the run failed by design. There is no "before"; the link shows ' +
        'the new render. Confirm it looks right, then apply `update-baselines` to ' +
        'commit these as the baselines.',
    );
    lines.push('');
    renderModuleSection('{n} new view(s) across {m} module(s)', newCells);
  }

  if (
    changedCells.length === 0 &&
    newCells.length === 0 &&
    !globalChange &&
    uncoveredChangedPaths.length === 0
  ) {
    // Boundary: nothing diffed, nothing new, nothing uncovered — safe no-diff body.
    lines.push('_No visual diffs detected and no changed page falls outside VR coverage._');
    lines.push('');
  }

  if (uncoveredChangedPaths.length > 0) {
    lines.push('### ⚠️ Changed but NOT covered by the VR manifest — review manually / add a row');
    lines.push('');
    lines.push(
      'These changed paths affect the website but match no `vr-manifest.ts` row, so ' +
        'they have **no before/after above**. Review them by hand, or add a manifest row:',
    );
    lines.push('');
    for (const path of [...uncoveredChangedPaths].sort()) {
      lines.push(`- \`${path}\``);
    }
    lines.push('');
  }

  return lines.join('\n').replace(/\n+$/, '') + '\n';
}
