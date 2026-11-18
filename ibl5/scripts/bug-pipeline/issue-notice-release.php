<?php

declare(strict_types=1);

/**
 * issue-notice-release.php — release a claimed issue notice after a failed send.
 * The row returns to pending, or parks as failed on the fifth release.
 *
 * Usage: php issue-notice-release.php --issue=N
 *
 * Prints "pending" or "failed". Exit 1 (message on stderr) when the row is not in the
 * claimed state. Exit 2 with a usage line on stderr for unknown flags, space-form flags,
 * or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-release.php --issue=N';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-release: {$msg}\n{$noticeUsage}\n");
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
try {
    echo $repo->release((int) $noticeOpts['issue']), PHP_EOL;
} catch (\RuntimeException $e) {
    fwrite(STDERR, "issue-notice-release: {$e->getMessage()}\n");
    exit(1);
}
