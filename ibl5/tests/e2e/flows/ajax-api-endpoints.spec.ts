import { test, expect } from '../fixtures/auth';

// Tab API endpoint tests — these are HTMX endpoints that return HTML fragments
// for tab/dropdown switching. The Saved Depth Chart API remains JSON.

// Helper: retry GET requests that return unexpected content (CI load)
async function fetchJson(
  request: import('@playwright/test').APIRequestContext,
  url: string,
  retries = 3,
): Promise<{ status: number; body: unknown; contentType: string }> {
  let lastStatus = 0;
  let lastContentType = '';

  for (let attempt = 0; attempt < retries; attempt++) {
    const response = await request.get(url);
    lastStatus = response.status();
    lastContentType = response.headers()['content-type'] ?? '';

    if (lastContentType.includes('json')) {
      const text = await response.text();
      let body: unknown = null;
      try {
        body = text ? JSON.parse(text) : null;
      } catch {
        // Empty or malformed JSON — return null body
      }
      return { status: lastStatus, body, contentType: lastContentType };
    }
    // Got HTML instead of JSON — brief pause before retry
    await new Promise((r) => setTimeout(r, 200));
  }

  return { status: lastStatus, body: null, contentType: lastContentType };
}

type TabApiSuite = {
  label: string;
  ratingsUrl: string;
  modes: string[];
  modeUrlFn: (mode: string) => string;
  modeMsgPrefix: string;
  allModesTitle: string;
  hxPushUrl: string;
  hxPushContains: string[];
};

function describeTabApi(cfg: TabApiSuite): void {
  test.describe(cfg.label, () => {
    test('ratings display returns HTML with table', async ({ request }) => {
      const response = await request.get(cfg.ratingsUrl);
      const contentType = response.headers()['content-type'] ?? '';
      expect(contentType, 'API endpoint must return HTML').toContain('text/html');
      expect(response.status()).toBe(200);
      const html = await response.text();
      expect(html).toContain('<table');
    });

    test(cfg.allModesTitle, async ({ request }) => {
      for (const mode of cfg.modes) {
        const response = await request.get(cfg.modeUrlFn(mode));
        expect(response.status(), `${cfg.modeMsgPrefix}${mode} should return 200`).toBe(200);
        const html = await response.text();
        expect(html.length, `${cfg.modeMsgPrefix}${mode} should return non-empty html`).toBeGreaterThan(0);
        expect(
          html,
          `${cfg.modeMsgPrefix}${mode} should render a real table, not an error page`,
        ).toContain('<table');
      }
    });

    test('response includes HX-Push-Url header', async ({ request }) => {
      const response = await request.get(cfg.hxPushUrl);
      const pushUrl = response.headers()['hx-push-url'] ?? '';
      for (const expected of cfg.hxPushContains) {
        expect(pushUrl).toContain(expected);
      }
    });
  });
}

// ============================================================
// DCE Tab API (DepthChartEntry&op=tab-api)
// Returns HTML table fragment for a given display mode
// ============================================================

describeTabApi({
  label: 'DCE Tab API',
  ratingsUrl: 'modules.php?name=DepthChartEntry&op=tab-api&teamid=1&display=ratings',
  modes: ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'],
  modeUrlFn: (mode) => `modules.php?name=DepthChartEntry&op=tab-api&teamid=1&display=${mode}`,
  modeMsgPrefix: '',
  allModesTitle: 'all valid display modes return table html',
  hxPushUrl: 'modules.php?name=DepthChartEntry&op=tab-api&teamid=1&display=contracts',
  hxPushContains: ['display=contracts'],
});

test.describe('DCE Tab API', () => {
  test('invalid display mode falls back to ratings', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=tab-api&teamid=1&display=invalid_mode',
    );
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<table');
  });
});

// ============================================================
// NextSim Tab API (DepthChartEntry&op=nextsim-api)
// Returns HTML position table fragment
// ============================================================

