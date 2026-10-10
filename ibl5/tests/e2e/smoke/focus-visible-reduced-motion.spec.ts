import type { Locator, Page } from '@playwright/test';
import { test, expect } from '../fixtures/public';
import { gotoWithRetry } from '../helpers/navigation';

/**
 * Keyboard focus rings and prefers-reduced-motion.
 *
 * Seed-free: each test injects a probe element carrying the production class
 * name into the logged-out homepage (which only supplies the compiled
 * stylesheet), then drives it with the keyboard.
 */

async function injectProbe(page: Page, markup: string): Promise<Locator> {
  await page.evaluate((html) => {
    const probe = document.createElement('div');
    probe.id = 'fv-probe';
    probe.innerHTML = html;
    document.body.appendChild(probe);
  }, markup);
  return page.locator('[data-fv="target"]');
}

async function tabInto(page: Page, markup: string): Promise<Locator> {
  const target = await injectProbe(
    page,
    `<button id="fv-start" type="button">start</button>${markup}`,
  );
  await page.locator('#fv-start').focus();
  await page.keyboard.press('Tab');
  // A display:none target fails here instead of passing on a stale `none`.
  await expect(target).toBeFocused();
  return target;
}

async function expectRing(target: Locator): Promise<void> {
  await expect
    .poll(() => target.evaluate((el) => getComputedStyle(el).boxShadow))
    .not.toBe('none');
}

test.describe('keyboard focus rings and reduced motion', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'index.php');
  });

  // Mirrors classes/UI/Components/TooltipLabel.php:39
  test('.ibl-tooltip shows a ring on keyboard focus', async ({ page }) => {
    const target = await tabInto(
      page,
      '<span class="ibl-tooltip" title="tip" tabindex="0" data-fv="target">T</span>',
    );
    await expectRing(target);
  });

  // Mirrors classes/Player/Views/PlayerMenuView.php:148. The class is
  // display:none outside max-width: 640px (navigation.css), so shrink first.
  test('.plr-nav__mobile-select shows a ring on keyboard focus', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    const target = await tabInto(
      page,
      '<select class="plr-nav__mobile-select" aria-label="x" data-fv="target"><option>a</option></select>',
    );
    await expectRing(target);
  });

  test('.nav-select pin', async ({ page }) => {
    const target = await tabInto(
      page,
      '<select class="nav-select" data-fv="target"><option>a</option></select>',
    );
    await expectRing(target);
  });

  test('.nav-login-input pin', async ({ page }) => {
    const target = await tabInto(
      page,
      '<input class="nav-login-input" type="text" data-fv="target">',
    );
    await expectRing(target);
  });

  test('.last-sim-recap__tab pin', async ({ page }) => {
    const target = await tabInto(
      page,
      '<button class="last-sim-recap__tab" type="button" data-fv="target">tab</button>',
    );
    await expectRing(target);
  });

  // The wrapper supplies --team-color-primary; the ring variables only exist
  // under [style*="--team-color-primary"] (tokens/tokens.css).
  test('.ibl-view-select pin', async ({ page }) => {
    const target = await tabInto(
      page,
      '<div style="--team-color-primary:#1e3a5f;--team-color-secondary:#d4af37"><select class="ibl-view-select" data-fv="target"><option>a</option></select></div>',
    );
    await expectRing(target);
  });

  // Mirrors classes/Boxscore/BoxscoreView.php:136
  test('.all-star-rename__input pin', async ({ page }) => {
    const target = await tabInto(
      page,
      '<input class="all-star-rename__input" type="text" data-fv="target">',
    );
    await expectRing(target);
  });

  test('mouse click leaves the tooltip ring off', async ({ page }) => {
    const target = await injectProbe(
      page,
      '<span class="ibl-tooltip" title="tip" tabindex="0" data-fv="target">T</span>',
    );
    await target.click();
    await expect(target).toBeFocused();
    await expect
      .poll(() => target.evaluate((el) => getComputedStyle(el).boxShadow))
      .toBe('none');
  });

  test('reduced motion: .ibl-input transition is 0s', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const target = await injectProbe(page, '<input class="ibl-input" data-fv="target">');
    expect(await target.evaluate((el) => getComputedStyle(el).transitionDuration)).toBe('0s');
  });

  test('reduced motion: spinner animation is 0s', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const target = await injectProbe(
      page,
      '<span class="updater-step__spinner" data-fv="target"></span>',
    );
    expect(await target.evaluate((el) => getComputedStyle(el).animationDuration)).toBe('0s');
  });

  test('no reduced-motion preference keeps transitions', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const target = await injectProbe(page, '<input class="ibl-input" data-fv="target">');
    expect(await target.evaluate((el) => getComputedStyle(el).transitionDuration)).not.toBe('0s');
  });
});
