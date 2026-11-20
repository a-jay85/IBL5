<?php

declare(strict_types=1);

/**
 * issue-notice-claim.php — claim a pending issue notice (pending -> claimed).
 *
 * Usage: php issue-notice-claim.php --issue=N
 *
 * Prints "claimed" when this caller won the claim, nothing when another caller
 * already holds it or the row is not pending.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-claim.php --issue=N';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-claim: {$msg}\n{$noticeUsage}\n");
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
if ($repo->claim((int) $noticeOpts['issue'])) {
    echo 'claimed', PHP_EOL;
}
