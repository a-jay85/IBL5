// Pure logic for the truly-manual visual-row screenshot pipeline (ADR-0126).
// No I/O — bin/vr-review-comment does the `gh`/fs glue and calls these; the
// capture spec (tests/e2e/manual-rows.spec.ts) calls the parser only.
//
// Sibling of vr-pr-body.ts, same no-I/O contract; reuses its pages-URL
// slash-normalization pattern (newScreenUrl).
//
// Grammar of the `vr:` cell (one per Truly-manual Verification Matrix row,
// carried verbatim into the PR body's `## Manual Testing` bullet):
//
//   vr: label=roster-grid; role=admin; url=modules.php?name=Roster; anchor=.x;
//       setup=DELETE test-state.php?action=reset-draft-order
//
// `|` is forbidden — it would split the matrix row and break matrix_split's
// fixed 5-column read in bin/check-plan.

export type VrRole = 'anon' | 'regular' | 'admin';

export type VrSetup = { method: string; path: string };

export type ManualVrRow = {
  row: string;
  label: string;
  role: VrRole;
  url: string;
  anchor: string;
  setup: VrSetup[];
};

export type ParsedVrCell = Omit<ManualVrRow, 'row'>;

export type VrCellResult =
  | { ok: true; row: ParsedVrCell }
  | { ok: false; error: string };

export type ManualRowStatus = 'ok' | 'failed' | 'skipped';

export type ManualRowResult = {
  label: string;
  row: string;
  status: ManualRowStatus;
  error?: string;
};

const ROLES: readonly string[] = ['anon', 'regular', 'admin'];
const METHODS: readonly string[] = ['GET', 'POST', 'DELETE'];
const KNOWN_KEYS: readonly string[] = ['label', 'role', 'url', 'anchor', 'setup'];
const LABEL_RE = /^[a-z0-9]+(-[a-z0-9]+)*$/;
const SETUP_PREFIX = 'test-state.php?action=';

export const MANUAL_COMMENT_HEADER = 'manual-row-screenshots';

// Strip optional wrapping backticks and surrounding whitespace.
function stripCell(raw: string): string {
  return raw.trim().replace(/^`+/, '').replace(/`+$/, '').trim();
}

// Parse one `vr:` cell. Every rejection returns a specific error string so a
// parser relaxed to accept-everything fails the unit tests rather than passing.
export function parseVrCell(raw: string): VrCellResult {
  const cell = stripCell(raw ?? '');
  if (cell.includes('|')) return { ok: false, error: 'vr cell must not contain "|"' };
  if (!/^vr:/.test(cell)) return { ok: false, error: 'vr cell must start with "vr:"' };

  const body = cell.slice('vr:'.length);
  const seen = new Set<string>();
  const setup: VrSetup[] = [];
  let label: string | undefined;
  let role: string | undefined;
  let url: string | undefined;
  let anchor: string | undefined;

  for (const rawPart of body.split(';')) {
    const part = rawPart.trim();
    if (part === '') continue;
    const eq = part.indexOf('=');
    if (eq === -1) return { ok: false, error: `malformed pair (no "="): ${part}` };
    const key = part.slice(0, eq).trim();
    const value = part.slice(eq + 1).trim();
    if (!KNOWN_KEYS.includes(key)) return { ok: false, error: `unknown key: ${key}` };
    if (value === '') return { ok: false, error: `empty value for key: ${key}` };
    if (key !== 'setup') {
      if (seen.has(key)) return { ok: false, error: `duplicate key: ${key}` };
      seen.add(key);
    }

    switch (key) {
      case 'label':
        if (value.length > 48) return { ok: false, error: `label longer than 48 chars: ${value}` };
        if (!LABEL_RE.test(value)) return { ok: false, error: `label is not a kebab slug: ${value}` };
        label = value;
        break;
      case 'role':
        if (!ROLES.includes(value)) return { ok: false, error: `bad role: ${value}` };
        role = value;
        break;
      case 'url':
        if (/^[a-z][a-z0-9+.-]*:\/\//i.test(value)) {
          return { ok: false, error: `url must be BASE_URL-relative (no scheme): ${value}` };
        }
        if (value.startsWith('/')) {
          return { ok: false, error: `url must not start with "/": ${value}` };
        }
        url = value;
        break;
      case 'anchor':
        anchor = value;
        break;
      case 'setup': {
        const sp = value.indexOf(' ');
        if (sp === -1) return { ok: false, error: `setup must be "<METHOD> path": ${value}` };
        const method = value.slice(0, sp).trim();
        const path = value.slice(sp + 1).trim();
        if (!METHODS.includes(method)) return { ok: false, error: `bad setup method: ${method}` };
        if (!path.startsWith(SETUP_PREFIX)) {
          return { ok: false, error: `setup path must start with "${SETUP_PREFIX}": ${path}` };
        }
        setup.push({ method, path });
        break;
      }
    }
  }

  if (label === undefined) return { ok: false, error: 'missing required key: label' };
  if (role === undefined) return { ok: false, error: 'missing required key: role' };
  if (url === undefined) return { ok: false, error: 'missing required key: url' };

  return { ok: true, row: { label, role: role as VrRole, url, anchor: anchor ?? 'body', setup } };
}

