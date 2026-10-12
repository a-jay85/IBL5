import { execFileSync } from 'node:child_process';
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Pure scanner for raw hex colors in ibl5/design/components/**/*.css.
// Classifies every hex literal so the ratchet spec can pin the remainder
// (component-css-hex.test.ts). CLI: `bun tests/ts-unit/component-css-hex-scan.ts
// [--ref <gitref>] [--list]`.

export type HexCategory = 'comment' | 'fallback' | 'definition' | 'raw';

export interface HexHit {
  hex: string;
  category: HexCategory;
  prop: string | null;
  line: number;
}

export interface AllowEntry {
  hex: string;
  category: 'raw' | 'definition';
  count: number;
  reason: string;
}

// key = path relative to design/components, e.g. 'block-fa-admin.css'
export type Allowlist = Record<string, AllowEntry[]>;

export const PURE_WHITE_BLACK: ReadonlySet<string> = new Set(['#fff', '#ffffff', '#000', '#000000']);

const HEX_RE = /#[0-9a-fA-F]{3,8}\b/g;
const DECL_RE = /([-\w]+)\s*:\s*([^;{}]+)(?=[;}])/g;

function lineAt(text: string, offset: number): number {
  let line = 1;
  for (let i = 0; i < offset; i++) {
    if (text.charCodeAt(i) === 10) line++;
  }
  return line;
}

// Returns [start, end) ranges inside `value` that sit after the first comma
// of a var( call at that call's own paren depth (the fallback argument).
function fallbackRanges(value: string): Array<[number, number]> {
  const ranges: Array<[number, number]> = [];
  const varRe = /var\(/g;
  let m: RegExpExecArray | null;
  while ((m = varRe.exec(value)) !== null) {
    let depth = 1;
    let fallbackStart = -1;
    let i = m.index + m[0].length;
    for (; i < value.length; i++) {
      const ch = value[i];
      if (ch === '(') depth++;
      else if (ch === ')') {
        depth--;
        if (depth === 0) break;
      } else if (ch === ',' && depth === 1 && fallbackStart === -1) {
        fallbackStart = i + 1;
      }
    }
    if (fallbackStart !== -1) ranges.push([fallbackStart, i]);
  }
  return ranges;
}

export function classifyCss(css: string): HexHit[] {
  const hits: HexHit[] = [];

  // Comments: collect hex, then blank each span (keep newlines) so offsets hold.
  let blanked = css;
  const commentRe = /\/\*[\s\S]*?\*\//g;
  let cm: RegExpExecArray | null;
  while ((cm = commentRe.exec(css)) !== null) {
    const span = cm[0];
    const hexRe = new RegExp(HEX_RE.source, 'g');
    let hm: RegExpExecArray | null;
    while ((hm = hexRe.exec(span)) !== null) {
      hits.push({
        hex: hm[0].toLowerCase(),
        category: 'comment',
        prop: null,
        line: lineAt(css, cm.index + hm.index),
      });
    }
    blanked =
      blanked.slice(0, cm.index) +
      span.replace(/[^\n]/g, ' ') +
      blanked.slice(cm.index + span.length);
  }

  const declRe = new RegExp(DECL_RE.source, 'g');
  let dm: RegExpExecArray | null;
  while ((dm = declRe.exec(blanked)) !== null) {
    const prop = dm[1];
    const value = dm[2];
    const valueStart = dm.index + dm[0].length - value.length;
    const ranges = fallbackRanges(value);
    const hexRe = new RegExp(HEX_RE.source, 'g');
    let hm: RegExpExecArray | null;
    while ((hm = hexRe.exec(value)) !== null) {
      const at = hm.index;
      let category: HexCategory;
      if (ranges.some(([s, e]) => at >= s && at < e)) category = 'fallback';
      else if (prop.startsWith('--')) category = 'definition';
      else category = 'raw';
      hits.push({
        hex: hm[0].toLowerCase(),
        category,
        prop,
        line: lineAt(blanked, valueStart + at),
      });
    }
  }

  return hits.sort((a, b) => a.line - b.line);
}

export function checkCorpus(hitsByFile: Record<string, HexHit[]>, allow: Allowlist): string[] {
  const violations: string[] = [];

  for (const [file, hits] of Object.entries(hitsByFile)) {
    const entries = allow[file] ?? [];
    const groups = new Map<string, HexHit[]>();
    for (const hit of hits) {
      if (hit.category !== 'raw' && hit.category !== 'definition') continue;
      const key = `${hit.hex}|${hit.category}`;
      groups.set(key, [...(groups.get(key) ?? []), hit]);
    }

    for (const [key, group] of groups) {
      const [hex, category] = key.split('|');
      const allowed =
        entries.find((e) => e.hex.toLowerCase() === hex && e.category === category)?.count ?? 0;
      if (group.length > allowed) {
        violations.push(
          `NEW ${category} hex ${hex} in ${file} at line(s) ${group.map((h) => h.line).join(',')}; ` +
            'use a token alias, or add an allowlist entry with a reason',
        );
      }
    }

    for (const hit of hits) {
      if ((hit.category === 'raw' || hit.category === 'definition') && PURE_WHITE_BLACK.has(hit.hex)) {
        violations.push(`PURE white/black ${hit.hex} in ${file}:${hit.line}; use var(--white) / var(--black)`);
      }
    }
  }

  for (const [file, entries] of Object.entries(allow)) {
    if (!(file in hitsByFile)) {
      violations.push(`STALE allowlist file ${file}: no such CSS file on disk; delete the entry`);
      continue;
    }
    for (const entry of entries) {
      const hex = entry.hex.toLowerCase();
      if (entry.reason.trim() === '') {
        violations.push(`BAD allowlist entry ${hex}/${entry.category} in ${file}: empty reason`);
      }
      if (PURE_WHITE_BLACK.has(hex)) {
        violations.push(
          `BAD allowlist entry ${hex}/${entry.category} in ${file}: pure white/black cannot be allowlisted`,
        );
      }
      const found = hitsByFile[file].filter((h) => h.hex === hex && h.category === entry.category).length;
      if (entry.count > found) {
        violations.push(
          `STALE allowlist entry ${hex}/${entry.category} in ${file}: allowed ${entry.count}, found ${found}; lower or delete it`,
        );
      }
    }
  }

  return violations;
}

export function scanDir(root: string): Record<string, HexHit[]> {
  const out: Record<string, HexHit[]> = {};
  const walk = (dir: string, prefix: string): void => {
    for (const entry of readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
      const rel = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) walk(join(dir, entry.name), rel);
      else if (entry.isFile() && entry.name.endsWith('.css')) {
        out[rel] = classifyCss(readFileSync(join(dir, entry.name), 'utf8'));
      }
    }
  };
  walk(root, '');
  return out;
}

