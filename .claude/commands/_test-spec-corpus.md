---
description: Calibration corpus of known-good and known-bad E2E spec patterns for the Agent D (E2E specs) reviewer.
last_verified: 2026-05-23
---

# E2E Spec Reviewer Calibration Corpus

Read this file before judging any spec. Each section matches an Agent D section. Anchor your flag/no-flag decisions against these examples.

---

## Section 1 — POST-effect

### Known-bad (flag)

**1a. Same-page success banner only**

```ts
test('submit trade proposal', async ({ page }) => {
  await page.goto('modules.php?name=Trading');
  await page.fill('#player-name', 'John Smith');
  await page.click('button[type="submit"]');
  await expect(page.locator('.alert--success')).toBeVisible();
});
```

Why bad: `.alert--success` proves the controller rendered a success message — not that the trade was persisted to the database. The test passes even if the INSERT silently failed.

**1b. Same-page flash message**

```ts
test('submit waiver claim', async ({ page }) => {
  await page.goto('modules.php?name=Waivers');
  await page.selectOption('#player-select', 'Jane Doe');
  await page.click('#claim-btn');
  await expect(page.locator('.flash')).toContainText('Claim submitted');
});
```

Why bad: `.flash` is a same-page element rendered by the controller's redirect-back. No verification that the claim row exists in the database.

**1c. Voting submission with only class check**

```ts
test('cast MVP vote', async ({ page }) => {
  await page.goto('modules.php?name=Voting');
  await page.selectOption('#mvp-select', 'Player One');
  await page.click('#submit-vote');
  await expect(page.locator('.voting-submission-success')).toBeVisible();
});
```

Why bad: `.voting-submission-success` is a same-page success indicator. Does not prove the vote was recorded.

**1d. Reload trick (still bad)**

```ts
test('submit lineup', async ({ page }) => {
  await page.goto('modules.php?name=Lineup');
  await page.click('#starter-1');
  await page.click('#save-lineup');
  await expect(page.locator('.alert--success')).toBeVisible();
  await page.reload();
  await expect(page.locator('.alert--success')).toBeVisible();
});
```

Why bad: Reloading the same page re-renders the same view state. The success banner may be flash-session-based and disappear, or it may persist from the query string. Neither proves persistence — the test needs to navigate to a *different* page that reads back the saved lineup.

**1e. Multiple same-page assertions**

```ts
test('submit free agent signing', async ({ page }) => {
  await page.goto('modules.php?name=FreeAgents');
  await page.fill('#offer-amount', '5000000');
  await page.click('#sign-btn');
  await expect(page.locator('.alert--success')).toBeVisible();
  await expect(page.locator('#offer-amount')).toHaveValue('');
});
```

Why bad: Form clearing + success banner are both same-page controller behavior. No cross-page read-back proving the signing persisted.

### Known-good (do not flag)

**1f. Cross-page navigation + destination assertion**

```ts
test('submit trade proposal', async ({ page }) => {
  await page.goto('modules.php?name=Trading');
  await page.fill('#player-name', 'John Smith');
  await page.click('button[type="submit"]');
  await page.waitForURL(/modules\.php\?name=Transactions/);
  await expect(page.locator('.ibl-data-table')).toContainText('John Smith');
});
```

Why good: Navigates to Transactions page and asserts the trade appears there — proves persistence.

**1g. API read-back**

```ts
test('submit draft pick', async ({ page, request }) => {
  await page.goto('modules.php?name=Draft');
  await page.click('#pick-player');
  await page.click('button[type="submit"]');
  const res = await request.get('/api/v1/draft-picks?round=1');
  const data = await res.json();
  expect(data.picks).toContainEqual(expect.objectContaining({ player: 'John Smith' }));
});
```

Why good: API read-back independently verifies the draft pick was persisted.

**1h. goto different module + assert**

```ts
test('submit roster move', async ({ page }) => {
  await page.goto('modules.php?name=Roster');
  await page.click('#activate-player');
  await page.click('#confirm');
  await page.goto('modules.php?name=Transactions');
  await expect(page.locator('table')).toContainText('Activated');
});
```

Why good: Explicit navigation to a different module and assertion on the destination.

**1i. Error-path test (exempt)**

```ts
test('reject trade with too few players', async ({ page }) => {
  await page.goto('modules.php?name=Trading');
  await page.click('button[type="submit"]');
  await expect(page.locator('.alert--danger')).toContainText('at least');
});
```

Why good: Error-path — intentionally verifying validation rejection. No POST effect expected.

**1j. Error-path duplicate submission (exempt)**

```ts
test('duplicate vote shows error', async ({ page }) => {
  await page.goto('modules.php?name=Voting');
  await page.click('#submit-vote');
  await page.click('#submit-vote');
  await expect(page.locator('.alert--warning')).toContainText('already voted');
});
```

Why good: Error-path — tests duplicate-submission guard. Correct to assert on same-page error.

---

## Section 2 — Assertion discrimination

### Known-bad (flag)

**2a. Body visibility**

```ts
test('standings page loads data', async ({ page }) => {
  await page.goto('modules.php?name=Standings');
  await expect(page.locator('body')).toBeVisible();
});
```

Why bad: `body` is visible on every page including 500 error pages. This proves nothing about Standings.

**2b. Title-only assertion in a flow test**

```ts
test('trade form shows correct teams', async ({ page }) => {
  await page.goto('modules.php?name=Trading');
  await expect(page).toHaveTitle(/IBL/i);
});
```

