import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/public';
import { assertNoHorizontalOverflow } from '../helpers/mobile';

// Mobile layout check for the box score scoreboard (backlog#1706).
// The CI seed names are short (ci-seed.sql:88-89), so each test rewrites the
// rendered names and scores to the longest real names and 3-digit scores,
// then asserts via bounding boxes that nothing overlaps or leaves the card.
const URL = 'modules.php?name=GameBoxscore&date=2026-02-20&game=1';
const PHONE_WIDTHS = [320, 375, 430] as const;
const PAIRINGS = [
  { away: 'Las Vegas Aces', home: 'Charlotte Sting', awayScore: '106', homeScore: '120' },
  { away: 'Charlotte Sting', home: 'Las Vegas Aces', awayScore: '120', homeScore: '106' },
] as const;
const TOL = 0.5; // sub-pixel rounding allowance

type Box = { left: number; right: number; top: number; bottom: number };

async function injectMatchup(page: Page, p: (typeof PAIRINGS)[number]): Promise<void> {
  await page.evaluate((pair) => {
    const set = (sel: string, text: string) => {
      const el = document.querySelector(sel);
      if (el === null) throw new Error(`missing ${sel}`);
      el.textContent = text;
    };
    set('.game-boxscore__team--away .game-boxscore__team-name', pair.away);
    set('.game-boxscore__team--home .game-boxscore__team-name', pair.home);
    set('.game-boxscore__team--away .game-boxscore__score', pair.awayScore);
    set('.game-boxscore__team--home .game-boxscore__score', pair.homeScore);
  }, p);
  await page.evaluate(() => document.fonts.ready);
}

async function measure(page: Page): Promise<Record<string, Box>> {
  return page.evaluate(() => {
    const toBox = (r: DOMRect) => ({ left: r.left, right: r.right, top: r.top, bottom: r.bottom });
    const el = (sel: string) => {
      const e = document.querySelector(sel);
      if (e === null) throw new Error(`missing ${sel}`);
      return e;
    };
    const text = (sel: string) => {
      const range = document.createRange();
      range.selectNodeContents(el(sel));
      return toBox(range.getBoundingClientRect());
    };
    return {
      card: toBox(el('.game-boxscore__scoreboard').getBoundingClientRect()),
      awayLogo: toBox(el('.game-boxscore__team--away .game-boxscore__logo').getBoundingClientRect()),
      awayName: text('.game-boxscore__team--away .game-boxscore__team-name'),
      awayScore: text('.game-boxscore__team--away .game-boxscore__score'),
      at: text('.game-boxscore__at'),
      homeLogo: toBox(el('.game-boxscore__team--home .game-boxscore__logo').getBoundingClientRect()),
      homeName: text('.game-boxscore__team--home .game-boxscore__team-name'),
      homeScore: text('.game-boxscore__team--home .game-boxscore__score'),
    };
  });
}

function overlaps(a: Box, b: Box): boolean {
  return a.left < b.right - TOL && b.left < a.right - TOL
    && a.top < b.bottom - TOL && b.top < a.bottom - TOL;
}

test.describe('GameBoxscore scoreboard on phones', () => {
  for (const width of PHONE_WIDTHS) {
    for (const pair of PAIRINGS) {
      test(`no overlap or spill at ${width}px (${pair.away} @ ${pair.home})`, async ({ page }) => {
        await page.setViewportSize({ width, height: 812 });
        await page.goto(URL);
        await expect(page.locator('.game-boxscore__scoreboard')).toBeVisible();
        await injectMatchup(page, pair);

        await expect(page.locator('.game-boxscore__at')).toBeVisible();
        const b = await measure(page);
        const parts = ['awayLogo', 'awayName', 'awayScore', 'at', 'homeLogo', 'homeName', 'homeScore'];

        // Every part has a real box (the "@" is not hidden or zero-width).
        for (const k of parts) {
          expect(b[k].right - b[k].left, `${k} width`).toBeGreaterThan(0);
        }
        // No two parts intersect, within a side or across sides.
        for (let i = 0; i < parts.length; i++) {
          for (let j = i + 1; j < parts.length; j++) {
            expect(overlaps(b[parts[i]], b[parts[j]]), `${parts[i]} overlaps ${parts[j]}`).toBe(false);
          }
        }
        // Nothing leaves the card on the left or right.
        for (const k of parts) {
          expect(b[k].left, `${k} left edge`).toBeGreaterThanOrEqual(b.card.left - TOL);
          expect(b[k].right, `${k} right edge`).toBeLessThanOrEqual(b.card.right + TOL);
        }
        await assertNoHorizontalOverflow(page, `scoreboard ${width}px`);
      });
    }
  }

  test('desktop keeps the three-column row layout', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(URL);
    const d = await page.evaluate(() => {
      const q = (s: string) => document.querySelector(s) as HTMLElement;
      const cs = (s: string) => getComputedStyle(q(s));
      const mid = (s: string) => { const r = q(s).getBoundingClientRect(); return (r.top + r.bottom) / 2; };
      const left = (s: string) => q(s).getBoundingClientRect().left;
      return {
        tracks: cs('.game-boxscore__matchup').gridTemplateColumns.trim().split(/\s+/).length,
        awayDir: cs('.game-boxscore__team--away').flexDirection,
        homeDir: cs('.game-boxscore__team--home').flexDirection,
        logoWidth: q('.game-boxscore__team--away .game-boxscore__logo').getBoundingClientRect().width,
        awayMid: mid('.game-boxscore__team--away'),
        atMid: mid('.game-boxscore__at'),
        homeMid: mid('.game-boxscore__team--home'),
        awayLogoX: left('.game-boxscore__team--away .game-boxscore__logo'),
        awayNameX: left('.game-boxscore__team--away .game-boxscore__team-name'),
        awayScoreX: left('.game-boxscore__team--away .game-boxscore__score'),
        atX: left('.game-boxscore__at'),
        homeLogoX: left('.game-boxscore__team--home .game-boxscore__logo'),
      };
    });
    expect(d.tracks).toBe(3);
    expect(d.awayDir).toBe('row');
    expect(d.homeDir).toBe('row');
    expect(d.logoWidth).toBe(48);
    expect(Math.abs(d.awayMid - d.homeMid)).toBeLessThan(2);
    expect(Math.abs(d.awayMid - d.atMid)).toBeLessThan(2);
    expect(d.awayLogoX).toBeLessThan(d.awayNameX);
    expect(d.awayNameX).toBeLessThan(d.awayScoreX);
    expect(d.awayScoreX).toBeLessThan(d.atX);
    expect(d.atX).toBeLessThan(d.homeLogoX);
  });
});
