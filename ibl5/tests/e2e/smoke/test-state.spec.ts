import { test, expect } from '../fixtures/auth';
import { getState, setState } from '../helpers/test-state';

// Meta-tests for the test-state.php endpoint itself.

test.describe('test-state.php endpoint', () => {
  // Run serially — these tests read/write shared DB state and verify exact values.
  test.describe.configure({ mode: 'serial' });
  test('GET returns all settings as JSON', async ({ request }) => {
    const settings = await getState(request);
    expect(settings).toHaveProperty('Current Season Phase');
    expect(typeof settings['Current Season Phase']).toBe('string');
  });

  test('POST sets and returns previous values', async ({ request }) => {
    const before = await getState(request);
    const originalPhase = before['Current Season Phase'];

    const result = await setState(request, {
      'Current Season Phase': 'Playoffs',
    });

    expect(result.previous['Current Season Phase']).toBe(originalPhase);
    expect(result.applied['Current Season Phase']).toBe('Playoffs');

    // Verify it actually changed
    const after = await getState(request);
    expect(after['Current Season Phase']).toBe('Playoffs');

    // Restore
    await setState(request, { 'Current Season Phase': originalPhase });
    const restored = await getState(request);
    expect(restored['Current Season Phase']).toBe(originalPhase);
  });

  test('POST rejects unknown settings', async ({ request }) => {
    const response = await request.post('test-state.php', {
      data: { 'Nonexistent Setting': 'value' },
    });
    expect(response.status()).toBe(400);
    const body = await response.json();
    expect(body.error).toContain('Unknown settings');
  });

  test('appState fixture overrides page state via cookie', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Draft', 'Show Draft Link': 'On' });
    await page.goto('modules.php?name=Draft');
    // Verify Draft module loaded (accessible when Show Draft Link is On)
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });
});
