import { describe, it, expect, vi } from 'vitest';

vi.mock('../config.js', () => ({
    config: {
        api: { baseUrl: 'https://iblhoops.net/ibl5/api/v1', key: 'test-api-key' },
    },
}));

import { boxScoreUrl, draftHistoryUrl, scheduleUrl, siteBase, teamUrl, teamYearUrl } from './common.js';

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

describe('team URL builders', () => {
    it('teamUrl emits lowercase teamid', () => {
        expect(teamUrl(5)).toBe('https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=5');
    });

    it('scheduleUrl emits lowercase teamid', () => {
        expect(scheduleUrl(5)).toBe('https://iblhoops.net/ibl5/modules.php?name=Schedule&teamid=5');
    });

    it('draftHistoryUrl emits lowercase teamid', () => {
        expect(draftHistoryUrl(5)).toBe('https://iblhoops.net/ibl5/modules.php?name=DraftHistory&teamid=5');
    });

    it('teamYearUrl emits lowercase teamid and keeps yr', () => {
        expect(teamYearUrl(5, 2023)).toBe('https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=5&yr=2023');
    });

    it('no team builder emits the camelCase teamID key', () => {
        for (const url of [teamUrl(1), scheduleUrl(1), draftHistoryUrl(1), teamYearUrl(1, 2023)]) {
            expect(url).not.toContain('teamID=');
            expect(url.startsWith(`${siteBase}/modules.php`)).toBe(true);
        }
    });
});
