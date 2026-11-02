<?php

declare(strict_types=1);

namespace Tests\CSS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the contract modules (Negotiation, RookieOption, Extension)
 * carry no legacy ibl-btn/card/alert/input/label/filter-form class in any
 * file, docblocks included, after their move to daisyUI d-* classes.
 */
class DaisyUiContractViewsTest extends TestCase
{
    /**
     * Mutation: revert the NegotiationOfferViewInterface docblock line to `.ibl-alert.ibl-alert--error`.
     */
    #[DataProvider('contractModuleFileProvider')]
    public function testContractModuleFileUsesNoLegacyIblComponentClass(string $filePath): void
    {
        $content = self::read($filePath);

        preg_match_all('/\bibl-(btn|card|alert|input|label|filter-form)\b/', $content, $matches);

        $this->assertSame([], $matches[0], basename($filePath) . ' still uses legacy ibl-* component classes');
    }

    /**
     * Mutation: restore the master view (it has no d-* classes).
     */
    #[DataProvider('contractViewProvider')]
    public function testContractViewUsesDaisyUiClasses(string $filePath): void
    {
        $content = self::read($filePath);

        $this->assertMatchesRegularExpression('/\bd-(btn|card|alert)\b/', $content);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contractModuleFileProvider(): array
    {
        $classes = dirname(__DIR__, 2) . '/classes';
        $cases = [];

        foreach (['Negotiation', 'RookieOption', 'Extension'] as $module) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($classes . '/' . $module, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                $cases[substr($path, strlen($classes) + 1)] = [$path];
            }
        }

        ksort($cases);

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contractViewProvider(): array
    {
        $classes = dirname(__DIR__, 2) . '/classes';

        return [
            'NegotiationOfferView' => [$classes . '/Negotiation/NegotiationOfferView.php'],
            'RookieOptionView' => [$classes . '/RookieOption/RookieOptionView.php'],
            'ExtensionView' => [$classes . '/Extension/ExtensionView.php'],
        ];
    }

    private static function read(string $filePath): string
    {
        self::assertFileExists($filePath);
        $content = file_get_contents($filePath); // phpunit-hygiene-allow: the property is the module's class vocabulary, docblocks included, which no render seam exposes
        self::assertIsString($content);

        return $content;
    }
}
