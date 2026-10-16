import { readFileSync } from 'fs';
import { resolve } from 'path';
import { describe, it, expect } from 'vitest';

// Guards the e2e job's VR publish steps (ADR-0181, decision 3): every step that
// runs on an update-baselines run must
// carry continue-on-error that is true on that run, or a failure there would
// silently skip the baseline regen. Manual-row capture must stay label-gated,
// because its setup= DB mutations would corrupt the regenerated baselines.
const WORKFLOW = resolve(__dirname, '../../../.github/workflows/e2e-tests.yml');
const REAL = readFileSync(WORKFLOW, 'utf-8');

const LABEL_GUARD = "!contains(github.event.pull_request.labels.*.name, 'update-baselines')";
const LABEL_COE = "${{ contains(github.event.pull_request.labels.*.name, 'update-baselines') }}";

const UNGATED = [
  'Build VR gallery',
  'Copy new-screen renders into gallery deploy tree',
  'Crop changed-screen pairs into gallery deploy tree',
  'Deploy VR gallery to per-SHA GitHub Pages',
  'Deploy VR gallery to per-SHA GitHub Pages (retry 1)',
  'Deploy VR gallery to per-SHA GitHub Pages (retry 2)',
  'Assert VR gallery deploy succeeded',
  'Re-serve the gh-pages tree (dispatch Pages deploy)',
  'Splice new-screen images into PR body',
];

const GATED = [
  'Extract manual-row VR specs from PR body',
  'Capture manual-row screenshots',
  'Capture manual-row before screenshots on the base SHA',
];

const CROP = 'Crop changed-screen pairs into gallery deploy tree';
const SPLICE = 'Splice new-screen images into PR body';
const DEPLOY = 'Deploy VR gallery to per-SHA GitHub Pages';
const REGEN = 'Regenerate visual regression baselines';

type Step = { name: string; index: number; ifLine: string; coe: string | null };

function e2eSteps(yaml: string): Step[] {
  const lines = yaml.split('\n');
  const start = lines.findIndex((l) => /^ {2}e2e:\s*$/.test(l));
  if (start === -1) return [];
  let end = lines.length;
  for (let i = start + 1; i < lines.length; i++) {
    if (/^ {2}[A-Za-z0-9_-]+:\s*$/.test(lines[i])) {
      end = i;
      break;
    }
  }
  const steps: Step[] = [];
  let cur: Step | null = null;
  for (let i = start + 1; i < end; i++) {
    const line = lines[i];
    const m = /^ {6}- name: (.+)$/.exec(line);
    if (m) {
      cur = { name: m[1].trim(), index: steps.length, ifLine: '', coe: null };
      steps.push(cur);
      continue;
    }
    if (cur === null) continue;
    const ifM = /^ {8}if: (.*)$/.exec(line);
    if (ifM) cur.ifLine = ifM[1];
    const coeM = /^ {8}continue-on-error: (.*)$/.exec(line);
    if (coeM) cur.coe = coeM[1].trim();
  }
  return steps;
}

function publishStepViolations(yaml: string): string[] {
  const steps = e2eSteps(yaml);
  const byName = new Map(steps.map((s) => [s.name, s]));
  const out: string[] = [];

  for (const name of UNGATED) {
    const s = byName.get(name);
    if (!s) {
      out.push(`missing: ${name}`);
      continue;
    }
    if (s.ifLine.includes(LABEL_GUARD)) out.push(`label-gated: ${name}`);
    if (s.coe !== 'true' && s.coe !== LABEL_COE) out.push(`no-coe: ${name}`);
  }

  for (const name of GATED) {
    const s = byName.get(name);
    if (!s || !s.ifLine.includes(LABEL_GUARD)) out.push(`ungated-manual: ${name}`);
  }

  const at = (name: string) => byName.get(name)?.index ?? -1;
  const regen = at(REGEN);
  for (const name of [SPLICE, CROP]) {
    if (at(name) !== -1 && (regen === -1 || at(name) > regen)) out.push(`order: ${name}`);
  }
  if (at(CROP) !== -1 && (at(DEPLOY) === -1 || at(CROP) > at(DEPLOY))) out.push(`order: ${CROP}`);

  return out;
}

// Cut one whole step block (from its `- name:` line up to the next step or
// comment line at step indent) out of the text, returning [text, block].
function cutStep(yaml: string, name: string): [string, string] {
  const startTok = `      - name: ${name}\n`;
  const from = yaml.indexOf(startTok);
  if (from === -1) throw new Error(`step not found: ${name}`);
  const rest = yaml.slice(from + startTok.length);
  const next = rest.search(/\n(?= {6}(- name: |# ))/);
  const to = next === -1 ? yaml.length : from + startTok.length + next + 1;
  return [yaml.slice(0, from) + yaml.slice(to), yaml.slice(from, to)];
}

// Apply `fn` to one step's block only, so a mutant never lands on a sibling
// step that shares the same `if:` text.
function mutateStep(yaml: string, name: string, fn: (block: string) => string): string {
  const [, block] = cutStep(yaml, name);
  const next = fn(block);
  if (next === block) throw new Error(`mutation was a no-op on: ${name}`);
  const at = yaml.indexOf(block);
  return yaml.slice(0, at) + next + yaml.slice(at + block.length);
}

describe('publishStepViolations', () => {
  it('12a: the real workflow has no violations', () => {
    expect(publishStepViolations(REAL)).toEqual([]);
  });

  it("12b: re-adding !contains(…'update-baselines') to the Splice step is reported", () => {
    const mutant = mutateStep(REAL, SPLICE, (b) => b.replace(/( {8}if: [^\n]*)/, `$1 && ${LABEL_GUARD}`));
    expect(publishStepViolations(mutant)).toEqual([`label-gated: ${SPLICE}`]);
  });

  it('12c: deleting continue-on-error from Build VR gallery is reported', () => {
    const mutant = mutateStep(REAL, 'Build VR gallery', (b) => b.replace(/ {8}continue-on-error: [^\n]*\n/, ''));
    expect(publishStepViolations(mutant)).toEqual(['no-coe: Build VR gallery']);
  });

  it('12d: continue-on-error: false on the assert step is reported', () => {
    const name = 'Assert VR gallery deploy succeeded';
    const mutant = mutateStep(REAL, name, (b) => b.replace(/( {8}continue-on-error: )[^\n]*/, '$1false'));
    expect(publishStepViolations(mutant)).toEqual([`no-coe: ${name}`]);
  });

  it('12e: stripping the guard from manual-row capture is reported', () => {
    const name = 'Capture manual-row screenshots';
    const mutant = mutateStep(REAL, name, (b) => b.replace(` && ${LABEL_GUARD}`, ''));
    expect(publishStepViolations(mutant)).toEqual([`ungated-manual: ${name}`]);
  });

  it('12f: removing the Crop step is reported', () => {
    const [without] = cutStep(REAL, CROP);
    expect(publishStepViolations(without)).toEqual([`missing: ${CROP}`]);
  });

  it('12g: moving the Splice step after the regen step is reported', () => {
    const [without, block] = cutStep(REAL, SPLICE);
    const [, regenBlock] = cutStep(without, REGEN);
    const mutant = without.replace(regenBlock, regenBlock + block);
    expect(publishStepViolations(mutant)).toEqual([`order: ${SPLICE}`]);
  });
});
