<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class GitHelpersGuardTest extends TestCase
{
    private string $libPath;

    protected function setUp(): void
    {
        $this->libPath = (string) realpath(__DIR__ . '/../../../bin/lib/git-helpers.sh');
    }

    public function testIsMainCheckoutTrueWhenGitIsDirectory(): void
    {
        $t = sys_get_temp_dir() . '/ghgt-main-' . uniqid();
        mkdir($t . '/.git', 0755, true);

        $output = [];
        $exit = 0;
        $lib = escapeshellarg($this->libPath);
        $dir = escapeshellarg($t);
        exec("bash -c 'source $lib; if is_main_checkout $dir; then echo MAIN; else echo WT; fi' 2>&1", $output, $exit);

        exec('rm -rf ' . escapeshellarg($t));
        self::assertSame(0, $exit);
        self::assertSame('MAIN', implode("\n", $output));
    }

    public function testIsMainCheckoutFalseForWorktreeLayout(): void
    {
        $t = sys_get_temp_dir() . '/ghgt-wt-' . uniqid();
        $m = sys_get_temp_dir() . '/ghgt-main-' . uniqid();
        mkdir($m . '/.git/worktrees/wt', 0755, true);
        mkdir($t, 0755, true);
        file_put_contents($t . '/.git', 'gitdir: ' . $m . '/.git/worktrees/wt');

        $lib = escapeshellarg($this->libPath);
        $dir = escapeshellarg($t);
        $output = [];
        $exit = 0;
        exec("bash -c 'source $lib; if is_main_checkout $dir; then echo MAIN; else echo WT; fi' 2>&1", $output, $exit);
        self::assertSame(0, $exit);
        self::assertSame('WT', implode("\n", $output), 'is_main_checkout should return 1 for worktree layout');

        // Guard against false-pass: canonical must differ from $t
        $canonical = [];
        exec("bash -c 'source $lib; resolve_canonical_root $dir' 2>&1", $canonical);
        $canonicalPath = implode("\n", $canonical);
        self::assertNotEmpty($canonicalPath, 'resolve_canonical_root must return a non-empty path');
        self::assertNotSame($t, $canonicalPath, 'resolve_canonical_root must differ from the worktree root');

        exec('rm -rf ' . escapeshellarg($t) . ' ' . escapeshellarg($m));
    }
}
