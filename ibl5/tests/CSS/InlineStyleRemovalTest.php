<?php

declare(strict_types=1);

namespace Tests\CSS;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that redundant inline text-align: center styles have been
 * removed from <td> elements in .ibl-data-table contexts.
 *
 * .ibl-data-table td already sets text-align: center via tables.css,
 * so inline styles are redundant and block CSS-only overrides.
 */
class InlineStyleRemovalTest extends TestCase
{
    #[DataProvider('viewFileProvider')]
    public function testViewFileHasNoRedundantInlineTextAlignCenter(string $filePath): void
    {
        $this->assertFileExists($filePath);
        $content = file_get_contents($filePath);
        $this->assertIsString($content);

        // Match <td with style="text-align: center;" (the exact redundant pattern)
        $matches = [];
        preg_match_all('/<td[^>]*style="text-align: center;"/', $content, $matches);

        $this->assertCount(
            0,
            $matches[0],
            sprintf(
                'File %s still contains %d <td> elements with redundant style="text-align: center;" — '
                . '.ibl-data-table td already sets this via CSS.',
                basename($filePath),
                count($matches[0])
            )
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function viewFileProvider(): array
    {
        $base = __DIR__ . '/../../classes';

        return [
            'NextSimView' => [$base . '/NextSim/NextSimView.php'],
            'Ratings' => [$base . '/UI/Tables/Ratings.php'],
            'Per36Minutes' => [$base . '/UI/Tables/Per36Minutes.php'],
            'SeasonAverages' => [$base . '/UI/Tables/SeasonAverages.php'],
            'PeriodAverages' => [$base . '/UI/Tables/PeriodAverages.php'],
            'SplitStats' => [$base . '/UI/Tables/SplitStats.php'],
            'SeasonTotals' => [$base . '/UI/Tables/SeasonTotals.php'],
            'Contracts' => [$base . '/UI/Tables/Contracts.php'],
        ];
    }
}
