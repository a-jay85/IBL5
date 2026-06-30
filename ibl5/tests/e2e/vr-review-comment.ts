// Pure builder for the sticky `visual-review` PR comment. No I/O — the
// bin/vr-review-comment wrapper reads the change-driven gallery JSON
// (bin/vr-build-gallery output) + coverage JSON and calls these functions, so
// the markup is unit-testable (tests/ts-unit/vr-review-comment.test.ts).
//
// Models the "script writes a .md consumed by marocchino" pattern of
// bin/lighthouse-comment (consumed in .github/workflows/lighthouse.yml).
import type { VrRow, Viewport } from './vr-manifest';

export const COMMENT_HEADER = '🖼️ Visual review';

export type DiffCell = {
  /** Manifest row name (module group), e.g. `standings`. */
  module: string;
  viewport: Viewport;
  /** The cell title (snapshot filename without .png), e.g. `standings-mobile`. */
  title: string;
  /**
   * True when this cell has NO committed baseline at the PR base SHA (a
   * brand-new view — no "before" to diff against). The gallery builder
   * (bin/vr-build-gallery) sets this from its `git show <base.sha>:…` /
   * `git ls-files` index check; absent/false ⇒ a render that differs from
   * master's committed baseline.
   */
  isNew?: boolean;
};

/** A cell with no reliable before/after — it failed to render, or rendered
 *  unstably across the two PR runs (self-disagreeing flake). NOT a real change. */
export type InfraCell = { module: string; viewport: Viewport; title: string; error?: string };

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
  infraCells?: InfraCell[];
  uncoveredChangedPaths: string[];
  globalChange: boolean;
  /** Per-SHA Pages base URL, e.g. https://a-jay85.github.io/IBL5/<sha>/visual-review/ */
  pagesUrl: string;
};

function reportLink(pagesUrl: string, title: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return `${base}#${encodeURIComponent(title)}`;
}

/**
 * Build the sticky-comment markdown. Three load-bearing properties:
 *  - diffing cells are grouped per module with desktop + mobile entries linking
 *    into the static side-by-side gallery (each cell is a `<title>` anchor);
 *  - a non-empty `uncoveredChangedPaths` ALWAYS renders a "⚠️ Changed but NOT
 *    covered" section (a coverage gap can never masquerade as "nothing to review");
 *  - the zero-diff branch produces a safe body with no broken links.
 */
export function buildComment(input: BuildCommentInput): string {
  const { diffCells, uncoveredChangedPaths, globalChange, pagesUrl } = input;
  const infraCells = input.infraCells ?? [];
  const hasPixelWork = diffCells.length > 0; // changed + new are both DiffCells
  const lines: string[] = [];

  lines.push(`## ${COMMENT_HEADER}`);
  lines.push('');

  if (hasPixelWork) {
    lines.push(
      "These views render differently from master's committed baseline. Review the " +
        'before/after/diff below. If the change is intended, **apply the ' +
        '`update-baselines` label** — the regenerated-baseline auto-commit is the ' +
        'durable sign-off. (The VR check may already be green if baselines were ' +
        "regenerated in-branch; this gallery compares against master's committed " +
        'baseline regardless of the check color.)',
    );
    lines.push('');
  } else if (infraCells.length > 0) {
    lines.push(
      '⚠️ **No reliable before/after — this is NOT a confirmed change.** ' +
        'The view(s) below either failed to render (navigation/timeout) **or rendered ' +
        'unstably across two runs** (the two PR renders disagreed). Either way there is ' +
        'nothing trustworthy to review — **re-run the VR job**; regenerating baselines ' +
        'cannot fix a flake, so do **not** apply the baseline label.',
    );
    lines.push('');
  }

  // Global-change banner: coverage's shared-CSS/theme/class signal. In the
  // change-driven design this NO LONGER fans the gallery out to every row — it is
  // a standalone heads-up, shown whenever a shared file changed, independent of
  // whether any row's render actually diffed (so coverage drives only the banner,
  // never the gallery).
  if (globalChange) {
    lines.push(
      '> 🌐 **Global change detected** (shared CSS / theme / class) — changes may ' +
        'surface in views beyond those listed below; review broadly.',
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
    infraCells.length === 0 &&
    !globalChange &&
    uncoveredChangedPaths.length === 0
  ) {
    // Boundary: nothing diffed, nothing new, nothing uncovered — safe no-diff body.
    lines.push('_No visual diffs detected and no changed page falls outside VR coverage._');
    lines.push('');
  }

  if (infraCells.length > 0) {
    lines.push(
      `### ⚠️ ${infraCells.length} view(s) with no reliable render (failed or self-disagreeing — not a confirmed change)`,
    );
    lines.push('');
    lines.push(
      'These cells **failed to render, or rendered unstably across two runs ' +
        '(self-disagreeing)**, so there is no reliable before/after. Re-run the VR job; ' +
        'this is a flake/infra failure, **not** a baseline issue.',
    );
    lines.push('');
    for (const cell of [...infraCells].sort((a, b) => a.title.localeCompare(b.title))) {
      lines.push(`- \`${cell.title}\` — ${cell.viewport}${cell.error ? ` (${cell.error})` : ''}`);
    }
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
