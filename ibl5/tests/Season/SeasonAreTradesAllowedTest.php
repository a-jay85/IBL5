<?php

declare(strict_types=1);

namespace Tests\Season;

use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\Season;

class SeasonAreTradesAllowedTest extends TestCase
{
    private Season $season;

    protected function setUp(): void
    {
        $this->season = new Season($this->createStub(\mysqli::class));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tradesAllowedProvider')]
    public function testAreTradesAllowed(string $phase, string $allowTrades, bool $expected): void
    {
        $this->season->phase = $phase;
        $this->season->allowTrades = $allowTrades;

        $this->assertSame($expected, $this->season->areTradesAllowed());
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function tradesAllowedProvider(): array
    {
        return [
            'draft phase overrides No setting' => ['Draft', 'No', true],
            'draft phase with Yes setting' => ['Draft', 'Yes', true],
            'free agency phase overrides No setting' => ['Free Agency', 'No', true],
            'free agency phase with Yes setting' => ['Free Agency', 'Yes', true],
            'regular season with Yes setting' => ['Regular Season', 'Yes', true],
            'regular season with No setting' => ['Regular Season', 'No', false],
            'preseason with Yes setting' => ['Preseason', 'Yes', true],
            'preseason with No setting' => ['Preseason', 'No', false],
            'playoffs with Yes setting' => ['Playoffs', 'Yes', true],
            'playoffs with No setting' => ['Playoffs', 'No', false],
        ];
    }
}
