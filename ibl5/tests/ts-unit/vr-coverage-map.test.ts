import { describe, it, expect } from 'vitest';
import type { VrRow } from '../e2e/vr-manifest';
import {
  classifyChangedFiles,
  deriveModuleGlob,
} from '../e2e/vr-coverage-map';

// Small inline fixture manifest — deliberately NOT the live 49-row manifest, so
// these contract tests don't drift when rows are added/removed in vr-manifest.ts.
const FIXTURE: VrRow[] = [
  { name: 'standings', auth: 'public', url: 'modules.php?name=Standings', anchor: '.t' },
  { name: 'index', auth: 'public', url: 'index.php', anchor: 'article',
    sourceGlobs: ['ibl5/index.php', 'ibl5/themes/**'] },
  // Cross-cutting row whose derived glob (ibl5/modules/News/**) would also match,
  // but the explicit override must take precedence.
  { name: 'news', auth: 'public', url: 'modules.php?name=News', anchor: 'article',
    sourceGlobs: ['ibl5/modules/News/templates/**'] },
];

const GLOBAL_GLOBS = ['ibl5/design/**', 'ibl5/classes/**', '**/*.css'];

describe('classifyChangedFiles', () => {
  it('row 1: a changed module file marks its row covered', () => {
    const r = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/modules/Standings/Standings.php'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(r.coveredRows).toContain('standings');
    expect(r.globalChange).toBe(false);
    expect(r.uncoveredChangedPaths).toEqual([]);
  });

  it('row 2: a changed module file with no manifest row lands in uncoveredChangedPaths', () => {
    const r = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/modules/Ghost/Ghost.php'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(r.coveredRows).toEqual([]);
    expect(r.uncoveredChangedPaths).toContain('ibl5/modules/Ghost/Ghost.php');
    expect(r.globalChange).toBe(false);
  });

  it('row 3: a global change sets globalChange and fans out to all rows', () => {
    const r = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/design/foo.css'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(r.globalChange).toBe(true);
    expect(r.coveredRows).toEqual(['standings', 'index', 'news']);
    expect(r.uncoveredChangedPaths).toEqual([]);
  });

  it('row 3 (classes): a changed ibl5/classes file is global too', () => {
    const r = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/classes/Foo/Bar.php'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(r.globalChange).toBe(true);
    expect(r.coveredRows).toHaveLength(FIXTURE.length);
  });

  it('row 4: deriveModuleGlob maps a module URL and returns null for index.php', () => {
    expect(deriveModuleGlob('modules.php?name=Standings')).toBe('ibl5/modules/Standings/**');
    expect(deriveModuleGlob('modules.php?name=Team&op=team&teamid=1')).toBe('ibl5/modules/Team/**');
    expect(deriveModuleGlob('index.php')).toBeNull();
  });

  it('row 5: an explicit sourceGlobs override wins over the derived glob', () => {
    // A change inside ibl5/modules/News/ but OUTSIDE the override (templates/) must
    // NOT mark the news row covered — proving the derived glob is not consulted.
    const outside = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/modules/News/News.php'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(outside.coveredRows).not.toContain('news');
    expect(outside.uncoveredChangedPaths).toContain('ibl5/modules/News/News.php');

    // A change matching the override DOES mark it covered.
    const inside = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/modules/News/templates/list.php'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(inside.coveredRows).toContain('news');
  });

  it('ignores non-website changes (docs / rules) — not covered, not uncovered', () => {
    const r = classifyChangedFiles({
      manifest: FIXTURE,
      changedPaths: ['ibl5/docs/foo.md', '.claude/rules/bar.md', 'README.md'],
      globalGlobs: GLOBAL_GLOBS,
    });
    expect(r.coveredRows).toEqual([]);
    expect(r.uncoveredChangedPaths).toEqual([]);
    expect(r.globalChange).toBe(false);
  });
});
