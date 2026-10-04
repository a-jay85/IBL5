<?php

declare(strict_types=1);

namespace Tests\PlrParser;

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
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
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
        $process = proc_open(
            [PHP_BINARY, $scriptPath, ...$args],
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
}
