<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class ShipLightEligibleCliTest extends TestCase
{
    private string $repo;
    private string $script;

    protected function setUp(): void
    {
        $this->script = (string) realpath(__DIR__ . '/../../../bin/ship-light-eligible');
        $this->repo = sys_get_temp_dir() . '/ship-light-' . uniqid();
        mkdir($this->repo, 0755, true);

        // A throwaway repo with an origin/master ref the helper diffs against.
        $this->git('init -q');
        $this->git('config user.email t@example.com');
        $this->git('config user.name Test');
        $this->git('config commit.gpgsign false');
        file_put_contents($this->repo . '/baseline.md', "# baseline\n");
        $this->git('add -A');
        $this->git('commit -qm baseline');
        $this->git('update-ref refs/remotes/origin/master HEAD');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function testEligibleForDocsOnlyChange(): void
    {
        file_put_contents($this->repo . '/guide.md', "new doc\n");

        [$exit, $out] = $this->runScript();

        self::assertSame(0, $exit, "docs-only change must be eligible; got: {$out}");
        self::assertStringContainsString('eligible: docs-only', $out);
    }

    public function testIneligibleForNonMarkdownChange(): void
    {
        file_put_contents($this->repo . '/script.php', "<?php\n");

        [$exit, $out] = $this->runScript();

        self::assertSame(1, $exit, 'a non-Markdown change must be ineligible');
        self::assertStringContainsString('non-Markdown', $out);
    }

    public function testIneligibleWhenGateMachineryChanged(): void
    {
        // A Markdown file, but one that DEFINES the arming machinery → self-gating.
        mkdir($this->repo . '/.claude/rules', 0755, true);
        file_put_contents($this->repo . '/.claude/rules/workflow-continuity.md', "edit\n");

        [$exit, $out] = $this->runScript();

        self::assertSame(1, $exit, 'editing arming machinery must be ineligible');
        self::assertStringContainsString('self-gating', $out);
    }

    public function testIneligibleWhenGateAuthoringDocChanged(): void
    {
        // plan.md authors the hold criteria that decide what future changes need review.
        mkdir($this->repo . '/.claude/commands', 0755, true);
        file_put_contents($this->repo . '/.claude/commands/plan.md', "edit\n");

        [$exit, $out] = $this->runScript();

        self::assertSame(1, $exit, 'editing plan-gate authoring docs must be ineligible');
        self::assertStringContainsString('self-gating', $out);
    }

    public function testIneligibleWhenSharedGateComponentChanged(): void
    {
        // Any .claude/commands/_*.md is a shared review/security/test-spec component.
        mkdir($this->repo . '/.claude/commands', 0755, true);
        file_put_contents($this->repo . '/.claude/commands/_review-rubric.md', "edit\n");

        [$exit, $out] = $this->runScript();

        self::assertSame(1, $exit, 'editing a shared gate component must be ineligible');
        self::assertStringContainsString('self-gating', $out);
    }

    public function testIneligibleWhenNoChanges(): void
    {
        [$exit, $out] = $this->runScript();

        self::assertSame(1, $exit, 'a clean tree must be ineligible');
        self::assertStringContainsString('no changes', $out);
    }

    /** @return array{0:int,1:string} */
    private function runScript(): array
    {
        $output = [];
        $exit = 0;
        exec(
            'cd ' . escapeshellarg($this->repo) . ' && '
                . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->script) . ' 2>&1',
            $output,
            $exit
        );
        return [$exit, implode("\n", $output)];
    }

    private function git(string $args): void
    {
        exec('cd ' . escapeshellarg($this->repo) . ' && git ' . $args . ' 2>&1');
    }
}
