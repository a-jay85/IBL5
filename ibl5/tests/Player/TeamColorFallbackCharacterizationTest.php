<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Player\Views\TeamColorHelper;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Pins the team-color fallback table: a full row returns its colors, while a
 * missing row, empty columns and NULL columns fall back per column to
 * D4AF37 / 1e3a5f. Provider rows must stay byte-identical across refactors.
 */
final class TeamColorFallbackCharacterizationTest extends TestCase
{
    /**
     * @return array<string, array{0: list<array<string, string|null>>, 1: array{color1: string, color2: string}}>
     */
    public static function colorFallbackCases(): array
    {
        return [
            'row with both colors' => [[['color1' => 'CE1141', 'color2' => '000000']], ['color1' => 'CE1141', 'color2' => '000000']],
            'missing row' => [[], ['color1' => 'D4AF37', 'color2' => '1e3a5f']],
            'empty color1 only' => [[['color1' => '', 'color2' => '000000']], ['color1' => 'D4AF37', 'color2' => '000000']],
            'empty color2 only' => [[['color1' => 'CE1141', 'color2' => '']], ['color1' => 'CE1141', 'color2' => '1e3a5f']],
            'null color1 and color2' => [[['color1' => null, 'color2' => null]], ['color1' => 'D4AF37', 'color2' => '1e3a5f']],
        ];
    }

    /**
     * @param list<array<string, string|null>> $mockRows
     * @param array{color1: string, color2: string} $expected
     */
    #[DataProvider('colorFallbackCases')]
    public function testColorFallbackMatchesCharacterizedTable(array $mockRows, array $expected): void
    {
        $db = new MockDatabase();
        $db->onQuery('SELECT color1, color2', $mockRows);
        $db->setMockData([]);

        self::assertSame($expected, TeamColorHelper::getTeamColors($db, 5));
    }
}
