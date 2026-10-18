import { test, expect } from '../fixtures/auth';
import type { APIRequestContext } from '@playwright/test';
import { triggerUpdater } from '../helpers/updater';

/**
 * Engine shadow sim runs OUT-OF-BAND (ADR-0037): hitting the admin
 * "Update All The Things" endpoint fires a detached background process
 * (ShadowProcessLauncher → runEngineShadow.php) that streams the engine and
 * writes the droppable shadow box-score tables. This spec proves the web trigger
 * spawns that process and that the run reaches a terminal outcome, read from the
 * shadow log via `test-state.php?action=shadow-log`.
 *
 * Outcomes, by the run's last log line:
 *   - `N games shadow-simmed`          rows landed; the row assertions run.
 *   - `nothing to do`                  SKIP, with the logged reason.
 *   - `another run holds the lock`     SKIP, with the logged reason.
 *   - `shadow run failed` / PHP fatal  FAIL.
 *   - no terminal line within 15 s     FAIL (the spawn never ran), flag on only.
 * The spec skips at once when the jsbsim binary is absent or ENGINE_SHADOW_ENABLED
 * is off (only docker-compose.ci.yml sets it).
 *
 * Why rows do not land in CI: the updater runs DELETE FROM ibl_schedule
 * (classes/Updater/ScheduleUpdater.php:522) and re-inserts from the tracked
 * IBL5.sch, which holds only played games. EngineBundleRepository.php:83-85 then
 * finds no unplayed game, so runEngineShadow.php:115-118 exits 0 with
 * `nothing to do`. Rows land only once a staged JSB archive with unplayed games
 * feeds the updater (out of scope here).
 *
 * Serial because updateAllTheThings mutates shared global tables (mirrors
 * updater-awards.spec.ts, which already triggers the same pipeline in CI). The
 * shadow tables have a single writer (this spec), so there is no cross-worker race
 * on them.
 */
test.describe.configure({ mode: 'serial', timeout: 120_000 });

interface ShadowCounts {
  players: number;
  teams: number;
}

async function countShadowRows(request: APIRequestContext): Promise<ShadowCounts> {
  const resp = await request.get('test-state.php?action=count-shadow-rows');
  expect(resp.ok()).toBeTruthy();
  return (await resp.json()) as ShadowCounts;
}

interface ShadowLog {
  enabled: boolean;
  size: number;
  tail: string;
}

type ShadowOutcome = 'pending' | 'failed' | 'simmed' | 'nothing-to-do' | 'locked';

// Terminal lines written by scripts/runEngineShadow.php (:78 lock, :108 success on
// STDOUT with no prefix, :117 nothing to do, :122 failed). A PHP fatal before the
// script's try block writes none of these, so it is classed as failed too. Order
// matters: the first match wins, and failed is checked first.
const OUTCOME_PATTERNS: ReadonlyArray<readonly [Exclude<ShadowOutcome, 'pending'>, RegExp]> = [
  ['failed', /runEngineShadow: shadow run failed:|PHP (?:Fatal|Parse) error|Uncaught /],
  ['simmed', /^\d+ games shadow-simmed \(seed \d+\) for season \d+$/m],
  ['nothing-to-do', /runEngineShadow: nothing to do:/],
  ['locked', /runEngineShadow: another run holds the lock/],
];

function classifyShadowLog(tail: string): ShadowOutcome {
  return OUTCOME_PATTERNS.find(([, pattern]) => pattern.test(tail))?.[0] ?? 'pending';
}

async function readShadowLog(request: APIRequestContext, offset: number): Promise<ShadowLog> {
  const resp = await request.get(`test-state.php?action=shadow-log&offset=${offset}`);
  expect(resp.ok()).toBeTruthy();
  return (await resp.json()) as ShadowLog;
}

