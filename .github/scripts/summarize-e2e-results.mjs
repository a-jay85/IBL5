/**
 * Parses Playwright JSON reports and outputs a markdown summary with embedded
 * screenshot paths (for CML to upload and replace with hosted URLs).
 *
 * Usage:
 *   node summarize-e2e-results.mjs \
 *     --visual=path/to/visual-results.json \
 *     --visual-screenshots=path/to/visual-test-results/ \
 *     --functional=path/to/functional-results.json \
 *     --functional-screenshots=path/to/functional-test-results/ \
 *     --artifact-url=https://github.com/...
 */

import { readFileSync, existsSync, readdirSync, statSync } from 'fs';
import { join } from 'path';

// --- Argument parsing ---

function parseArgs(argv) {
  const args = {};
  for (const arg of argv.slice(2)) {
    const match = arg.match(/^--(\S+?)=(.+)$/);
    if (match) {
      args[match[1]] = match[2];
    }
  }
  return args;
}

const args = parseArgs(process.argv);

// --- Error categorization ---

function categorizeError(message) {
  if (!message) return 'Error';
  const lower = message.toLowerCase();

  if (lower.includes('timeout') || lower.includes('exceeded')) return 'Timeout';
  if (lower.includes('tohavescreenshot') || lower.includes('pixel') || lower.includes('diff')) return 'Visual diff';
  if (lower.includes('locator.') || lower.includes('no element') || lower.includes('resolved to 0')) return 'Element not found';
  if (lower.includes('err_') || lower.includes('net::') || lower.includes('failed to load')) return 'Network error';
  if (lower.includes('expected') || lower.includes('tobe') || lower.includes('tocontain')) return 'Assertion failure';

  return 'Error';
}

// --- JSON parsing ---

function loadJson(filePath) {
  if (!filePath || !existsSync(filePath)) return null;
  try {
    return JSON.parse(readFileSync(filePath, 'utf-8'));
  } catch {
    return null;
  }
}

// --- Recursively find screenshot files in a directory ---

function findScreenshots(dir) {
  const results = [];
  if (!dir || !existsSync(dir)) return results;

  function walk(currentDir) {
    let entries;
    try {
      entries = readdirSync(currentDir);
    } catch {
      return;
    }
    for (const entry of entries) {
      const fullPath = join(currentDir, entry);
      try {
        const stat = statSync(fullPath);
        if (stat.isDirectory()) {
          walk(fullPath);
        } else if (/\.(png|jpg|jpeg|webp)$/i.test(entry)) {
          results.push(fullPath);
        }
      } catch {
        // Skip inaccessible entries
      }
    }
  }

  walk(dir);
  return results;
}

// --- Walk Playwright JSON suites recursively ---

function walkSuites(suites, titlePath = []) {
  const results = { failed: [], flaky: [], passed: 0, total: 0 };

  for (const suite of suites ?? []) {
    const currentPath = suite.title ? [...titlePath, suite.title] : titlePath;

    for (const spec of suite.specs ?? []) {
      const testTitle = [...currentPath, spec.title].filter(Boolean).join(' > ');

      for (const test of spec.tests ?? []) {
        results.total++;

        if (test.status === 'expected' || test.status === 'skipped') {
          if (test.status === 'expected') results.passed++;
          continue;
        }

        if (test.status === 'flaky') {
          results.passed++;
          const retries = (test.results?.length ?? 1) - 1;
          results.flaky.push({ title: testTitle, retries });
          continue;
        }

        // Failed or unexpected
        const lastResult = test.results?.[test.results.length - 1];
        const errorMessage = lastResult?.errors?.[0]?.message ?? '';
        const category = categorizeError(errorMessage);

        // Collect screenshot attachments
        const screenshots = [];
        for (const result of test.results ?? []) {
          for (const attachment of result.attachments ?? []) {
            if (attachment.contentType?.startsWith('image/') && attachment.path) {
              screenshots.push(attachment.path);
            }
          }
        }

        results.failed.push({
          title: testTitle,
          category,
          errorMessage: truncateError(errorMessage),
          screenshots,
        });
      }
    }

    // Recurse into nested suites
    if (suite.suites) {
      const nested = walkSuites(suite.suites, currentPath);
      results.failed.push(...nested.failed);
      results.flaky.push(...nested.flaky);
      results.passed += nested.passed;
      results.total += nested.total;
    }
  }

  return results;
}

