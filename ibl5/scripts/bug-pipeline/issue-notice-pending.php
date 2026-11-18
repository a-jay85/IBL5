<?php

declare(strict_types=1);

/**
 * issue-notice-pending.php — list issue notices waiting to be sent.
 *
 * Usage: php issue-notice-pending.php [--limit=N] [--after=ISSUE]
 *   Default 25; --after=ISSUE returns only rows with a higher issue number (paging); the repository clamps N to 1..50.
 *
 * Prints a JSON array of {issue_number, reporter_discord_id, thread_id, original_message_id}.
 * Discord ids are JSON strings (a snowflake can exceed 2^53); `[]` when nothing is pending.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-pending.php [--limit=N] [--after=ISSUE]';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-pending: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if ($key !== 'limit' && $key !== 'after') {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

$limit = 25;
if (isset($noticeOpts['limit'])) {
    if (!ctype_digit($noticeOpts['limit'])) {
        $noticeFail('--limit must be a non-negative integer');
    }
    $limit = (int) $noticeOpts['limit'];
}

$after = 0;
if (isset($noticeOpts['after'])) {
    if (!ctype_digit($noticeOpts['after'])) {
        $noticeFail('--after must be a non-negative integer');
    }
    $after = (int) $noticeOpts['after'];
}

$repo = new BugIssueNoticeRepository($mysqli_db);
echo json_encode($repo->listPending($limit, $after)), PHP_EOL;
