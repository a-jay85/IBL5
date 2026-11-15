import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

export const VIEW_PATH_RE = /^ibl5\/classes\/.+(View|Renderer|Formatter)\.php$/;

export interface CellRecord { row: string; cell: string; uri: string; files: string[] }
export type RenderedViews =
  | { ok: true; files: string[]; byRow: Record<string, string[]> }
  | { ok: false; reason: string };

export function vrRecordingEnabled(env: string | undefined): boolean {
  return env === '1';
}

export function readCellRecords(dir: string): CellRecord[] {
  return readdirSync(dir)
    .filter((f) => f.endsWith('.json') && !f.startsWith('.'))
    .map((f) => JSON.parse(readFileSync(join(dir, f), 'utf8')) as CellRecord);
}

export function collectRenderedViews(dir: string, titles: string[]): RenderedViews {
  if (!existsSync(dir)) return { ok: false, reason: `rendered dir missing: ${dir}` };
  const shot = new Set(titles);
  const records = readCellRecords(dir).filter((r) => shot.has(r.cell));
  const missing = titles.filter((t) => !records.some((r) => r.cell === t));
  if (missing.length > 0) return { ok: false, reason: `no record for cells: ${missing.join(', ')}` };
  const all = new Set<string>();
  const byRow: Record<string, Set<string>> = {};
  for (const r of records) {
    byRow[r.row] ??= new Set();
    for (const f of r.files) {
      if (!VIEW_PATH_RE.test(f)) return { ok: false, reason: `invalid path in record for ${r.cell}: ${f}` };
      all.add(f);
      byRow[r.row].add(f);
    }
  }
  if (all.size === 0) return { ok: false, reason: 'no View files recorded in any cell' };
  const rows = Object.entries(byRow).map(([k, v]) => [k, [...v].sort()] as const);
  return { ok: true, files: [...all].sort(), byRow: Object.fromEntries(rows) };
}

export function renderedViewFields(dir: string | null, titles: string[]): Record<string, unknown> {
  if (dir === null) return {};
  const result = collectRenderedViews(dir, titles);
  if (!result.ok) {
    console.warn(`[vr-build-gallery] renderedViewFiles omitted: ${result.reason}`);
    return {};
  }
  return { renderedViewFiles: result.files, renderedViewFilesByRow: result.byRow };
}

export function galleryRenderedViewsError(gallery: unknown): string | null {
  const g = gallery as { renderedViewFiles?: unknown; renderedCells?: unknown };
  // A run that took zero screenshots has nothing to record, so the field is absent by design.
  if (g.renderedCells === 0) return null;
  if (!Array.isArray(g.renderedViewFiles)) return 'vr-gallery.json has no renderedViewFiles array';
  if (g.renderedViewFiles.length === 0) return 'vr-gallery.json renderedViewFiles is empty';
  return null;
}
