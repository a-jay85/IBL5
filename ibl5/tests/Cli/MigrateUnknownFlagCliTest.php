<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs bin/migrate with a misspelled flag and proves it is rejected before
 * config.php (and therefore any database connection) is loaded.
 */
#[Group('cli')]
final class MigrateUnknownFlagCliTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/migrate-flag-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($this->tmpDir, 0700, true), 'temp dir must be creatable');

        $includedFile = var_export($this->tmpDir . '/included.txt', true);
        $prepend = '<?php register_shutdown_function(static function (): void { '
            . 'file_put_contents(' . $includedFile . ', implode("\n", get_included_files())); });';
        self::assertNotFalse(file_put_contents($this->tmpDir . '/prepend.php', $prepend));
    }

    protected function tearDown(): void
    {
        foreach (['prepend.php', 'included.txt'] as $file) {
            $path = $this->tmpDir . '/' . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testMisspelledStatusFlagExitsTwoBeforeConfigInclude(): void
    {
        $script = realpath(dirname(__DIR__, 2) . '/bin/migrate');
        self::assertNotFalse($script, 'bin/migrate must exist');

        $process = proc_open(
            [PHP_BINARY, '-d', 'auto_prepend_file=' . $this->tmpDir . '/prepend.php', $script, '--statu'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame(2, $exit, "Expected exit 2 for --statu. stdout: {$stdout} stderr: {$stderr}");
        self::assertStringContainsString('Unknown option(s): --statu', $stderr);

        $included = file_get_contents($this->tmpDir . '/included.txt');
        self::assertIsString($included, 'shutdown hook must record the included files');
        $files = explode("\n", $included);

        $matching = static fn (string $suffix): array => array_filter(
            $files,
            static fn (string $f): bool => str_ends_with($f, $suffix),
        );

        self::assertNotSame([], $matching('/ibl5/classes/Cli/CliFlagGuard.php'), 'the guard must have been loaded');
        self::assertSame([], $matching('/ibl5/config.php'), 'config.php must not load before the flag check');
        self::assertSame([], $matching('/ibl5/db/db.php'), 'db.php must not load before the flag check');
    }
}
