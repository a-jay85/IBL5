<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source-content pins for the PHPStan annotations in the bug-pipeline CLI
 * wrappers. Asserts on the wrappers' SOURCE and never runs one: a real run
 * would claim rows or write to the DB.
 *
 * PHPStan does not carry the trailing `@var \mysqli $mysqli_db` in
 * _bootstrap.php across the `require`, so every wrapper repeats it.
 */
final class BugPipelineWrapperAnnotationWiringTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function wrapperProvider(): iterable
    {
        yield 'claim-next' => ['claim-next'];
        yield 'get-reporter-tech-level' => ['get-reporter-tech-level'];
        yield 'list-active-conversations' => ['list-active-conversations'];
        yield 'list-pr-open' => ['list-pr-open'];
        yield 'report-issue-renumber' => ['report-issue-renumber'];
        yield 'set-reporter-tech-level' => ['set-reporter-tech-level'];
        yield 'transition' => ['transition'];
    }

    /**
     * Wrappers over BugIssueNoticeRepository (the issue-notice-* family).
     *
     * @return iterable<string, array{string}>
     */
    public static function noticeWrapperProvider(): iterable
    {
        yield 'issue-notice-claim' => ['issue-notice-claim'];
        yield 'issue-notice-mark' => ['issue-notice-mark'];
        yield 'issue-notice-pending' => ['issue-notice-pending'];
        yield 'issue-notice-release' => ['issue-notice-release'];
        yield 'issue-notice-seed' => ['issue-notice-seed'];
        yield 'issue-notice-skip' => ['issue-notice-skip'];
        yield 'issue-notice-stale' => ['issue-notice-stale'];
        yield 'issue-notice-upsert' => ['issue-notice-upsert'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function argvWrapperProvider(): iterable
    {
        yield 'claim-next' => ['claim-next'];
        yield 'report-issue-renumber' => ['report-issue-renumber'];
        yield 'transition' => ['transition'];
        yield from self::noticeWrapperProvider();
    }

    #[DataProvider('wrapperProvider')]
    public function testWrapperAnnotatesMysqliBetweenBootstrapRequireAndRepositoryConstruction(string $name): void
    {
        $src = $this->readWrapper($name);

        $requirePos = strpos($src, "require __DIR__ . '/_bootstrap.php';");
        self::assertNotFalse($requirePos, 'bootstrap require not found in ' . $name);
        $constructPos = strpos($src, 'new BugReportRepository($mysqli_db)');
        self::assertNotFalse($constructPos, 'repository construction not found in ' . $name);

        self::assertSame(
            1,
            preg_match('/@var\s+\\\\mysqli\s+\$mysqli_db/', $src, $m, PREG_OFFSET_CAPTURE),
            $name . ' must annotate $mysqli_db as \mysqli'
        );
        $annotationPos = $m[0][1];

        self::assertGreaterThan($requirePos, $annotationPos, 'annotation must come after the bootstrap require in ' . $name);
        self::assertLessThan($constructPos, $annotationPos, 'annotation must come before the repository construction in ' . $name);
    }

    #[DataProvider('noticeWrapperProvider')]
    public function testNoticeWrapperAnnotatesMysqliBetweenBootstrapRequireAndRepositoryConstruction(string $name): void
    {
        $src = $this->readWrapper($name);

        $requirePos = strpos($src, "require __DIR__ . '/_bootstrap.php';");
        self::assertNotFalse($requirePos, 'bootstrap require not found in ' . $name);
        $constructPos = strpos($src, 'new BugIssueNoticeRepository($mysqli_db)');
        self::assertNotFalse($constructPos, 'repository construction not found in ' . $name);

        self::assertSame(
            1,
            preg_match('/@var\s+\\\\mysqli\s+\$mysqli_db/', $src, $m, PREG_OFFSET_CAPTURE),
            $name . ' must annotate $mysqli_db as \mysqli'
        );

        self::assertGreaterThan($requirePos, $m[0][1], 'annotation must come after the bootstrap require in ' . $name);
        self::assertLessThan($constructPos, $m[0][1], 'annotation must come before the repository construction in ' . $name);
    }

    #[DataProvider('argvWrapperProvider')]
    public function testWrapperAnnotatesArgvBeforeFirstArgvRead(string $name): void
    {
        $src = $this->readWrapper($name);

        $annotationPos = strpos($src, '@var list<string> $argv');
        self::assertNotFalse($annotationPos, $name . ' must annotate $argv as list<string>');

        self::assertSame(
            1,
            preg_match('/^(?!\s*(?:\*|\/\/|\/\*)).*\$argv/m', $src, $m, PREG_OFFSET_CAPTURE),
            'no non-comment $argv read found in ' . $name
        );

        self::assertLessThan($m[0][1], $annotationPos, 'annotation must precede the first $argv read in ' . $name);
    }

    public function testClaimNextOwnerGuardDropsDeadFalseArm(): void
    {
        $src = $this->readWrapper('claim-next');

        self::assertStringNotContainsString('$owner === false', $src, 'substr() cannot return false on PHP 8+; the arm is dead');
        self::assertStringContainsString("if (\$owner === '') {", $src, 'the empty --owner= guard must stay');
    }

    public function testClaimNextKeepsOwnerFallbackBody(): void
    {
        $src = $this->readWrapper('claim-next');

        self::assertStringContainsString("\$owner = 'unknown:' . getmypid();", $src);
    }

    private function readWrapper(string $name): string
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/scripts/bug-pipeline/' . $name . '.php');
        if ($src === false) {
            self::fail('could not read bug-pipeline wrapper ' . $name);
        }

        return $src;
    }
}