test.describe('Engine shadow: out-of-band spawn on admin update', () => {
  test('classifies each terminal shadow-log line', () => {
    expect(classifyShadowLog('')).toBe('pending');
    expect(classifyShadowLog('3 games shadow-simmed (seed 42) for season 2026\n')).toBe('simmed');
    expect(
      classifyShadowLog(
        'runEngineShadow: nothing to do: No unplayed games to simulate for season 2026 in the given window.\n',
      ),
    ).toBe('nothing-to-do');
    expect(classifyShadowLog('runEngineShadow: another run holds the lock; skipping.\n')).toBe('locked');
    expect(classifyShadowLog('runEngineShadow: shadow run failed: boom\n')).toBe('failed');
    expect(classifyShadowLog('PHP Fatal error:  Uncaught Error: boom in /x.php:1\n')).toBe('failed');
    expect(
      classifyShadowLog('runEngineShadow: nothing to do: x\nrunEngineShadow: shadow run failed: y\n'),
    ).toBe('failed');
  });

  test('shadow-log serialises the spawn gate and rejects a bad offset', async ({ request }) => {
    const resp = await request.get('test-state.php?action=shadow-log&offset=0');
    expect(resp.status()).toBe(200);
    const raw = await resp.text();
    const log = JSON.parse(raw) as ShadowLog;
    expect(typeof log.enabled).toBe('boolean');
    expect(typeof log.size).toBe('number');
    expect(log.tail.length).toBeLessThanOrEqual(8192);
    // The gate must reach the wire as a JSON boolean: an unset or empty flag is "enabled":false.
    expect(raw).toContain(log.enabled ? '"enabled":true' : '"enabled":false');

    const bad = await request.get('test-state.php?action=shadow-log&offset=abc');
    expect(bad.status()).toBe(400);
  });

  test('hitting updateAllTheThings spawns the detached run and it reaches a terminal outcome', async ({
    request,
  }) => {
    // Skip fast when the engine binary is not installed in this image.
    const readyResp = await request.get('test-state.php?action=engine-binary-ready');
    expect(readyResp.ok()).toBeTruthy();
    const { ready } = (await readyResp.json()) as { ready: boolean };
    // e2e-hygiene-allow: integration-availability skip — the prebaked :latest image may predate the NDJSON engine binary; green-or-skip until master rebuilds (cf. reference_prebaked_php_image_rebuild_lag)
    test.skip(!ready, 'jsbsim binary not installed in this image (prebaked :latest may predate it)');

    const start = await readShadowLog(request, 0);
    // e2e-hygiene-allow: stack-config skip, ENGINE_SHADOW_ENABLED is set only by docker-compose.ci.yml so local worktree stacks never spawn the shadow run
    test.skip(
      !start.enabled,
      'ENGINE_SHADOW_ENABLED is off on this stack (only docker-compose.ci.yml sets it), so no shadow run is spawned',
    );

    const before = await countShadowRows(request);

    // Trigger the admin update via a CSRF-validated POST (token fetched from the
    // LCP form). We POST rather than click the phase-gated button so this spec
    // never mutates the shared season-phase row. The POST resolves after the
    // streamed pipeline completes; the detached shadow spawn fires at its end.
    await triggerUpdater(request);

    // Read only the bytes appended since `start.size` until a terminal line shows up.
    let latest: ShadowLog = { ...start, tail: '' };
    await expect
      .poll(
        async () => {
          latest = await readShadowLog(request, start.size);
          return classifyShadowLog(latest.tail);
        },
        {
          timeout: 15_000,
          intervals: [250, 500, 1_000],
          message:
            'no terminal runEngineShadow line in the shadow log within 15 s: the detached spawn never ran',
        },
      )
      .not.toBe('pending');

    const outcome = classifyShadowLog(latest.tail);
    const lastLine = latest.tail.trim().split('\n').pop() ?? '';
    expect(outcome, `shadow run failed; log tail:\n${latest.tail}`).not.toBe('failed');

    // e2e-hygiene-allow: data-availability skip, tracked IBL5.sch has 0 unplayed games so the run exits 0 with nothing to do (see header)
    test.skip(outcome === 'nothing-to-do', `runEngineShadow reported ${lastLine}`);
    // e2e-hygiene-allow: benign overlap skip, an earlier updater spec's shadow run still held the single-run lock
    test.skip(outcome === 'locked', `shadow run skipped: ${lastLine}`);

    // The success line prints after the loader writes rows, so one read is enough.
    expect(outcome).toBe('simmed');
    const after = await countShadowRows(request);
    expect(after.players).toBeGreaterThan(before.players);
    expect(after.teams).toBeGreaterThan(before.teams);
  });
});