export function summarize(hitsByFile: Record<string, HexHit[]>): Record<HexCategory, number> {
  const totals: Record<HexCategory, number> = { comment: 0, fallback: 0, definition: 0, raw: 0 };
  for (const hits of Object.values(hitsByFile)) {
    for (const hit of hits) totals[hit.category]++;
  }
  return totals;
}

function scanRef(ref: string, ibl5Dir: string): Record<string, HexHit[]> {
  const marker = 'design/components/';
  const files = execFileSync('git', ['ls-tree', '-r', '--name-only', '--full-name', ref, '--', 'design/components'], {
    cwd: ibl5Dir,
    encoding: 'utf8',
  })
    .split('\n')
    .filter((p) => p.endsWith('.css'));
  const out: Record<string, HexHit[]> = {};
  for (const full of files) {
    const rel = full.slice(full.indexOf(marker) + marker.length);
    out[rel] = classifyCss(execFileSync('git', ['show', `${ref}:${full}`], { cwd: ibl5Dir, encoding: 'utf8' }));
  }
  return out;
}

if ((import.meta as { main?: boolean }).main) {
  const args = process.argv.slice(2);
  const ibl5Dir = fileURLToPath(new URL('../../', import.meta.url));
  const refIdx = args.indexOf('--ref');
  const hitsByFile =
    refIdx !== -1
      ? scanRef(args[refIdx + 1], ibl5Dir)
      : scanDir(fileURLToPath(new URL('../../design/components', import.meta.url)));
  if (args.includes('--list')) {
    for (const [file, hits] of Object.entries(hitsByFile)) {
      for (const h of hits) {
        if (h.category === 'raw' || h.category === 'definition') {
          console.log(`${file}:${h.line} ${h.category} ${h.hex} ${h.prop}`);
        }
      }
    }
  }
  const t = summarize(hitsByFile);
  console.log(`comment=${t.comment} fallback=${t.fallback} definition=${t.definition} raw=${t.raw}`);
}
