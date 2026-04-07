import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Depth Chart Entry — authenticated page.
// The roster form may load asynchronously after the page header renders.
// NOTE: Do NOT submit the form — that would mutate data.

test.describe('Depth Chart Entry flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('page loads with title and team banner', async ({ page }) => {
    // The title uses .ibl-title with CSS text-transform: uppercase
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    // Authenticated user sees their team banner
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('saved depth chart dropdown present', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();
    const options = dropdown.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('roster form loads with player rows', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    await expect(
      page.locator('.depth-chart-table').first(),
    ).toBeVisible();

    // Player rows should have data-pid attributes
    const playerRows = page.locator('.depth-chart-table tr[data-pid]');
    await expect(playerRows.first()).toBeVisible();
  });

  test('role slot selects have options', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Position selects (pg/sg/sf/pf/c) are now hidden inputs; role slot
    // selects use field names BH, DI, OI, DF, OF for PG/SG/SF/PF/C columns.
    const roleSelect = page.locator('select[name^="BH"]').first();
    await expect(roleSelect).toBeVisible();
    const options = roleSelect.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('active checkboxes render as form controls', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Active field is a checkbox (not a select) as of the depth-chart-redesign
    // refactor. Use the desktop `.dc-active-cb` class to disambiguate from the
    // mobile `.dc-card__active-cb` which shares the name prefix.
    const activeCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    await expect(activeCheckboxes.first()).toBeAttached();
    // At least one player in the seed should have `checked` pre-set.
    const count = await activeCheckboxes.count();
    expect(count).toBeGreaterThan(0);
  });

  test('minutes renders as number input 0-40', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    const minInputs = page.locator('input[type="number"][name^="min"]');
    await expect(minInputs.first()).toBeAttached();
    // The server constrains the input to 0-40 via min/max attributes.
    await expect(minInputs.first()).toHaveAttribute('min', '0');
    await expect(minInputs.first()).toHaveAttribute('max', '40');
    await expect(minInputs.first()).toHaveAttribute('step', '1');
  });

  test('reset button prompts confirmation', async ({ page }) => {
    const resetBtn = page.locator('.depth-chart-buttons .depth-chart-reset-btn');
    await expect(resetBtn).toBeVisible({ timeout: 15000 });

    let dialogFired = false;
    page.on('dialog', async (dialog) => {
      dialogFired = true;
      await dialog.dismiss();
    });

    await resetBtn.click();
    expect(dialogFired).toBe(true);
  });

  test('reset accepted: selects cleared to 0, minutes blanked, active boxes checked', async ({
    page,
  }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Mutate form state so reset has something to revert. Desktop and mobile
    // cards share the same input names (name="BH1" etc.), so every locator
    // must scope to `.depth-chart-table` to avoid strict-mode violations
    // against the mobile card's disabled duplicates.
    const firstBh = page.locator('.depth-chart-table select[name^="BH"]').first();
    await firstBh.selectOption('1');

    const firstMin = page
      .locator('.depth-chart-table input[type="number"][name^="min"]')
      .first();
    await firstMin.fill('25');

    const firstActive = page
      .locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]')
      .first();
    // Force the checkbox to unchecked regardless of seed state.
    if (await firstActive.isChecked()) {
      await firstActive.uncheck();
    }

    // Accept the confirm() dialog raised by resetDepthChart().
    page.once('dialog', (dialog) => {
      void dialog.accept();
    });

    await page.locator('.depth-chart-buttons .depth-chart-reset-btn').click();

    // All role slot selects should now be 0.
    const nonZeroSelects = await page.evaluate(() => {
      const form = document.forms.namedItem('DepthChartEntry');
      if (!form) return -1;
      const selects = Array.from(form.querySelectorAll('select'));
      return selects.filter((s) => s.value !== '0').length;
    });
    expect(nonZeroSelects).toBe(0);

    // All min inputs should be blank (empty string — server converts to 0).
    const nonBlankMins = await page.evaluate(() => {
      const form = document.forms.namedItem('DepthChartEntry');
      if (!form) return -1;
      const mins = Array.from(
        form.querySelectorAll<HTMLInputElement>('input[type="number"][name^="min"]'),
      );
      return mins.filter((m) => m.value !== '').length;
    });
    expect(nonBlankMins).toBe(0);

    // All canPlayInGame checkboxes (desktop + mobile) should be checked.
    const uncheckedActive = await page.evaluate(() => {
      const form = document.forms.namedItem('DepthChartEntry');
      if (!form) return -1;
      const cbs = Array.from(
        form.querySelectorAll<HTMLInputElement>(
          'input[type="checkbox"][name^="canPlayInGame"]',
        ),
      );
      return cbs.filter((c) => !c.checked).length;
    });
    expect(uncheckedActive).toBe(0);
  });

  test('lineup preview recalculates when a role slot value changes', async ({
    page,
  }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    const preview = page.locator('#dc-lineup-preview');
    await expect(preview.locator('.dc-lineup-preview__title')).toContainText(
      /projected lineup/i,
    );
    await expect(preview.locator('.dc-lineup-preview-table')).toBeVisible();

    // Install a MutationObserver on the preview container. depth-chart-lineup-
    // preview.js re-renders via `container.innerHTML = html`, which replaces
    // the entire childList subtree — the observer fires even when the new
    // HTML is byte-identical, so we get a clean "recalculate happened"
    // signal without depending on the BH change producing a visible diff.
    // (For many rosters a BH=0→1 promotion produces identical output
    // because the new candidate's score doesn't beat the incumbents.)
    await page.evaluate(() => {
      const container = document.getElementById('dc-lineup-preview');
      if (!container) return;
      const w = window as typeof window & {
        __ibl_preview_mutations?: number;
        __ibl_preview_observer?: MutationObserver;
      };
      w.__ibl_preview_mutations = 0;
      w.__ibl_preview_observer?.disconnect();
      const observer = new MutationObserver(() => {
        w.__ibl_preview_mutations = (w.__ibl_preview_mutations ?? 0) + 1;
      });
      observer.observe(container, { childList: true, subtree: true });
      w.__ibl_preview_observer = observer;
    });

    // Change a desktop BH select (scoped to `.depth-chart-table` to avoid
    // strict-mode collisions with the mobile card duplicates). Any change
    // triggers the delegated form listener in depth-chart-lineup-preview.js.
    const firstBh = page.locator('.depth-chart-table select[name^="BH"]').first();
    const originalBh = await firstBh.inputValue();
    await firstBh.selectOption(originalBh === '0' ? '1' : '0');

    await expect
      .poll(
        async () =>
          page.evaluate(
            () =>
              (window as typeof window & { __ibl_preview_mutations?: number })
                .__ibl_preview_mutations ?? 0,
          ),
        { timeout: 3000 },
      )
      .toBeGreaterThan(0);
  });

  test('lineup preview minute shares update when dc_minutes changes', async ({
    page,
  }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    const preview = page.locator('#dc-lineup-preview');
    await expect(preview.locator('.dc-lineup-preview-table')).toBeVisible();
    await expect(preview.locator('.dc-lineup-preview__starter').first()).toBeVisible();

    // Target the FIRST starter in the preview: since they're rendered with a
    // .dc-lineup-preview__mins annotation, mutating their min input is
    // guaranteed to change that annotation. We resolve the starter's pid
    // from the preview link href, look up their row in the desktop table,
    // and read the matching minN input name. This avoids the failure mode
    // where we change a bench player's min and see no rendered diff.
    const target = await page.evaluate(() => {
      const starterCell = document.querySelector<HTMLTableCellElement>(
        '#dc-lineup-preview .dc-lineup-preview__starter',
      );
      if (!starterCell) return null;
      const link = starterCell.querySelector<HTMLAnchorElement>('a[href*="pid="]');
      if (!link) return null;
      const pidMatch = link.getAttribute('href')?.match(/pid=(\d+)/);
      if (!pidMatch) return null;
      const pid = pidMatch[1];
      const row = document.querySelector(
        `.depth-chart-table tr[data-pid="${pid}"]`,
      );
      if (!row) return null;
      const pidInput = row.querySelector<HTMLInputElement>('input[name^="pid"]');
      if (!pidInput) return null;
      return { pid, suffix: pidInput.name.replace('pid', '') };
    });
    expect(target, 'preview should render at least one starter').not.toBeNull();

    const minsBefore = await preview
      .locator('.dc-lineup-preview__mins')
      .allInnerTexts();
    expect(minsBefore.length).toBeGreaterThan(0);

    // Change this starter's dc_minutes input to a distinctly different value
    // and dispatch 'change' explicitly — the preview listens on both 'input'
    // and 'change', but fill() only fires 'input' for number inputs.
    const minInput = page.locator(
      `.depth-chart-table input[type="number"][name="min${target!.suffix}"]`,
    );
    const origMin = await minInput.inputValue();
    const newMin = origMin === '17' ? '19' : '17';
    await minInput.fill(newMin);
    await minInput.dispatchEvent('change');

    await expect
      .poll(
        async () => {
          const after = await preview
            .locator('.dc-lineup-preview__mins')
            .allInnerTexts();
          return JSON.stringify(after) !== JSON.stringify(minsBefore);
        },
        { timeout: 3000 },
      )
      .toBe(true);
  });

  test('loading a saved DC populates selects, minutes, and active checkbox', async ({
    page,
  }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Read the actual pids from the first two rows so the test works against
    // any roster (CI seed, worktree prod snapshot, etc.) — the roster varies
    // between environments but the client-side populateForm() logic under
    // test doesn't. We'll mock the API response to echo these real pids back
    // with deterministic values we control.
    const rosterPids = await page.evaluate(() => {
      const rows = Array.from(document.querySelectorAll('tr[data-pid]')).slice(
        0,
        2,
      );
      return rows.map((row) => {
        const pid = row.getAttribute('data-pid');
        const pidInput = row.querySelector<HTMLInputElement>('input[name^="pid"]');
        return {
          pid: pid ? parseInt(pid, 10) : null,
          suffix: pidInput ? pidInput.name.replace('pid', '') : null,
        };
      });
    });
    expect(rosterPids.length).toBe(2);
    expect(rosterPids[0].pid).not.toBeNull();
    expect(rosterPids[0].suffix).not.toBeNull();
    expect(rosterPids[1].pid).not.toBeNull();
    expect(rosterPids[1].suffix).not.toBeNull();

    // Mock the saved-DC load endpoint with deterministic values tied to the
    // real pids so we can assert exact form-field population. This isolates
    // the client-side populateForm() logic from seed-data variability.
    // The URL shape is: modules.php?name=DepthChartEntry&op=api&action=load&id=N
    // (apiBaseUrl is wired in DepthChartEntryController.php).
    await page.route(
      '**/modules.php?name=DepthChartEntry&op=api&action=load**',
      async (route) => {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            depthChart: { id: 1, name: 'Offensive Config' },
            players: [
              {
                pid: rosterPids[0].pid,
                name: 'Mock Player One',
                pos: 'SG',
                dc_canPlayInGame: 1,
                dc_minutes: 34,
                dc_bh: 1,
                dc_di: 2,
                // BH/DI/OI selects clamp to max=2 (see renderRolePriorityOptions),
                // so we use 2 here — any value >2 would silently drop to blank.
                dc_oi: 2,
                dc_df: 0,
                dc_of: 0,
                isOnCurrentRoster: true,
              },
              {
                pid: rosterPids[1].pid,
                name: 'Mock Player Two',
                pos: 'PF',
                dc_canPlayInGame: 0,
                dc_minutes: 18,
                dc_bh: 0,
                dc_di: 0,
                dc_oi: 0,
                dc_df: 1,
                dc_of: 2,
                isOnCurrentRoster: true,
              },
            ],
            stats: {},
          }),
        });
      },
    );

    // Trigger the load by picking the second dropdown option.
    const dropdown = page.locator('#saved-dc-select');
    await dropdown.selectOption({ index: 1 });

    // Poll until the form reflects the mocked payload. We query the desktop
    // `.depth-chart-table` directly rather than `form.elements.namedItem()`
    // because the mobile cards duplicate every input name, which makes
    // namedItem() return a RadioNodeList (no .value accessor for selects).
    await expect
      .poll(
        async () => {
          return await page.evaluate(
            (counts: { p1: string; p2: string }) => {
              const table = document.querySelector('.depth-chart-table');
              if (!table) return null;
              const val = (name: string) =>
                (
                  table.querySelector<
                    HTMLInputElement | HTMLSelectElement
                  >(`[name="${name}"]`)
                )?.value ?? null;
              const cb = (name: string) =>
                (
                  table.querySelector<HTMLInputElement>(
                    `input[type="checkbox"][name="${name}"]`,
                  )
                )?.checked ?? null;
              return {
                p1_bh: val(`BH${counts.p1}`),
                p1_di: val(`DI${counts.p1}`),
                p1_oi: val(`OI${counts.p1}`),
                p1_min: val(`min${counts.p1}`),
                p1_active: cb(`canPlayInGame${counts.p1}`),
                p2_df: val(`DF${counts.p2}`),
                p2_of: val(`OF${counts.p2}`),
                p2_min: val(`min${counts.p2}`),
                p2_active: cb(`canPlayInGame${counts.p2}`),
              };
            },
            { p1: rosterPids[0].suffix!, p2: rosterPids[1].suffix! },
          );
        },
        { timeout: 5000 },
      )
      .toEqual({
        p1_bh: '1',
        p1_di: '2',
        p1_oi: '2',
        p1_min: '34',
        p1_active: true,
        p2_df: '1',
        p2_of: '2',
        p2_min: '18',
        p2_active: false,
      });
  });

  test('submit button present when form loaded', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    await expect(page.locator('.depth-chart-buttons .depth-chart-submit-btn')).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Depth Chart Entry');
  });
});

// ===========================================================================
// NextSim position tab switching
// ===========================================================================

test.describe('DCE: NextSim position tabs', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('position tabs render in NextSim section', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    // Should have 5 position tabs: PG, SG, SF, PF, C
    await expect(tabs).toHaveCount(5);
    await expect(tabs.first()).toBeVisible();
  });

  test('tab click loads content without PHP errors', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    await expect(tabs).toHaveCount(5);

    // Click a non-default tab and wait for HTMX response
    const sfTab = tabs.nth(2);
    await Promise.all([
      page.waitForResponse(resp => resp.url().includes('nextsim-api') && resp.status() === 200),
      sfTab.click(),
    ]);
    await assertNoPhpErrors(page, 'after NextSim tab switch');
  });
});
