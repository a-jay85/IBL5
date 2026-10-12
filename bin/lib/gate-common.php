<?php

declare(strict_types=1);

/**
 * bin/lib/gate-common.php — helpers shared by the bin/adr-check and
 * bin/refactor-flag CI gates. Loaded with require_once from each gate.
 * Function declarations only; no top-level side effects. Per-gate policy
 * (BYPASS_REGEX, BYPASS_MIN_LENGTH, trigger detection, diff ranges) stays in
 * each gate and is passed in as arguments.
 */

/**
 * Run a shell command with stderr discarded.
 *
 * @return array{0: list<string>, 1: int} [output lines, exit code]
 */
function runCmd(string $cmd): array
{
    $output = [];
    $exit = 0;
    exec($cmd . ' 2>/dev/null', $output, $exit);
    return [$output, $exit];
}

/**
 * Fetch the PR body. --bypass-from-stdin reads STDIN in any mode; otherwise
 * only 'pr' mode calls `gh pr view [<PR_NUMBER>] --json body --jq .body`.
 * Returns null on gh failure or empty output.
 */
function fetchPrBody(string $mode, bool $fromStdin): ?string
{
    if ($fromStdin) {
        $body = stream_get_contents(STDIN);
        return $body === false ? null : $body;
    }
    if ($mode !== 'pr') {
        return null;
    }
    // GitHub Actions checks out a detached merge commit for pull_request events, so
    // `gh pr view` without arguments cannot infer the PR. The workflow passes the
    // number via PR_NUMBER env. Fall back to inference when PR_NUMBER is absent
    // (local `--pr` usage on a branch checkout).
    $prNumber = getenv('PR_NUMBER');
    $cmd = 'gh pr view';
    if (is_string($prNumber) && $prNumber !== '') {
        $cmd .= ' ' . escapeshellarg($prNumber);
    }
    $cmd .= ' --json body --jq .body';
    [$lines, $exit] = runCmd($cmd);
    if ($exit !== 0 || $lines === []) {
        return null;
    }
    return implode("\n", $lines);
}

/**
 * `git diff <diffArgs> --name-only --diff-filter=<filter>`, minus empty lines
 * and any path present as a KEY of $excluded.
 *
 * @param string $diffArgs already shell-escaped range/args fragment
 * @param array<string, mixed> $excluded path => anything; [] disables exclusion
 * @return list<string>
 */
function gateDiffNames(string $diffArgs, string $filter, array $excluded = []): array
{
    [$lines] = runCmd(sprintf('git diff %s --name-only --diff-filter=%s', $diffArgs, escapeshellarg($filter)));
    return array_values(array_filter(
        $lines,
        static fn (string $line): bool => $line !== '' && !isset($excluded[$line])
    ));
}

/**
 * Bypass reason from the FIRST $regex match in $body (capture group 1),
 * trimmed. null when the body is null/''/'null', nothing matches, or the
 * trimmed reason is shorter than $minLength BYTES (strlen).
 */
function gateExtractBypassReason(?string $body, string $regex, int $minLength): ?string
{
    if ($body === null || $body === '' || $body === 'null') {
        return null;
    }
    if (preg_match($regex, $body, $m) !== 1) {
        return null;
    }
    $reason = trim($m[1]);
    if (strlen($reason) < $minLength) {
        return null;
    }
    return $reason;
}