test.describe('NextSim Tab API', () => {
  test('position endpoints return valid HTML', async ({ request }) => {
    const positions = ['PG', 'SG', 'SF', 'PF', 'C'];

    for (const pos of positions) {
      const response = await request.get(
        `modules.php?name=DepthChartEntry&op=nextsim-api&teamid=1&position=${pos}`,
      );

      const contentType = response.headers()['content-type'] ?? '';
      expect(contentType, `${pos} API must return HTML`).toContain('text/html');

      expect(response.status(), `${pos} should return 200`).toBe(200);
      // HTML may be empty if no games in sim window — that's valid
    }
  });

  test('invalid position falls back to PG', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=nextsim-api&teamid=1&position=XX',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');
    expect(response.status()).toBe(200);
  });
});

// ============================================================
// Team API (Team&op=api)
// Returns HTML table fragment — public endpoint, no auth required
// ============================================================

describeTabApi({
  label: 'Team API',
  ratingsUrl: 'modules.php?name=Team&op=api&teamid=1&display=ratings',
  modes: ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'],
  modeUrlFn: (mode) => `modules.php?name=Team&op=api&teamid=1&display=${mode}`,
  modeMsgPrefix: 'Team API ',
  allModesTitle: 'all valid display modes return html',
  hxPushUrl: 'modules.php?name=Team&op=api&teamid=1&display=contracts',
  hxPushContains: ['display=contracts', 'teamid=1'],
});

test.describe('Team API', () => {
  test('invalid teamID returns a 4xx client error', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=Team&op=api&teamid=99999&display=ratings',
    );
    expect(response.status()).toBeGreaterThanOrEqual(400);
    expect(response.status()).toBeLessThan(500);
  });
});

// ============================================================
// Saved Depth Chart API (DepthChartEntry&op=api)
// Requires auth — returns 401 without valid session (still JSON)
// ============================================================

test.describe('Saved Depth Chart API', () => {
  test('list action returns JSON with auth', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=list',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    // Should return 200 with saved depth chart list (may be empty array)
    expect(status).toBe(200);
    expect(body).toBeTruthy();
  });

  test('unauthenticated request returns 401', async ({ request }) => {
    // Use a fresh request context: strip auth cookies, set _e2e to skip
    // PageCache; auto-login does not fire (opt-in is absent by default)
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=api&action=list',
      { headers: { Cookie: '_e2e=1' } },
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'Unauth API endpoint must return JSON, not HTML').toContain('json');

    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body).toHaveProperty('error');
  });

  test('unknown action returns 400', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=nonexistent',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(400);
    expect(body).toHaveProperty('error');
  });
});

// ============================================================
// LeagueStarters API (LeagueStarters&op=api)
// Returns HTML position tables fragment for a given display mode
// ============================================================

describeTabApi({
  label: 'LeagueStarters API',
  ratingsUrl: 'modules.php?name=LeagueStarters&op=api&display=ratings',
  modes: ['ratings', 'total_s', 'avg_s', 'per36mins'],
  modeUrlFn: (mode) => `modules.php?name=LeagueStarters&op=api&display=${mode}`,
  modeMsgPrefix: 'LeagueStarters API ',
  allModesTitle: 'all valid display modes return html',
  hxPushUrl: 'modules.php?name=LeagueStarters&op=api&display=total_s',
  hxPushContains: ['display=total_s'],
});

test.describe('LeagueStarters API', () => {
  test('invalid display mode falls back to ratings', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=LeagueStarters&op=api&display=invalid_mode',
    );
    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<table');
  });
});

// ============================================================
// DraftHistory API (DraftHistory&op=api)
// Returns HTML draft table fragment for a given year
// ============================================================

