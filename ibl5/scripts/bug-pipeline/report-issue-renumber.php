<?php

declare(strict_types=1);

/**
 * report-issue-renumber.php — move bug reports from a legacy issue number to the new
 * GitHub issue number (ibl_bug_reports.issue_number).
 *
 * Usage: php report-issue-renumber.php --legacy=N --new=N
 *
 * Prints the affected row count (0 when no report carries the legacy number).
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugReportRepository;

$noticeUsage = 'usage: php report-issue-renumber.php --legacy=N --new=N';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "report-issue-renumber: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if ($key !== 'legacy' && $key !== 'new') {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

if (!isset($noticeOpts['legacy']) || !ctype_digit($noticeOpts['legacy'])) {
    $noticeFail('--legacy is required and must be a non-negative integer');
}
if (!isset($noticeOpts['new']) || !ctype_digit($noticeOpts['new'])) {
    $noticeFail('--new is required and must be a non-negative integer');
}

$repo = new BugReportRepository($mysqli_db);
echo $repo->renumberIssue((int) $noticeOpts['legacy'], (int) $noticeOpts['new']), PHP_EOL;
