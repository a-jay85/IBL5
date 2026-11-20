<?php

declare(strict_types=1);

/**
 * issue-notice-mark.php — mark a claimed issue notice as notified (claimed -> notified).
 *
 * Usage: php issue-notice-mark.php --issue=N --reason=R
 *   R is one of: completed, not_planned, duplicate, other.
 *
 * Prints "notified" when the row moved, nothing otherwise.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-mark.php --issue=N --reason=completed|not_planned|duplicate|other';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-mark: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if ($key !== 'issue' && $key !== 'reason') {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

if (!isset($noticeOpts['issue']) || !ctype_digit($noticeOpts['issue'])) {
    $noticeFail('--issue is required and must be a non-negative integer');
}
if (!isset($noticeOpts['reason'])) {
    $noticeFail('--reason is required');
}

$repo = new BugIssueNoticeRepository($mysqli_db);
try {
    if ($repo->markNotified((int) $noticeOpts['issue'], $noticeOpts['reason'])) {
        echo 'notified', PHP_EOL;
    }
} catch (\InvalidArgumentException $e) {
    $noticeFail($e->getMessage());
}
