import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

import { CELL_KEYS, EMPTY_DISPLAY, FALLBACK, clip, neutralize, parseDisplay } from './approve-dm-display.js';
import type { Display } from './approve-dm-display.js';

const FALLBACK_CELL = '❌ Could not read';
const ZWSP = '\u200b';
const MENTIONS = '@everyone @here <@123> <@!123> <@&456> <#789>';
const RAW_EVERYONE = /@(everyone|here)\b/i;
const RAW_ID_MENTION = /<(@[!&]?|#)\d+>/;

/** A raw display with `text` in every string slot. */
function rawDisplay(text: string) {
    const cell = { icon: '✅', text };
    return {
        title: text,
        size: text,
        cells: { tests: cell, comments: cell, bot_review: cell, easy_undo: cell, your_ok: cell },
        verdict: text,
        decision: text,
    };
}

function allStrings(display: Display): string[] {
    return [display.title!, display.size!, ...Object.values(display.cells), display.verdict, display.decision!];
}

describe('neutralize', () => {
    it('drops control characters but keeps newlines', () => {
        expect(neutralize('a\u0000b\tc\r\nd\u001be\u007f')).toBe('abc\nde');
    });
});

describe('parseDisplay', () => {
    it('neutralizes @everyone, @here and id mentions in every field', () => {
        const out = allStrings(parseDisplay(rawDisplay(`${MENTIONS} @EveryOne`)));
        expect(out).toHaveLength(9);
        for (const value of out) {
            expect(value).not.toMatch(RAW_EVERYONE);
            expect(value).not.toMatch(RAW_ID_MENTION);
            expect(value).toContain(`@${ZWSP}everyone`);
            expect(value).toContain(`@${ZWSP}here`);
            expect(value).toContain(`<${ZWSP}@123>`);
            expect(value).toContain(`<${ZWSP}@!123>`);
            expect(value).toContain(`<${ZWSP}@&456>`);
            expect(value).toContain(`<${ZWSP}#789>`);
        }
    });

    it('breaks masked links', () => {
        const { verdict } = parseDisplay({ verdict: '[click](https://evil.example)' });
        expect(verdict).not.toContain('](');
        expect(verdict).toBe(`[click]${ZWSP}(https://evil.example)`);
    });

    it('truncates a 2000-char field to 1024 with an ellipsis', () => {
        const { verdict } = parseDisplay({ verdict: 'a'.repeat(2000) });
        expect(verdict).toHaveLength(1024);
        expect(verdict.endsWith('…')).toBe(true);
    });

    it('neutralizes before truncating', () => {
        const { verdict } = parseDisplay({ verdict: `${'a'.repeat(1015)} @everyone` });
        expect(verdict.length).toBeLessThanOrEqual(1024);
        expect(verdict).not.toContain('@everyone');

        // An early mention grows when neutralized, so clipping first would overshoot the cap.
        const early = parseDisplay({ verdict: ' @everyone'.repeat(200) }).verdict;
        expect(early).toHaveLength(1024);
        expect(early).not.toContain('@everyone');
    });

    it('falls back per cell for a bad icon or type', () => {
        const { cells } = parseDisplay({
            cells: {
                tests: { icon: '<@1>', text: 'ok' },
                comments: 'x',
                bot_review: { icon: '✅', text: 5 },
                easy_undo: { icon: '✅', text: 'revert' },
                your_ok: { icon: '', text: 'none' },
            },
        });
        expect(cells).toEqual({
            tests: FALLBACK_CELL,
            comments: FALLBACK_CELL,
            bot_review: FALLBACK_CELL,
            easy_undo: '✅ revert',
            your_ok: 'none',
        });
    });

    it('returns the fallback display for non-object input', () => {
        for (const raw of [undefined, null, [], 'str', 42]) {
            expect(parseDisplay(raw)).toEqual(EMPTY_DISPLAY);
        }
        expect(Object.values(EMPTY_DISPLAY.cells)).toEqual(Array(5).fill(FALLBACK_CELL));
    });

    it('takes the fallback or null for strings that are blank after neutralizing', () => {
        const blank = ' \u0001\n ';
        const out = parseDisplay({
            ...rawDisplay(blank),
            cells: { tests: { icon: '', text: '' }, comments: { icon: '', text: blank } },
        });
        expect(out).toEqual(EMPTY_DISPLAY);
    });

    it('turns newlines in the title and size into spaces', () => {
        const out = parseDisplay({ title: 'a\nb', size: 'c\nd' });
        expect([out.title, out.size]).toEqual(['a b', 'c d']);
    });

    it('blockquotes every decision line', () => {
        expect(parseDisplay({ decision: 'a\nb' }).decision).toBe('> a\n> b');
    });

    it('parses the committed gadget output with no fallback', () => {
        const raw: unknown = JSON.parse(
            fs.readFileSync(path.join(__dirname, 'approve-dm-display.fixture.json'), 'utf8'),
        );
        const out = parseDisplay(raw);
        expect(CELL_KEYS).toContain('bot_review');
        expect(Object.values(out.cells)).toHaveLength(5);
        for (const value of Object.values(out.cells)) {
            expect(value).not.toBe(FALLBACK_CELL);
        }
        expect(out.verdict).not.toBe(FALLBACK);
        expect(out.title).not.toBeNull();
        expect(out.size).not.toBeNull();
        expect(out.decision).toBe('> Merge if the new DM looks right.');
    });
});

describe('clip', () => {
    it('leaves a string at the cap unchanged', () => {
        expect(clip('abcde', 5)).toBe('abcde');
    });

    it('clip never splits a surrogate pair', () => {
        // Max 6 cuts at unit 5, in the middle of the third emoji.
        for (const max of [6, 7]) {
            const out = clip('🚨'.repeat(10), max);
            expect(out.length).toBeLessThanOrEqual(max);
            expect(out).not.toMatch(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])/);
            expect(out.endsWith('…')).toBe(true);
        }
    });
});
