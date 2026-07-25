import { describe, it, expect, vi } from 'vitest';

vi.mock('../config.js', () => ({
    config: {
        api: { baseUrl: 'https://iblhoops.net/ibl5/api/v1', key: 'test-api-key' },
    },
}));

import { boxScoreUrl, siteBase } from './common.js';

describe('boxScoreUrl', () => {
    it('builds a PHP GameBoxscore URL on the ibl5 origin', () => {
        expect(boxScoreUrl('2008-03-10', 7)).toBe(
            'https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-03-10&game=7',
        );
    });

    it('derives its origin from siteBase like every other embed link', () => {
        expect(boxScoreUrl('2008-03-10', 7).startsWith(`${siteBase}/modules.php`)).toBe(true);
    });

    it('never emits a retired ibl6 origin', () => {
        expect(boxScoreUrl('2008-03-10', 7)).not.toMatch(/ibl6/i);
    });

    it('returns an empty string when the game number is zero', () => {
        expect(boxScoreUrl('2008-03-10', 0)).toBe('');
    });

    it('returns an empty string when the game number is negative', () => {
        expect(boxScoreUrl('2008-03-10', -1)).toBe('');
    });
});
