<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlrBulkScriptsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/plr-bulk-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);
        file_put_contents($this->tmpDir . '/IBL5.plr', SyntheticPlrCorpus::corpus());
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    /**
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runScript(string $scriptName, array $args = []): array
    {
        $scriptPath = dirname(__DIR__, 2) . '/scripts/' . $scriptName . '.php';
        // Prepend the test bootstrap so worktree checkouts (vendor/ symlinked to the
        // main checkout) can autoload classes that exist only in the worktree.
        $process = proc_open(
            [PHP_BINARY, '-d', 'auto_prepend_file=' . __DIR__ . '/../bootstrap.php', $scriptPath, ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tmpDir,
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }

    public function testAdvanceYearsOfExperienceRewritesOnlyExpFieldOfEligibleRows(): void
    {
        $result = $this->runScript('plrAdvanceYearsOfExperience');

        self::assertSame(0, $result['exit']);
        self::assertSame(SyntheticPlrCorpus::corpusAfter('exp'), file_get_contents($this->tmpDir . '/IBL5.plr'));
    }

    public function testDropUnsignedFreeAgentsRewritesTeamAndOwnerOfExpiredRows(): void
    {
        $result = $this->runScript('plrDropUnsignedFreeAgentsToWaivers');

        self::assertSame(0, $result['exit']);
        self::assertSame(SyntheticPlrCorpus::corpusAfter('waivers'), file_get_contents($this->tmpDir . '/IBL5.plr'));
    }

    public function testAdvanceBirdYearsRewritesOnlyBirdFieldOfEligibleRows(): void
    {
        $result = $this->runScript('plrAdvanceBirdYears');

        self::assertSame(0, $result['exit']);
        self::assertSame(SyntheticPlrCorpus::corpusAfter('bird'), file_get_contents($this->tmpDir . '/IBL5.plr'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function scriptProvider(): array
    {
        return [
            'advance experience' => [
                'plrAdvanceYearsOfExperience',
                'advance-exp',
                "Player 101's new years of experience =  4",
            ],
            'drop unsigned free agents' => [
                'plrDropUnsignedFreeAgentsToWaivers',
                'drop-unsigned-fa',
                "Player 101's new teamid =  0",
            ],
            'advance bird years' => [
                'plrAdvanceBirdYears',
                'advance-bird',
                "Player 101's new bird years =  3",
            ],
        ];
    }

    #[DataProvider('scriptProvider')]
    public function testDryRunLeavesFileByteIdenticalAndPrintsPreview(
        string $scriptName,
        string $tag,
        string $previewNeedle,
    ): void {
        $result = $this->runScript($scriptName, ['--dry-run']);

        self::assertSame(0, $result['exit']);
        self::assertSame(SyntheticPlrCorpus::corpus(), file_get_contents($this->tmpDir . '/IBL5.plr'));
        self::assertSame([], glob($this->tmpDir . '/*.pre-*.plr'));
        self::assertStringContainsString('DRY RUN:', $result['stdout']);
        self::assertStringContainsString($previewNeedle, $result['stdout']);
        self::assertStringContainsString('done (dry run: no bytes written).', $result['stdout']);
    }

    #[DataProvider('scriptProvider')]
    public function testLiveRunWritesVerifiedBackupOfOriginalBytes(
        string $scriptName,
        string $tag,
        string $previewNeedle,
    ): void {
        $result = $this->runScript($scriptName, []);

        self::assertSame(0, $result['exit']);
        $backups = glob($this->tmpDir . '/IBL5.pre-' . $tag . '-*.plr');
        self::assertIsArray($backups);
        self::assertCount(1, $backups);
        self::assertStringEndsWith('.plr', $backups[0]);
        self::assertSame(SyntheticPlrCorpus::corpus(), file_get_contents($backups[0]));
        self::assertStringContainsString('Backup written and verified:', $result['stdout']);
    }

    #[DataProvider('scriptProvider')]
    public function testRejectsUnknownFlagWithoutTouchingFile(
        string $scriptName,
        string $tag,
        string $previewNeedle,
    ): void {
        $result = $this->runScript($scriptName, ['--dryrun']);

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('Usage:', $result['stderr']);
        self::assertSame(SyntheticPlrCorpus::corpus(), file_get_contents($this->tmpDir . '/IBL5.plr'));
        self::assertSame([], glob($this->tmpDir . '/*.pre-*.plr'));
    }

    #[DataProvider('scriptProvider')]
    public function testAbortsBeforeAnyWriteWhenPlrFileMissing(
        string $scriptName,
        string $tag,
        string $previewNeedle,
    ): void {
        unlink($this->tmpDir . '/IBL5.plr');

        $result = $this->runScript($scriptName, []);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('Aborted before any write', $result['stderr']);
        self::assertSame([], glob($this->tmpDir . '/*.plr'));
    }

    #[DataProvider('scriptProvider')]
    public function testCliGuardRemainsFirstStatement(
        string $scriptName,
        string $tag,
        string $previewNeedle,
    ): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/' . $scriptName . '.php');
        self::assertIsString($source);

        $skipped = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], $skipped, true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        self::assertStringStartsWith("if(PHP_SAPI!=='cli')", (string) preg_replace('/\s+/', '', $code));
    }
}