function truncateError(msg) {
  if (!msg) return '';
  // Strip ANSI codes
  const clean = msg.replace(/\x1b\[[0-9;]*m/g, '');
  const firstLine = clean.split('\n')[0].trim();
  return firstLine.length > 200 ? firstLine.slice(0, 200) + '...' : firstLine;
}

// --- Match screenshots from directory to failed tests ---

function matchScreenshotsFromDir(failed, screenshotsDir) {
  if (!screenshotsDir) return;
  const allScreenshots = findScreenshots(screenshotsDir);

  for (const test of failed) {
    if (test.screenshots.length > 0) continue;

    // Try to match by test title — Playwright stores screenshots in directories
    // named after the test (slugified)
    const slug = test.title
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-|-$/g, '');

    const matches = allScreenshots.filter(s => {
      const dir = s.toLowerCase();
      // Check if any part of the path contains a slug-like match
      return dir.includes(slug) || slug.split('-').every(word => dir.includes(word));
    });

    if (matches.length > 0) {
      test.screenshots.push(...matches);
    }
  }
}

// --- Generate markdown ---

function generateSectionMarkdown(label, report, screenshotsDir) {
  if (!report) {
    return `### ${label} — Report not found\n\nNo JSON report was available.\n`;
  }

  const results = walkSuites(report.suites);
  matchScreenshotsFromDir(results.failed, screenshotsDir);

  const lines = [];
  const failCount = results.failed.length;
  const flakyCount = results.flaky.length;

  if (failCount === 0 && flakyCount === 0) {
    lines.push(`### ${label} — All ${results.total} tests passed`);
  } else {
    const parts = [];
    if (failCount > 0) parts.push(`${failCount} failed`);
    if (flakyCount > 0) parts.push(`${flakyCount} flaky`);
    lines.push(`### ${label} — ${parts.join(', ')} (${results.total} total)`);
  }

  if (failCount > 0) {
    lines.push('');
    lines.push('#### Failed Tests');

    for (const test of results.failed) {
      lines.push('');
      lines.push(`**${test.title}** — ${test.category}`);
      lines.push('');
      lines.push(`\`${test.errorMessage}\``);

      for (const screenshot of test.screenshots) {
        lines.push('');
        lines.push(`![${test.title}](${screenshot})`);
      }

      lines.push('');
      lines.push('---');
    }
  }

  if (flakyCount > 0) {
    lines.push('');
    lines.push('<details><summary>Flaky tests (passed after retry)</summary>');
    lines.push('');
    lines.push('| Test | Retries |');
    lines.push('|------|---------|');
    for (const test of results.flaky) {
      lines.push(`| ${test.title} | ${test.retries} |`);
    }
    lines.push('');
    lines.push('</details>');
  }

  return lines.join('\n');
}

// --- Main ---

const visualReport = loadJson(args.visual);
const functionalReport = loadJson(args.functional);

const lines = [
  '<!-- e2e-report -->',
  '## E2E Test Results',
  '',
  generateSectionMarkdown('Visual Regression', visualReport, args['visual-screenshots']),
  '',
  '---',
  '',
  generateSectionMarkdown('Functional E2E', functionalReport, args['functional-screenshots']),
];

if (args['artifact-url']) {
  lines.push('');
  lines.push(`[View full HTML report artifact](${args['artifact-url']})`);
}

lines.push('');

process.stdout.write(lines.join('\n'));