Why bad: Every page has "IBL" in the title, including error pages. The test name claims to verify teams but asserts nothing about them.

**2c. Uncounted .first()**

```ts
test('roster shows all players', async ({ page }) => {
  await page.goto('modules.php?name=Roster&team=Heat');
  await expect(page.locator('.player-row').first()).toBeVisible();
});
```

Why bad: Test name says "all players" but `.first()` passes with just one row. Needs `toHaveCount(N)` or a minimum count assertion.

**2d. Header text as discrimination**

```ts
test('stats page renders correctly', async ({ page }) => {
  await page.goto('modules.php?name=Stats');
  const body = await page.textContent('body');
  expect(body).toContain('IBL');
});
```

Why bad: "IBL" appears in the site-wide header on every page. This is not discriminating for the Stats page.

**2e. Generic fallback selector**

```ts
test('schedule displays games', async ({ page }) => {
  await page.goto('modules.php?name=Schedule');
  await expect(page.locator('td')).toBeVisible();
});
```

Why bad: `td` is too generic — present on error templates, nav tables, etc. Use a module-specific selector.

### Known-good (do not flag)

**2f. Smoke test with generic assertion (exempt)**

```ts
test('standings page loads', async ({ page }) => {
  await page.goto('modules.php?name=Standings');
  await expect(page.locator('.ibl-data-table').first()).toBeVisible();
});
```

Why good: Smoke test (name says "loads") — generic visibility is the explicit purpose. Exempt from Section 2.

**2g. Specific count assertion**

```ts
test('standings shows all teams', async ({ page }) => {
  await page.goto('modules.php?name=Standings');
  await expect(page.locator('.standings-row')).toHaveCount(28);
});
```

Why good: Discriminating — 28 rows is specific to a valid standings render. Error pages don't produce 28 `.standings-row` elements.

**2h. Validation-specific text**

```ts
test('trade with too few players shows error', async ({ page }) => {
  await page.goto('modules.php?name=Trading');
  await page.click('button[type="submit"]');
  await expect(page.locator('.alert--danger')).toContainText('less than FOUR');
});
```

Why good: "less than FOUR" is a validation message specific to this form — not rendered by the global header or error templates.

**2i. Module-specific class**

```ts
test('depth chart renders', async ({ page }) => {
  await page.goto('modules.php?name=DepthChart');
  await expect(page.locator('.depth-chart-position')).toHaveCount(5);
});
```

Why good: `.depth-chart-position` is module-specific, and count of 5 is discriminating.

**2j. Smoke test with explicit "renders" name (exempt)**

```ts
test('free agents page renders', async ({ page }) => {
  await page.goto('modules.php?name=FreeAgents');
  await expect(page.locator('#main')).toBeVisible();
});
```

Why good: Test name says "renders" — smoke test, exempt from Section 2.

---

## Section 3 — Coverage-branch

### Known-bad (flag)

**3a. New phase-gated view without spec coverage**

Production diff adds:
```php
if ($phase === 'Playoffs') {
    echo '<div class="playoff-bracket">...</div>';
}
```

Spec diff has no new `test(...)` block calling `appState({ 'Current Season Phase': 'Playoffs' })`.

Why bad: New user-visible UI state with no E2E coverage — regressions in the playoff bracket will be invisible.

**3b. New HTMX-swapped admin tab without spec**

Production diff adds:
```php
<button hx-get="modules.php?name=Admin&tab=audit" hx-target="#tab-content">Audit Log</button>
```

No new spec covers clicking the tab and asserting on `#tab-content`.

Why bad: New interactive UI element with no E2E verifying the swap renders correctly.

**3c. New conditional details/modal without spec**

Production diff adds:
```php
<details>
  <summary>Advanced Trade Options</summary>
  <div class="trade-advanced"><?= $advancedForm ?></div>
</details>
```

No new spec verifies the `<details>` opens and shows `.trade-advanced` content.

Why bad: New expandable section — E2E is required per plan-verification forced-E2E triggers.

### Known-good (do not flag)

**3d. Pure refactor — no new user-visible state**

Production diff moves logic from `Trading/index.php` to `Trading/TradeFormRenderer.php`. Same HTML output. No new conditional branches.

Why good: No new user-visible state — refactoring the rendering path doesn't require new specs.

**3e. New phase-gated state WITH matching spec**

Production diff adds:
```php
if ($phase === 'Playoffs') {
    echo '<div class="playoff-bracket">...</div>';
}
```

Spec diff adds:
```ts
test('renders playoff bracket', async ({ appState, page }) => {
  await appState({ 'Current Season Phase': 'Playoffs' });
  await page.goto('modules.php?name=Standings');
  await expect(page.locator('.playoff-bracket')).toBeVisible();
});
```

Why good: New state and matching spec — coverage present.

**3f. Module not covered by any spec (Agent D not launched)**

Production diff touches an admin module but no e2e spec was changed. Agent D was not launched for this PR (no `ibl5/tests/e2e/**/*.ts` in the diff), so there is nothing to cross-reference.

Why good: Outside Agent D's scope — the agent only runs when spec files are in the diff.

**3g. Branch gated by unreachable configuration**

Production diff adds:
```php
if ($config->isFeatureFlagEnabled('beta-dashboard')) {
    echo '<div class="beta-dash">...</div>';
}
```

The feature flag is not settable in the test environment.

Why good: Branch gated by configuration the test suite cannot reach. Do not flag.