test.describe('DraftHistory API', () => {
  test('returns HTML response', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DraftHistory&op=api',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');
    expect(response.status()).toBe(200);
  });

  test('out-of-range year falls back gracefully', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DraftHistory&op=api&year=9999',
    );

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html.length).toBeGreaterThan(0);
    expect(html, 'out-of-range year should still render a draft table, not an error page').toContain(
      '<table',
    );
  });

  test('response includes HX-Push-Url header', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DraftHistory&op=api&year=2000',
    );

    const pushUrl = response.headers()['hx-push-url'] ?? '';
    expect(pushUrl).toContain('year=');
  });
});

// ============================================================
// FranchiseRecordBook API (FranchiseRecordBook&op=api)
// Returns HTML content fragment (title + record sections)
// ============================================================

test.describe('FranchiseRecordBook API', () => {
  test('league-wide view returns HTML with title', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=FranchiseRecordBook&op=api',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');
    expect(response.status()).toBe(200);

    const html = await response.text();
    expect(html).toContain('ibl-title');
  });

  test('team-specific view returns HTML', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=FranchiseRecordBook&op=api&teamid=1',
    );

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html.length).toBeGreaterThan(0);
    expect(html, 'team-specific view should render the record-book page, not an error').toContain(
      'ibl-title',
    );
  });

  test('invalid teamid falls back to league-wide', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=FranchiseRecordBook&op=api&teamid=9999',
    );

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('League-Wide');
  });

  test('response includes HX-Push-Url header for team view', async ({
    request,
  }) => {
    const response = await request.get(
      'modules.php?name=FranchiseRecordBook&op=api&teamid=1',
    );

    const pushUrl = response.headers()['hx-push-url'] ?? '';
    expect(pushUrl).toContain('teamid=1');
  });

  test('response includes HX-Push-Url header for league view', async ({
    request,
  }) => {
    const response = await request.get(
      'modules.php?name=FranchiseRecordBook&op=api',
    );

    const pushUrl = response.headers()['hx-push-url'] ?? '';
    expect(pushUrl).toContain('FranchiseRecordBook');
  });
});

// ============================================================
// Saved Depth Chart API: load and rename
// Validation-failure tests for load, rename, and rename-active actions.
// No successful rename — that would require a cleanup endpoint.
// ============================================================

test.describe('Saved Depth Chart API: load and rename', () => {
  test('action=load with invalid id returns 400 JSON error', async ({
    request,
  }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=load&id=0',
    );
    expect(contentType).toContain('json');
    expect(status).toBe(400);
    expect(body).toHaveProperty('error', 'Invalid depth chart ID');
  });

  test('action=load with nonexistent id returns 404 JSON', async ({
    request,
  }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=load&id=999999',
    );
    expect(contentType).toContain('json');
    expect(status).toBe(404);
    expect(body).toHaveProperty('error', 'Depth chart not found');
  });

  test('action=rename with empty name returns 400 JSON', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=DepthChartEntry&op=api&action=rename',
      { data: { id: 1, name: '' } },
    );
    expect(response.status()).toBe(400);
    const body = await response.json();
    expect(body).toHaveProperty('error', 'Name cannot be empty');
  });

  test('action=rename with invalid id returns 400 JSON', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=DepthChartEntry&op=api&action=rename',
      { data: { id: 0, name: 'x' } },
    );
    expect(response.status()).toBe(400);
    const body = await response.json();
    expect(body).toHaveProperty('error', 'Invalid depth chart ID');
  });

  test('action=rename-active with empty name returns 400 JSON', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=DepthChartEntry&op=api&action=rename-active',
      { data: { name: '   ' } },
    );
    expect(response.status()).toBe(400);
    const body = await response.json();
    expect(body).toHaveProperty('error', 'Name cannot be empty');
  });

  test('unauthenticated load returns 401 JSON', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=api&action=load&id=1',
      { headers: { Cookie: '_e2e=1' } },
    );
    expect(response.status()).toBe(401);
    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType).toContain('json');
    const body = await response.json();
    expect(body).toHaveProperty('error');
  });
});
