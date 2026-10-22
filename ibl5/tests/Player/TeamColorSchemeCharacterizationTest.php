<?php

declare(strict_types=1);

namespace Tests\Player;

use Player\Views\TeamColorHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Golden-table characterization of TeamColorHelper::generateColorScheme().
 *
 * Expected arrays were captured from the pre-split implementation on 2026-10-18.
 * Provider rows must stay byte-identical across refactors.
 *
 * The muted-text '222222' branch is unreachable: it needs a light primary, and
 * '333333' then has contrast >= 6.6, so the < 3.0 test never fires. No row pins it.
 *
 * Mutation check: changing the muted 'dddddd' literal to 'dedede' fails the default-pair row
 * and testGetDefaultColorSchemeMatchesGoldenDefaultRow.
 */
final class TeamColorSchemeCharacterizationTest extends TestCase
{
    private const DEFAULT_CASE = 'default pair D4AF37/1e3a5f: no-adjust, accent fallback, muted adjust';

    /**
     * @return array<string, array{0: string, 1: string, 2: array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}}>
     */
    public static function goldenSchemeCases(): array
    {
        return [
            'loop B exit at last step 98, accent lighten clamps to border, muted adjust' => ['0077c0', '000000', ['primary' => '0077c0', 'secondary' => '000000', 'gradient_start' => '000000', 'gradient_mid' => '006bac', 'gradient_end' => '000000', 'border' => 'f9f9f9', 'border_rgb' => '249, 249, 249', 'accent' => 'f9f9f9', 'text' => 'ffffff', 'text_muted' => 'dddddd']],
            'loop B exit at mid step 92, muted keep' => ['CE1141', '000000', ['primary' => 'CE1141', 'secondary' => '000000', 'gradient_start' => '000000', 'gradient_mid' => 'b90f3a', 'gradient_end' => '000000', 'border' => 'eaeaea', 'border_rgb' => '234, 234, 234', 'accent' => 'ececec', 'text' => 'ffffff', 'text_muted' => 'cccccc']],
            'equal luminance takes loop B, exit at first step 80' => ['555555', '555555', ['primary' => '555555', 'secondary' => '555555', 'gradient_start' => '555555', 'gradient_mid' => '4c4c4c', 'gradient_end' => '555555', 'border' => 'dddddd', 'border_rgb' => '221, 221, 221', 'accent' => 'e0e0e0', 'text' => 'ffffff', 'text_muted' => 'cccccc']],
            'loop B exhausted, dark primary falls back to f0f0f0' => ['777777', '111111', ['primary' => '777777', 'secondary' => '111111', 'gradient_start' => '111111', 'gradient_mid' => '6b6b6b', 'gradient_end' => '111111', 'border' => 'f0f0f0', 'border_rgb' => '240, 240, 240', 'accent' => 'f0f0f0', 'text' => '000000', 'text_muted' => 'dddddd']],
            'primary is color2, loop B exhausted, light primary falls back to 1a1a1a' => ['dddddd', 'ffffff', ['primary' => 'ffffff', 'secondary' => 'dddddd', 'gradient_start' => 'dddddd', 'gradient_mid' => 'e5e5e5', 'gradient_end' => 'dddddd', 'border' => '1a1a1a', 'border_rgb' => '26, 26, 26', 'accent' => '303030', 'text' => '000000', 'text_muted' => '333333']],
            'primary is color2, loop A exhausted, light primary falls back to 1a1a1a' => ['dddddd', 'FDB927', ['primary' => 'FDB927', 'secondary' => 'dddddd', 'gradient_start' => 'dddddd', 'gradient_mid' => 'e3a623', 'gradient_end' => 'dddddd', 'border' => '1a1a1a', 'border_rgb' => '26, 26, 26', 'accent' => '303030', 'text' => '000000', 'text_muted' => '333333']],
            'loop A exhausted, dark primary falls back to f0f0f0, accent fallback' => ['777777', '999999', ['primary' => '777777', 'secondary' => '999999', 'gradient_start' => '999999', 'gradient_mid' => '6b6b6b', 'gradient_end' => '999999', 'border' => 'f0f0f0', 'border_rgb' => '240, 240, 240', 'accent' => 'f0f0f0', 'text' => '000000', 'text_muted' => 'dddddd']],
            'loop A exit at first step 20' => ['000000', '555555', ['primary' => '000000', 'secondary' => '555555', 'gradient_start' => '555555', 'gradient_mid' => '000000', 'gradient_end' => '555555', 'border' => '777777', 'border_rgb' => '119, 119, 119', 'accent' => '848484', 'text' => 'ffffff', 'text_muted' => 'cccccc']],
            'loop A exit at mid step 50' => ['000000', '111111', ['primary' => '000000', 'secondary' => '111111', 'gradient_start' => '111111', 'gradient_mid' => '000000', 'gradient_end' => '111111', 'border' => '888888', 'border_rgb' => '136, 136, 136', 'accent' => '939393', 'text' => 'ffffff', 'text_muted' => 'cccccc']],
            'loop A exit at last step 90, muted adjust' => ['0077c0', 'FDB927', ['primary' => '0077c0', 'secondary' => 'FDB927', 'gradient_start' => 'FDB927', 'gradient_mid' => '006bac', 'gradient_end' => 'FDB927', 'border' => 'fef8e9', 'border_rgb' => '254, 248, 233', 'accent' => 'fef8eb', 'text' => 'ffffff', 'text_muted' => 'dddddd']],
            'primary is color2, no-adjust border keeps seed case, accent lighten, muted keep' => ['FDB927', '552583', ['primary' => '552583', 'secondary' => 'FDB927', 'gradient_start' => 'FDB927', 'gradient_mid' => '4c2175', 'gradient_end' => 'FDB927', 'border' => 'FDB927', 'border_rgb' => '253, 185, 39', 'accent' => 'fdc03c', 'text' => 'ffffff', 'text_muted' => 'cccccc']],
            'no-adjust border, accent fallback, muted adjust' => ['777777', '000000', ['primary' => '777777', 'secondary' => '000000', 'gradient_start' => '000000', 'gradient_mid' => '6b6b6b', 'gradient_end' => '000000', 'border' => '000000', 'border_rgb' => '0, 0, 0', 'accent' => '000000', 'text' => '000000', 'text_muted' => 'dddddd']],
            self::DEFAULT_CASE => ['D4AF37', '1e3a5f', ['primary' => 'D4AF37', 'secondary' => '1e3a5f', 'gradient_start' => '1e3a5f', 'gradient_mid' => 'be9d31', 'gradient_end' => '1e3a5f', 'border' => '1e3a5f', 'border_rgb' => '30, 58, 95', 'accent' => '1e3a5f', 'text' => '000000', 'text_muted' => 'dddddd']],
        ];
    }

    /**
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $expected
     */
    #[DataProvider('goldenSchemeCases')]
    public function testGenerateColorSchemeMatchesGoldenTable(string $color1, string $color2, array $expected): void
    {
        self::assertSame($expected, TeamColorHelper::generateColorScheme($color1, $color2));
    }

    public function testGetDefaultColorSchemeMatchesGoldenDefaultRow(): void
    {
        self::assertSame(self::goldenSchemeCases()[self::DEFAULT_CASE][2], TeamColorHelper::getDefaultColorScheme());
    }
}
