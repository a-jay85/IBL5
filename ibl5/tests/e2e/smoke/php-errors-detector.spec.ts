import { test, expect } from '../fixtures/public';
import { detectPhpError } from '../helpers/php-errors';

/**
 * Self-tests for the PHP-error detector. The detector greps page body text,
 * so it must trip on real PHP error output while ignoring legitimate UI copy
 * that happens to contain the word "Warning:" (e.g. the rookie-option alert).
 * These are pure-function checks — no page navigation required.
 */
test.describe('PHP error detector — self-tests', () => {
  test('trips on a real PHP warning frame', () => {
    const body = 'Warning: Undefined array key "x" in /var/www/file.php on line 42';
    expect(detectPhpError(body)).toBe('Warning:');
  });

  test('trips on a real PHP warning even after html_errors tag stripping', () => {
    // textContent of `<b>Warning</b>: ... in <b>file</b> on line <b>1</b>`
    const body = 'Warning:  Undefined array key "x" in Command line code on line 1';
    expect(detectPhpError(body)).toBe('Warning:');
  });

  test('trips on Fatal error / Parse error / Uncaught / Stack trace', () => {
    expect(detectPhpError('Fatal error: blah')).toBe('Fatal error');
    expect(detectPhpError('Parse error: syntax')).toBe('Parse error');
    expect(detectPhpError('Uncaught TypeError')).toBe('Uncaught');
    expect(detectPhpError('Stack trace: #0 ...')).toBe('Stack trace:');
  });

  test('does NOT trip on the rookie-option warning alert UI copy', () => {
    const body =
      'Warning: By exercising this option, you cannot use an in-season ' +
      'contract extension on this player next season. They will become a ' +
      'free agent after the option year.';
    expect(detectPhpError(body)).toBeNull();
  });

  test('does NOT trip on a clean page', () => {
    expect(detectPhpError('Roster — Standings — Schedule')).toBeNull();
  });
});
