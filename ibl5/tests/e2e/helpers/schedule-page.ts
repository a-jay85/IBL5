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
