import type { Page } from '@playwright/test';
import { test } from '../fixtures/base';
import { readCellRecords, vrRecordingEnabled } from './rendered-views';

export const RENDERED_DIR = 'vr-rendered';
// Rows whose page renders no View/Renderer/Formatter class. Each entry names the module
// file that renders without one. Fill from the first CI run only (Phase 6), never by guess.
export const NO_VIEW_ROWS: readonly string[] = [];

export function recordingOn(): boolean {
  return vrRecordingEnabled(process.env.IBL_VR_RECORD_VIEWS);
}

export async function setVrCellCookie(page: Page, rowName: string, title: string): Promise<void> {
  const baseURL = test.info().project.use.baseURL ?? process.env.BASE_URL;
  if (!baseURL) throw new Error('[vr-recorder] no baseURL for the _vr_cell cookie');
  await page.context().addCookies([{ name: '_vr_cell', value: `${rowName}~${title}`, url: baseURL }]);
}

export function assertCellRecorded(rowName: string, title: string): void {
  const recs = readCellRecords(RENDERED_DIR).filter((r) => r.cell === title);
  if (recs.length === 0) {
    throw new Error(`[vr-recorder] no record for cell ${title}; is vr-recorder.ini mounted in the php service?`);
  }
  if (recs.every((r) => r.files.length === 0) && !NO_VIEW_ROWS.includes(rowName)) {
    const uris = recs.map((r) => r.uri).join(', ');
    throw new Error(`[vr-recorder] cell ${title} loaded no View files (uris: ${uris})`);
  }
}
