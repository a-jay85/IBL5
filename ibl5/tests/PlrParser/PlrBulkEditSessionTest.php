<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlrParser\PlrBulkEditSession;

final class PlrBulkEditSessionTest extends TestCase
{
    private const TIMESTAMP = '20261003-000000';

    private string $tmpDir;
    private string $plrPath;
    private string $corpus;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/plr-session-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);
        $this->plrPath = $this->tmpDir . '/IBL5.plr';
        $this->corpus = SyntheticPlrCorpus::corpus();
        file_put_contents($this->plrPath, $this->corpus);
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
     */
    private function session(array $args, ?\Closure $copier = null, ?string $plrPath = null): PlrBulkEditSession
    {
        return PlrBulkEditSession::fromCliArgs('advance-exp', $args, $plrPath ?? $this->plrPath, $copier, self::TIMESTAMP);
    }

    private function backupFile(): string
    {
        return $this->tmpDir . '/IBL5.pre-advance-exp-' . self::TIMESTAMP . '.plr';
    }

    /**
     * @return list<string>
     */
    private function plrFiles(): array
    {
        $files = glob($this->tmpDir . '/*.plr');

        return array_map('basename', $files === false ? [] : $files);
    }

    public function testNoArgsSelectsLiveMode(): void
    {
        self::assertFalse($this->session([])->isDryRun());
    }

    public function testDryRunFlagSelectsDryRunMode(): void
    {
        self::assertTrue($this->session(['--dry-run'])->isDryRun());
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public static function malformedArgsProvider(): array
    {
        return [
            'no dash typo' => [['--dryrun']],
            'with value' => [['--dry-run=1']],
            'positional' => [['foo']],
            'repeated flag' => [['--dry-run', '--dry-run']],
        ];
    }

    /**
     * @param list<string> $args
     */
    #[DataProvider('malformedArgsProvider')]
    public function testRejectsUnknownOrMalformedArgs(array $args): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Usage:/');

        $this->session($args);
    }

    public function testDryRunOpensReadOnlyAndCreatesNoBackup(): void
    {
        $session = $this->session(['--dry-run']);
        $handle = $session->open();

        self::assertStringNotContainsString('+', stream_get_meta_data($handle)['mode']);
        self::assertNull($session->backupPath());
        self::assertSame(['IBL5.plr'], $this->plrFiles());
        fclose($handle);
    }

    public function testDryRunWriteAdvancesPositionWithoutWriting(): void
    {
        $session = $this->session(['--dry-run']);
        $handle = $session->open();

        fseek($handle, 286);
        $session->write($handle, ' 4');

        self::assertSame(288, ftell($handle));
        fclose($handle);
        self::assertSame($this->corpus, file_get_contents($this->plrPath));
    }

    public function testLiveOpenCreatesVerifiedBackupBeforeReturningHandle(): void
    {
        $session = $this->session([]);
        $handle = $session->open();

        $backupPath = $session->backupPath();
        self::assertNotNull($backupPath);
        self::assertStringEndsWith('IBL5.pre-advance-exp-20261003-000000.plr', $backupPath);
        self::assertSame(hash('sha256', $this->corpus), hash_file('sha256', $backupPath));
        self::assertSame(strlen($this->corpus), filesize($backupPath));
        self::assertStringContainsString('+', stream_get_meta_data($handle)['mode']);
        fclose($handle);
    }

    public function testLiveWritePatchesBytesInPlace(): void
    {
        $session = $this->session([]);
        $handle = $session->open();

        fseek($handle, 286);
        $session->write($handle, ' 4');
        fclose($handle);

        $expected = substr_replace($this->corpus, ' 4', 286, 2);
        self::assertSame($expected, file_get_contents($this->plrPath));
        self::assertSame($this->corpus, file_get_contents($this->backupFile()));
    }

    public function testAbortsBeforeOpeningWhenCopierFails(): void
    {
        $session = $this->session([], static fn (string $from, string $to): bool => false);

        try {
            $session->open();
            self::fail('open() should have thrown');
        } catch (\RuntimeException) {
            self::assertSame($this->corpus, file_get_contents($this->plrPath));
            self::assertSame(['IBL5.plr'], $this->plrFiles());
        }
    }

    public function testAbortsAndRemovesBackupOnSizeMismatch(): void
    {
        $truncated = substr($this->corpus, 0, -1);
        $session = $this->session([], static function (string $from, string $to) use ($truncated): bool {
            file_put_contents($to, $truncated);

            return true;
        });

        try {
            $session->open();
            self::fail('open() should have thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('size mismatch', $e->getMessage());
            self::assertFileDoesNotExist($this->backupFile());
            self::assertSame($this->corpus, file_get_contents($this->plrPath));
        }
    }

    public function testAbortsAndRemovesBackupOnHashMismatch(): void
    {
        $reversed = strrev($this->corpus);
        $session = $this->session([], static function (string $from, string $to) use ($reversed): bool {
            file_put_contents($to, $reversed);

            return true;
        });

        try {
            $session->open();
            self::fail('open() should have thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('hash mismatch', $e->getMessage());
            self::assertFileDoesNotExist($this->backupFile());
            self::assertSame($this->corpus, file_get_contents($this->plrPath));
        }
    }

    public function testRefusesToOverwriteExistingBackup(): void
    {
        file_put_contents($this->backupFile(), 'old');
        $session = $this->session([]);

        try {
            $session->open();
            self::fail('open() should have thrown');
        } catch (\RuntimeException) {
            self::assertSame('old', file_get_contents($this->backupFile()));
        }
    }

    public function testThrowsWhenSourceMissing(): void
    {
        $session = $this->session([], null, $this->tmpDir . '/missing.plr');

        try {
            $session->open();
            self::fail('open() should have thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('PLR file not found', $e->getMessage());
            self::assertSame(['IBL5.plr'], $this->plrFiles());
        }
    }
}
