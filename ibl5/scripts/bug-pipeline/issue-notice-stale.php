<?php

declare(strict_types=1);

/**
 * issue-notice-stale.php — list issue notices claimed longer ago than N minutes
 * (a tick that crashed after claiming).
 *
 * Usage: php issue-notice-stale.php [--minutes=N]
 *   Default 60.
 *
 * Prints a JSON array of issue numbers; `[]` when none are stale.
 * Exit 2 with a usage line on stderr for unknown flags, space-form flags, or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-stale.php [--minutes=N]';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-stale: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if ($key !== 'minutes') {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

$minutes = 60;
if (isset($noticeOpts['minutes'])) {
    if (!ctype_digit($noticeOpts['minutes'])) {
        $noticeFail('--minutes must be a non-negative integer');
    }
    $minutes = (int) $noticeOpts['minutes'];
}

$repo = new BugIssueNoticeRepository($mysqli_db);
echo json_encode($repo->listStaleClaims($minutes)), PHP_EOL;
