<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;

/**
 * Locks the outcome of maintenance items 14.8 + 14.12: the four converted
 * controllers read request input only through Http\HttpRequest.
 *
 * Deliberately scoped to these four files. BanRawSuperglobalsRule still
 * allowlists the Controller.php suffix for DebugController and the deferred
 * ApiHandler sweep, so PHPStan alone cannot enforce this.
 */
final class ControllerSuperglobalFreedomTest extends TestCase
{
    /** @return list<array{string}> */
    public static function convertedControllerProvider(): array
    {
        return [
            ['Player/PlayerPageController.php'],
            ['Team/TeamController.php'],
            ['DepthChartEntry/DepthChartEntryController.php'],
            ['FreeAgency/FreeAgencyController.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('convertedControllerProvider')]
    public function testConvertedControllerReadsNoRequestSuperglobal(string $relativePath): void
    {
        $path = dirname(__DIR__, 2) . '/classes/' . $relativePath;
        self::assertFileExists($path);
        $source = (string) file_get_contents($path);

        foreach (['$_GET', '$_POST', '$_REQUEST'] as $superglobal) {
            self::assertStringNotContainsString(
                $superglobal,
                $source,
                $relativePath . ' must read request input through Http\\HttpRequest, not ' . $superglobal
            );
        }
    }

    public function testHttpRequestIsTheSanctionedBoundary(): void
    {
        $path = dirname(__DIR__, 2) . '/classes/Http/HttpRequest.php';
        self::assertFileExists($path);
        self::assertStringContainsString('$_REQUEST', (string) file_get_contents($path));
    }
}
