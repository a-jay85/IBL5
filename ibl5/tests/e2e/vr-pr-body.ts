// Pure builder for the top-of-PR-body "new VR screens" section. No I/O — the
// bin/vr-review-comment wrapper reads the change-driven gallery JSON
// (bin/vr-build-gallery output) and calls these functions, plus does the
// fs copy + `gh pr edit` glue (tests/ts-unit/vr-pr-body.test.ts covers this
// module; the CLI-executable checks in the plan cover the glue).
//
// Sibling of vr-review-comment.ts, same no-I/O contract. Reuses its
// base-URL normalization + encodeURIComponent pattern (reportLink) and its
// module-grouping / localeCompare / title-sort pattern (renderModuleSection).
import type { Viewport } from './vr-manifest';

export type LeanCell = { module: string; viewport: Viewport; title: string };

export const PR_BODY_MARKER_BEGIN = '<!-- vr-new-screens:begin -->';
export const PR_BODY_MARKER_END = '<!-- vr-new-screens:end -->';

// URL under the per-SHA Pages tree; mirrors reportLink's slash-normalization + encoding.
export function newScreenUrl(pagesUrl: string, title: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return `${base}new-screens/${encodeURIComponent(title)}.png`;
}

// Deterministic copy plan (no I/O). src = <renders>/<title>.after.png ; dest = <dest>/<title>.png
export function buildCopyPlan(
  newCells: LeanCell[],
  rendersDir: string,
  destDir: string
): { src: string; dest: string }[] {
  return newCells.map((c) => ({
    src: `${rendersDir}/${c.title}.after.png`,
    dest: `${destDir}/${c.title}.png`,
  }));
}

// Managed section markdown, grouped by module (localeCompare), cells sorted by title.
// Empty newCells => '' (splice will strip the block).
export function buildNewScreensSection(newCells: LeanCell[], pagesUrl: string): string {
  if (newCells.length === 0) return '';

  const byModule = new Map<string, LeanCell[]>();
  for (const cell of newCells) {
    const group = byModule.get(cell.module);
    if (group) group.push(cell);
    else byModule.set(cell.module, [cell]);
  }

  const lines: string[] = [PR_BODY_MARKER_BEGIN, '', '### 🆕 New VR screens', ''];
  const modules = [...byModule.keys()].sort((a, b) => a.localeCompare(b));
  for (const moduleName of modules) {
    const cells = byModule.get(moduleName)!.slice().sort((a, b) => a.title.localeCompare(b.title));
    lines.push(`**${moduleName}**`, '');
    for (const c of cells) {
      lines.push(`![${c.title} — ${c.viewport}](${newScreenUrl(pagesUrl, c.title)})`);
    }
    lines.push('');
  }
  lines.push(PR_BODY_MARKER_END);
  return lines.join('\n');
}

// "null"/nullish -> ""; strip trailing newlines.
export function normalizeBody(raw: string | null | undefined): string {
  const b = raw == null || raw === 'null' ? '' : raw;
  return b.replace(/\n+$/, '');
}

// Offset-0-only, clobber-safe splice. currentBody must already be normalized.
export function spliceBody(currentBody: string, section: string): string {
  let human = currentBody;
  if (currentBody.startsWith(PR_BODY_MARKER_BEGIN)) {
    const end = currentBody.indexOf(PR_BODY_MARKER_END);
    if (end !== -1) human = currentBody.slice(end + PR_BODY_MARKER_END.length).replace(/^\s+/, '');
    // BEGIN-at-0 but no END => malformed (never written by us); leave human = currentBody.
  }
  if (section === '') return human; // strip case
  return human === '' ? section : `${section}\n\n${human}`;
}
