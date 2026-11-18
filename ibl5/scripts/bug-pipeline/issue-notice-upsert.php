<?php

declare(strict_types=1);

/**
 * issue-notice-upsert.php — record the Discord origin of a tracked GitHub bug issue.
 *
 * Usage: php issue-notice-upsert.php --issue=N [--legacy=N] [--reporter=ID] [--channel=ID] [--thread=ID] [--message=ID]
 *   Ids are Discord snowflakes (17-19 digits) carried as STRINGS, never (int)-cast.
 *   Never touches the notice state, so a re-run cannot re-send a notice.
 *
 * Prints nothing on success. Exit 2 with a usage line on stderr for unknown flags,
 * space-form flags (`--issue 5`), or invalid values.
 */

require __DIR__ . '/_bootstrap.php';
/**
 * @var \mysqli $mysqli_db
 * @var list<string> $argv
 */

use BugPipeline\BugIssueNoticeRepository;

$noticeUsage = 'usage: php issue-notice-upsert.php --issue=N [--legacy=N] [--reporter=ID] [--channel=ID] [--thread=ID] [--message=ID]';
$noticeFail = static function (string $msg) use ($noticeUsage): never {
    fwrite(STDERR, "issue-notice-upsert: {$msg}\n{$noticeUsage}\n");
    exit(2);
};

$noticeAllowed = ['issue', 'legacy', 'reporter', 'channel', 'thread', 'message'];
$noticeOpts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_starts_with($arg, '--') || !str_contains($arg, '=')) {
        $noticeFail("unrecognized argument '{$arg}' (flags use --name=value)");
    }
    [$key, $value] = explode('=', substr($arg, 2), 2);
    if (!in_array($key, $noticeAllowed, true)) {
        $noticeFail("unknown flag --{$key}");
    }
    $noticeOpts[$key] = $value;
}

$noticeInt = static function (string $key, bool $required) use ($noticeOpts, $noticeFail): ?int {
    if (!isset($noticeOpts[$key])) {
        return $required ? $noticeFail("--{$key} is required") : null;
    }
    if (!ctype_digit($noticeOpts[$key])) {
        $noticeFail("--{$key} must be a non-negative integer");
    }
    return (int) $noticeOpts[$key];
};

$issue = $noticeInt('issue', true);
$legacy = $noticeInt('legacy', false);
if ($issue === null) {
    $noticeFail('--issue is required');
}

$repo = new BugIssueNoticeRepository($mysqli_db);
try {
    $repo->upsert(
        $issue,
        $legacy,
        $noticeOpts['reporter'] ?? null,
        $noticeOpts['channel'] ?? null,
        $noticeOpts['thread'] ?? null,
        $noticeOpts['message'] ?? null
    );
} catch (\InvalidArgumentException $e) {
    $noticeFail($e->getMessage());
}
