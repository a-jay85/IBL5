<?php

declare(strict_types=1);

namespace Tests\PlayerSearch;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchView;

/**
 * Byte-exact goldens for PlayerSearchView::renderSearchForm().
 *
 * The goldens were captured from the unrefactored method and must never be
 * regenerated. They pin fieldset order, selected-option arms, and escaping.
 */
final class PlayerSearchViewGoldenTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function formCases(): array
    {
        return [
            'default' => [[]],
            'populated' => [[
                'search_name' => 'Smith',
                'pos' => 'PG',
                'active' => 1,
                'age' => '31',
                'exp' => '2',
                'exp_max' => '12',
                'bird' => '3',
                'bird_max' => '9',
                'college' => 'Duke',
                'talent' => '4',
                'skill' => '5',
                'intangibles' => '6',
                'Clutch' => '7',
                'Consistency' => '8',
                'r_fga' => '11',
                'r_fgp' => '12',
                'r_fta' => '13',
                'r_ftp' => '14',
                'r_3ga' => '15',
                'r_3gp' => '16',
                'r_orb' => '17',
                'r_drb' => '18',
                'r_ast' => '19',
                'r_stl' => '20',
                'r_blk' => '21',
                'r_to' => '22',
                'r_foul' => '23',
                'oo' => '31',
                'do' => '32',
                'po' => '33',
                'to' => '34',
                'od' => '35',
                'dd' => '36',
                'pd' => '37',
                'td' => '38',
            ]],
            'hostile' => [[
                'search_name' => '"><script>alert(1)</script>',
                'college' => "' onfocus='alert(2)",
                'r_fga' => '<img src=x onerror=alert(3)>',
                'oo' => '&amp;"<>',
                'pos' => 'PG"><script>alert(4)</script>',
                'active' => '1',
                'age' => ['x'],
                'exp' => 1.5,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('formCases')]
    public function testRenderSearchFormMatchesGolden(array $params): void
    {
        $html = (new PlayerSearchView())->renderSearchForm($params);

        $goldenPath = __DIR__ . '/fixtures/search-form-' . $this->dataName() . '.golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    public function testHostileInputGoldenHasNoUnescapedPayload(): void
    {
        $golden = file_get_contents(__DIR__ . '/fixtures/search-form-hostile.golden.html');
        $this->assertIsString($golden);

        $this->assertStringNotContainsString('<script>alert(1)', $golden);
        $this->assertStringNotContainsString('<script>alert(4)', $golden);
        $this->assertStringNotContainsString('<img src=x', $golden);
        $this->assertStringNotContainsString("onfocus='alert(2)", $golden);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $golden);
    }
}
