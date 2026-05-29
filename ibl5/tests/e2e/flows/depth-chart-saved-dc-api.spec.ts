import { test, expect } from '../fixtures/auth-isolated';
import { resetSavedDcNames } from '../helpers/cleanup';

// API-test coverage for the saved depth chart rename JSON endpoints.
//
// Isolation: auth-isolated sets `_test_team=Monarchs` so DepthChartEntry
// resolves to Monarchs (tid=8). Seed rows: id=10 ("DC Test Offense"),
// id=11 ("DC Test Defense"), both is_active=0.
//
// Serial mode is required because rename mutations change shared row names;
// running in parallel would leave the list endpoint in an unpredictable state.
test.describe.configure({ mode: 'serial' });

const API_URL = 'modules.php?name=DepthChartEntry&op=api';

test.describe('Saved DC JSON API', () => {
  test.afterAll(async ({ request }) => {
    await resetSavedDcNames(request, 8);
  });

  test('rename round-trip: GET list finds id=10, POST rename, GET list confirms new name', async ({ page }) => {
    // Step 1: GET action=list — confirm seed row id=10 is present.
    const listResp1 = await page.request.get(`${API_URL}&action=list`);
    expect(listResp1.ok()).toBe(true);
    const list1 = await listResp1.json();
    expect(Array.isArray(list1.depthCharts)).toBe(true);

    const before = (list1.depthCharts as Array<{ id: number; name: string }>).find(
      (dc) => dc.id === 10,
    );
    expect(before).toBeDefined();

    // Step 2: POST action=rename with JSON body {id, name}.
    const renameResp = await page.request.post(`${API_URL}&action=rename`, {
      data: { id: 10, name: 'E2E Renamed' },
    });
    expect(renameResp.ok()).toBe(true);
    const renamed = await renameResp.json();
    expect(renamed.success).toBe(true);
    expect(renamed.name).toBe('E2E Renamed');

    // Step 3: GET action=list again — id=10 must carry the new name.
    const listResp2 = await page.request.get(`${API_URL}&action=list`);
    expect(listResp2.ok()).toBe(true);
    const list2 = await listResp2.json();

    const after = (list2.depthCharts as Array<{ id: number; name: string }>).find(
      (dc) => dc.id === 10,
    );
    expect(after).toBeDefined();
    expect(after!.name).toBe('E2E Renamed');
  });

  test('rename-active round-trip: POST sets active name, GET list reflects it', async ({ page }) => {
    // POST action=rename-active with JSON body {name}.
    const renameActiveResp = await page.request.post(`${API_URL}&action=rename-active`, {
      data: { name: 'E2E Active Name' },
    });
    expect(renameActiveResp.ok()).toBe(true);
    const renameActive = await renameActiveResp.json();
    expect(renameActive.success).toBe(true);

    // GET action=list — either currentLiveLabel contains the name, or the
    // active entry's name equals it.
    const listResp = await page.request.get(`${API_URL}&action=list`);
    expect(listResp.ok()).toBe(true);
    const list = await listResp.json();

    const activeDc = (list.depthCharts as Array<{ id: number; name: string; isActive: boolean }>).find(
      (dc) => dc.isActive,
    );

    const labelContains =
      typeof list.currentLiveLabel === 'string' &&
      list.currentLiveLabel.includes('E2E Active Name');
    const activeEntryMatches = activeDc !== undefined && activeDc.name === 'E2E Active Name';

    expect(labelContains || activeEntryMatches).toBe(true);
  });
});
