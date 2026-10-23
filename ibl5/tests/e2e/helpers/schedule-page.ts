import { expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from './php-errors';

/**
 * Shared structural assertions for the Schedule pages (league + team views).
 * Each consumer supplies its own `appState`/navigation before calling these.
 */

export async function assertScheduleStructure(
  page: Page,
  opts: { minGames: number },
): Promise<void> {
  await expect(page.locator('.ibl-title').first()).toBeVisible();
  await assertNoPhpErrors(page, 'on Schedule');
  await expect(page.locator('.sos-legend__item')).toHaveCount(5);
  await expect(page.locator('.schedule-months__link').first()).toBeVisible();
  expect(await page.locator('.schedule-game').count())
    .toBeGreaterThanOrEqual(opts.minGames);
}

export async function assertUnplayedGameDash(page: Page): Promise<void> {
  await expect(
    page.locator('.schedule-game span.schedule-game__score-link', { hasText: '–' }).first(),
  ).toBeVisible();
}

export async function assertPlayedGameScores(page: Page): Promise<void> {
  const texts = await page.locator('.schedule-game .schedule-game__score-link').allTextContents();
  expect(texts.filter((t) => /\d+/.test(t)).length).toBeGreaterThanOrEqual(2);
  await expect(page.locator('.schedule-game__team--win').first()).toBeVisible();
}

export async function assertPlayoffPhaseLabels(page: Page): Promise<void> {
  await assertNoPhpErrors(page, 'on Schedule (Playoffs)');
  await expect(page.locator('.schedule-month__header--playoffs').first()).toBeVisible();
}

/**
 * The CI seed schedule is too short to scroll. Push the element with `targetId`
 * 3000px down with a top margin and append 2000px of trailing room so
 * window.scrollTo can place it anywhere in the viewport. Throws when the id is
 * absent so a markup change fails loudly instead of asserting on nothing.
 */
export async function injectScrollRoom(page: Page, targetId: string): Promise<void> {
  await page.evaluate((id) => {
    const target = document.getElementById(id);
    if (target === null) {
      throw new Error(`scroll target #${id} not found`);
    }
    target.style.marginTop = '3000px';
    const tail = document.createElement('div');
    tail.setAttribute('data-e2e-scroll-spacer', '');
    tail.style.height = '2000px';
    document.body.appendChild(tail);
  }, targetId);
}

export interface TargetPosition {
  top: number;
  centre: number;
  viewport: number;
}

/** Live getBoundingClientRect of the element, plus the viewport height. */
export async function readTargetPosition(page: Page, targetId: string): Promise<TargetPosition> {
  return page.evaluate((id) => {
    const rect = document.getElementById(id)?.getBoundingClientRect();
    return {
      top: rect === undefined ? Number.NaN : rect.top,
      centre: rect === undefined ? Number.NaN : rect.top + rect.height / 2,
      viewport: window.innerHeight,
    };
  }, targetId);
}
