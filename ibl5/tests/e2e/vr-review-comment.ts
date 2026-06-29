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
type PwAttachment = { name?: string; contentType?: string; path?: string };
type PwResult = { status?: string; attachments?: PwAttachment[]; error?: { message?: string }; errors?: Array<{ message?: string }> };
type PwTest = { results?: PwResult[] };
type PwSpec = { title?: string; ok?: boolean; tests?: PwTest[] };
type PwSuite = { specs?: PwSpec[]; suites?: PwSuite[] };
type PwReport = { suites?: PwSuite[] };

/** A failed cell that carries no screenshot triplet — a navigation/render/error
 *  failure, NOT a pixel change. */
export type InfraCell = { module: string; viewport: Viewport; title: string; error?: string };

/** All attachments across a spec's results. */
function specAttachments(spec: PwSpec): PwAttachment[] {
  const out: PwAttachment[] = [];
  for (const t of spec.tests ?? []) for (const r of t.results ?? []) for (const a of r.attachments ?? []) out.push(a);
  return out;
}
/** A genuine screenshot mismatch attaches `<title>-diff.png`. */
function specHasPixelDiff(spec: PwSpec): boolean {
  return specAttachments(spec).some((a) => (a.name ?? '').endsWith('-diff.png'));
}
/** First error line, if the report carried one (for the infra section). */
function specError(spec: PwSpec): string | undefined {
  for (const t of spec.tests ?? []) for (const r of t.results ?? []) {
    const m = r.error?.message ?? r.errors?.[0]?.message;
    if (m) return m.split('\n')[0].slice(0, 140);
  }
  return undefined;
}

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

/**
 * Parse the Playwright JSON report into failing cells, split by failure KIND:
 *  - diffCells  — genuine pixel mismatches (carry a `*-diff.png` attachment).
 *  - infraCells — failed but with NO screenshot triplet (navigation/timeout/error).
 *
 * Legacy fallback: a failed spec with NO attachment info at all (the historical
 * `{title, ok}`-only fixture shape) is treated as a pixel diff, preserving prior
 * behavior. Real Playwright reports always attach ≥1 artifact on failure
 * (`error-context` at minimum), so this fallback only affects synthetic fixtures.
 */
export function extractCells(report: PwReport, manifest: VrRow[]): { diffCells: DiffCell[]; infraCells: InfraCell[] } {
  const specs: PwSpec[] = [];
  for (const suite of report.suites ?? []) collectSpecs(suite, specs);

  const diffCells: DiffCell[] = [];
  const infraCells: InfraCell[] = [];
  for (const spec of specs) {
    if (spec.ok !== false) continue; // only failed specs
    const title = spec.title;
    if (!title) continue;
    const module = titleToModule(title, manifest);
    if (module === null) continue; // not a VR cell (defensive)
    const viewport = titleViewport(title);
    const atts = specAttachments(spec);
    if (specHasPixelDiff(spec)) {
      diffCells.push({ module, viewport, title });
    } else if (atts.length > 0) {
      infraCells.push({ module, viewport, title, error: specError(spec) });
    } else {
      diffCells.push({ module, viewport, title }); // legacy fallback (no attachment info)
    }
  }
  return { diffCells, infraCells };
}

/** Back-compat: callers that only want pixel-diff cells. */
export function extractDiffCells(report: PwReport, manifest: VrRow[]): DiffCell[] {
  return extractCells(report, manifest).diffCells;
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
  const infraCells = input.infraCells ?? [];
  const hasPixelWork = diffCells.length > 0; // changed + new are both DiffCells
  const lines: string[] = [];

  lines.push(`## ${COMMENT_HEADER}`);
  lines.push('');

  if (hasPixelWork) {
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
  } else if (infraCells.length > 0) {
    lines.push(
      '⚠️ **The VR run failed to render — this is NOT a pixel change.** ' +
        'The view(s) below errored on navigation/timeout, so there is no before/after to ' +
        'review. **Re-run the VR job** — regenerating baselines cannot fix a render failure, ' +
        'so do **not** apply the baseline-regeneration label.',
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
      `### ⚠️ ${infraCells.length} view(s) failed to render (navigation/error — not a pixel change)`,
    );
    lines.push('');
    lines.push(
      'These cells errored before a screenshot could be taken, so there is **no before/after**. ' +
        'Re-run the VR job; this is a flake/infra failure, **not** a baseline issue.',
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
