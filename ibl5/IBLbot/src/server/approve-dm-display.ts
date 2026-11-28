/**
 * Turns the untrusted `display` value of `POST /discordApproveDM` into strings
 * that are safe to place in an embed. Pure: no client, no store, no config, no
 * I/O. Nothing returned here may reach a custom id, the click store, routing or
 * the recipient.
 */

export const CELL_KEYS = ['tests', 'comments', 'bot_review', 'easy_undo', 'your_ok'] as const;
export type CellKey = (typeof CELL_KEYS)[number];
export const CELL_NAMES: Record<CellKey, string> = {
    tests: 'Tests', comments: 'Comments', bot_review: 'Bot review',
    easy_undo: 'Easy undo', your_ok: 'Your OK',
};
export const FALLBACK = 'Could not read';
export const CAPS = { title: 256, size: 64, cell: 200, field: 1024 } as const;
const ICONS = new Set(['✅', '⏳', '❌', '🚨', '']);
const FALLBACK_CELL = `❌ ${FALLBACK}`;

export interface Display {
    title: string | null;       // PR title line, or null
    size: string | null;        // "3 files, +32 −10", or null
    cells: Record<CellKey, string>;
    verdict: string;
    decision: string | null;    // blockquoted, or null to omit the field
}

export const EMPTY_DISPLAY: Display = {
    title: null,
    size: null,
    cells: {
        tests: FALLBACK_CELL,
        comments: FALLBACK_CELL,
        bot_review: FALLBACK_CELL,
        easy_undo: FALLBACK_CELL,
        your_ok: FALLBACK_CELL,
    },
    verdict: FALLBACK,
    decision: null,
};

/**
 * Drop control characters (newline stays) and break mentions and masked links
 * with a zero-width space. Mentions and links grow, so call this before `clip`.
 */
export function neutralize(text: string): string {
    return text
        .replace(/[\u0000-\u0009\u000b-\u001f\u007f]/g, '')
        .replace(/@(everyone|here)/gi, '@\u200b$1')
        .replace(/<(@[!&]?|#)(\d+)>/g, '<\u200b$1$2>')
        .replace(/\]\(/g, ']\u200b(');
}

/** Cut to at most `max` UTF-16 units, never leaving half of a surrogate pair. */
export function clip(text: string, max: number): string {
    if (text.length <= max) return text;
    return text.slice(0, max - 1).replace(/[\uD800-\uDBFF]$/, '') + '…';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Neutralized text, or null for a non-string or a blank result. */
function cleanText(value: unknown): string | null {
    if (typeof value !== 'string') return null;
    const cleaned = neutralize(value);
    return cleaned.trim() === '' ? null : cleaned;
}

/** A one-line description part: newlines become spaces. */
function parseLine(value: unknown, max: number): string | null {
    const cleaned = cleanText(value);
    return cleaned === null ? null : clip(cleaned.replace(/\n/g, ' '), max);
}

function parseCell(value: unknown): string {
    if (!isRecord(value)) return FALLBACK_CELL;
    const { icon, text } = value;
    if (typeof icon !== 'string' || !ICONS.has(icon) || typeof text !== 'string') return FALLBACK_CELL;
    const cleaned = cleanText(icon && text ? `${icon} ${text}` : icon || text);
    return cleaned === null ? FALLBACK_CELL : clip(cleaned, CAPS.cell);
}

/** Never throws. Anything unreadable takes the fallback, or null where a part can be omitted. */
export function parseDisplay(raw: unknown): Display {
    if (!isRecord(raw)) return EMPTY_DISPLAY;

    const cells: Record<CellKey, string> = { ...EMPTY_DISPLAY.cells };
    const rawCells = raw.cells;
    if (isRecord(rawCells)) {
        for (const key of CELL_KEYS) cells[key] = parseCell(rawCells[key]);
    }

    const verdict = cleanText(raw.verdict);
    const decision = cleanText(raw.decision);

    return {
        title: parseLine(raw.title, CAPS.title),
        size: parseLine(raw.size, CAPS.size),
        cells,
        verdict: verdict === null ? FALLBACK : clip(verdict, CAPS.field),
        decision: decision === null
            ? null
            : clip(decision.split('\n').map((line) => `> ${line}`).join('\n'), CAPS.field),
    };
}
