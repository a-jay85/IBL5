<?php

declare(strict_types=1);

namespace Tests\Unit\E2e;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../e2e/vr-recorder/functions.php';

/**
 * Pure helpers behind the CI-only VR rendered-View recorder.
 */
final class VrViewRecorderTest extends TestCase
{
    public function testRecorderDisabledWhenEnvUnset(): void
    {
        $this->assertFalse(\ibl_vr_recorder_enabled(false));
    }

    public function testRecorderDisabledWhenEnvEmpty(): void
    {
        $this->assertFalse(\ibl_vr_recorder_enabled(''));
    }

    public function testRecorderEnabledOnlyForLiteralOne(): void
    {
        $this->assertFalse(\ibl_vr_recorder_enabled('0'));
        $this->assertFalse(\ibl_vr_recorder_enabled('true'));
        $this->assertFalse(\ibl_vr_recorder_enabled(' 1'));
        $this->assertFalse(\ibl_vr_recorder_enabled('1 '));
        $this->assertTrue(\ibl_vr_recorder_enabled('1'));
    }

    public function testParseCellCookieAcceptsRowAndTitle(): void
    {
        $this->assertSame(
            ['row' => 'player', 'cell' => 'player-mobile'],
            \ibl_vr_parse_cell_cookie('player~player-mobile'),
        );
    }

    /** @return array<string, array{mixed}> */
    public static function malformedCookieProvider(): array
    {
        return [
            'no tilde' => ['player'],
            'empty row' => ['~x'],
            'path traversal' => ['../x~y'],
            'slash in cell' => ['player~a/b'],
            'dot in cell' => ['player~a.b'],
            'trailing newline' => ["player~x\n"],
            'empty string' => [''],
            'null' => [null],
            'array' => [['a']],
        ];
    }

    #[DataProvider('malformedCookieProvider')]
    public function testParseCellCookieRejectsMalformedValues(mixed $raw): void
    {
        $this->assertNull(\ibl_vr_parse_cell_cookie($raw));
    }

    public function testFilterKeepsSortedRepoRelativeViewFiles(): void
    {
        $result = \ibl_vr_filter_view_files([
            '/srv/ibl5/classes/Player/Views/PlayerTradingCardFlipView.php',
            '/srv/ibl5/classes/A/ARenderer.php',
            '/srv/ibl5/classes/A/ARenderer.php',
            '/srv/ibl5/classes/F/BFormatter.php',
        ], '/srv/ibl5');

        $this->assertSame([
            'ibl5/classes/A/ARenderer.php',
            'ibl5/classes/F/BFormatter.php',
            'ibl5/classes/Player/Views/PlayerTradingCardFlipView.php',
        ], $result);
    }

    public function testFilterDropsNearMissAndOutsideRootPaths(): void
    {
        $result = \ibl_vr_filter_view_files([
            '/srv/ibl5/classes/Player/Views/PlayerViewFactory.php',
            '/srv/ibl5/classes/View.php',
            '/srv/ibl5/mainfile.php',
            '/srv/ibl5/vendor/x/classes/XView.php',
            '/srv/xxxx/classes/AView.php',
        ], '/srv/ibl5');

        $this->assertSame([], $result);
    }

    /**
     * The filter suffix group must stay anchored to View, Renderer and Formatter. A file
     * ending in Viewx.php is the near-miss a mutated suffix group such as `(Viewx)` would keep.
     */
    public function testFilterDropsViewxSuffixNearMiss(): void
    {
        $result = \ibl_vr_filter_view_files([
            '/srv/ibl5/classes/A/AViewx.php',
            '/srv/ibl5/classes/A/ARendererx.php',
        ], '/srv/ibl5');

        $this->assertSame([], $result);
    }

    public function testWriteRecordCreatesOneJsonFile(): void
    {
        $dir = sys_get_temp_dir() . '/vr-rec-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $ok = \ibl_vr_write_record(
                $dir,
                ['row' => 'player', 'cell' => 'player-mobile'],
                '/ibl5/modules.php?name=Player&pid=1',
                ['ibl5/classes/A/ARenderer.php'],
            );

            $this->assertTrue($ok);
            $files = glob($dir . '/*.json');
            $this->assertIsArray($files);
            $this->assertCount(1, $files);
            $decoded = json_decode((string) file_get_contents($files[0]), true);
            $this->assertSame([
                'row' => 'player',
                'cell' => 'player-mobile',
                'uri' => '/ibl5/modules.php?name=Player&pid=1',
                'files' => ['ibl5/classes/A/ARenderer.php'],
            ], $decoded);
            $this->assertSame([], glob($dir . '/.*.tmp'));
        } finally {
            $leftovers = glob($dir . '/{,.}*', GLOB_BRACE);
            foreach ($leftovers === false ? [] : $leftovers as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            rmdir($dir);
        }
    }

    public function testWriteRecordFailsForMissingDir(): void
    {
        $dir = sys_get_temp_dir() . '/vr-rec-missing-' . bin2hex(random_bytes(4));

        $ok = \ibl_vr_write_record($dir, ['row' => 'player', 'cell' => 'x'], '/', []);

        $this->assertFalse($ok);
        $this->assertDirectoryDoesNotExist($dir);
    }
}
