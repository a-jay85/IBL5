import { test, expect } from '../fixtures/auth';

test.describe('allStarRename.php — admin success + error paths', () => {
  test.describe.configure({ mode: 'serial' });

  let awayRecordId: number;

  test.beforeAll(async ({ request }) => {
    await request.delete('test-state.php?action=reset-allstar-names');
  });

  test('admin GET with missing params returns validation error', async ({
    request,
  }) => {
    const response = await request.get('scripts/allStarRename.php');
    expect(response.status()).toBe(200);
    const body = (await response.json()) as Record<string, unknown>;
    expect(body).toMatchObject({
      success: false,
      error: 'Invalid team ID or name.',
    });
  });

  test('bad input (renameTeamId=0) is rejected', async ({ request }) => {
    const response = await request.post('scripts/allStarRename.php', {
      form: { renameTeamId: '0', renameTeamName: 'Anything' },
    });
    expect(response.status()).toBe(200);
    const body = (await response.json()) as Record<string, unknown>;
    expect(body.success).toBe(false);
  });

  test('admin rename succeeds and DB read-back confirms new name', async ({
    request,
  }) => {
    // Resolve the record id for 'Team Away' via test-state endpoint
    const idsResponse = await request.get(
      'test-state.php?action=get-allstar-ids',
    );
    const idsBody = (await idsResponse.json()) as {
      rows: Array<{ id: number; name: string }>;
    };
    expect(idsBody.rows.length).toBeGreaterThanOrEqual(1);
    const awayRow = idsBody.rows.find((r) => r.name === 'Team Away');
    expect(awayRow).toBeTruthy();
    awayRecordId = awayRow!.id;

    // Submit rename
    const response = await request.post('scripts/allStarRename.php', {
      form: {
        renameTeamId: String(awayRecordId),
        renameTeamName: 'Team LeBron',
      },
    });
    expect(response.status()).toBe(200);
    const body = (await response.json()) as Record<string, unknown>;
    expect(body).toEqual({ success: true });

    // Read-back: verify the DB row now has the new name
    const nameResponse = await request.get(
      `test-state.php?action=get-allstar-name&id=${awayRecordId}`,
    );
    const nameBody = (await nameResponse.json()) as Record<string, unknown>;
    expect(nameBody.name).toBe('Team LeBron');
  });

  test.afterAll(async ({ request }) => {
    await request.delete('test-state.php?action=reset-allstar-names');
  });
});
