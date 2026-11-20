<?php

declare(strict_types=1);

/**
 * issue-notice-skip.php — mark a claimed issue notice as skipped (no reporter and no
 * reachable Discord location, so nothing can be sent). claimed -> skipped.
 *
 * Usage: php issue-notice-skip.php --issue=N
 *
 * Prints "skipped" when the row moved, nothing otherwise.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-skip.php --issue=N';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-skip: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if ($key !== 'issue') {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

if (!isset($noticeOpts['issue']) || !ctype_digit($noticeOpts['issue'])) {
    $noticeFail('--issue is required and must be a non-negative integer');
}

$repo = new BugIssueNoticeRepository($mysqli_db);
if ($repo->markSkipped((int) $noticeOpts['issue'])) {
    echo 'skipped', PHP_EOL;
}