// Scan ONLY the PR body's `## Manual Testing` section — the same window
// bin/lib/pr-armable.sh uses (from `^## Manual Testing` to the next `^## `).
export function manualTestingSection(body: string): string {
  const lines = (body ?? '').split('\n');
  const start = lines.findIndex((l) => /^## Manual Testing/.test(l));
  if (start === -1) return '';
  let end = lines.length;
  for (let i = start + 1; i < lines.length; i++) {
    if (/^## /.test(lines[i])) {
      end = i;
      break;
    }
  }
  return lines.slice(start + 1, end).join('\n');
}

// Pull every `vr:` row out of the Manual Testing bullets. A bullet with no
// `vr:` token is SKIPPED (not an error) — non-visual truly-manual rows are
// legitimate. A repeated label is reported in `errors`, never silently kept.
export function parseManualBullets(body: string): { rows: ManualVrRow[]; errors: string[] } {
  const rows: ManualVrRow[] = [];
  const errors: string[] = [];
  const byLabel = new Set<string>();

  for (const line of manualTestingSection(body).split('\n')) {
    const bullet = line.match(/^\s*-\s*\[[ xX]\]\s*(.*)$/);
    if (!bullet) continue;
    const text = bullet[1];
    const vrAt = text.indexOf('vr:');
    if (vrAt === -1) continue;

    const rowMatch = text.match(/\*\*Row\s+(\d+)\*\*/);
    const rowName = rowMatch ? rowMatch[1] : '?';

    const parsed = parseVrCell(text.slice(vrAt));
    if (!parsed.ok) {
      errors.push(`Row ${rowName}: ${parsed.error}`);
      continue;
    }
    if (byLabel.has(parsed.row.label)) {
      errors.push(`Row ${rowName}: duplicate label: ${parsed.row.label}`);
      continue;
    }
    byLabel.add(parsed.row.label);
    rows.push({ row: rowName, ...parsed.row });
  }

  return { rows, errors };
}

export function manualShotFile(label: string): string {
  return `${label}.png`;
}

// URL under the per-SHA Pages tree; mirrors newScreenUrl's slash-normalization.
export function manualShotUrl(pagesUrl: string, label: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return `${base}manual-rows/${encodeURIComponent(label)}.png`;
}

// Sticky-comment markdown. Only an `ok` row gets an image; a failed or skipped
// row gets a named line so a wrong `vr:` cell is visible, never silent.
// Zero rows => '' (the caller then posts nothing).
export function buildManualSection(results: ManualRowResult[], pagesUrl: string): string {
  if (results.length === 0) return '';

  const lines: string[] = ['### 📸 Manual-row screenshots', ''];
  for (const r of results) {
    if (r.status === 'ok') {
      lines.push(`**Row ${r.row} — \`${r.label}\`**`, '');
      lines.push(`![${r.label}](${manualShotUrl(pagesUrl, r.label)})`, '');
    } else {
      const why = r.error ? `: ${r.error}` : '';
      lines.push(`**Row ${r.row} — \`${r.label}\`** — ${r.status}${why}`, '');
    }
  }
  lines.push('_Review aids only — never a gate. See ADR-0126._');
  return lines.join('\n');
}
