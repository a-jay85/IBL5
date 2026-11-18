<?php

declare(strict_types=1);

/**
 * issue-notice-seed.php — mark a pending issue notice as seeded (already closed before
 * the notifier existed, so no notice is owed).
 *
 * Usage: php issue-notice-seed.php --issue=N
 *
 * Prints "seeded" when the row moved pending -> seeded, nothing otherwise.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-seed.php --issue=N';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-seed: {$msg}\n{$noticeUsage}\n");
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
if ($repo->seedNotified((int) $noticeOpts['issue'])) {
    echo 'seeded', PHP_EOL;
}
