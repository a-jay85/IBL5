/**
 * PHP error patterns to check for in page body text.
 * Every smoke test must check visited pages for these patterns.
 */
export const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];
