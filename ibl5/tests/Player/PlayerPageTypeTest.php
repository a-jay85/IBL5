<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Player\PlayerPageType;

/**
 * @covers \Player\PlayerPageType
 */
class PlayerPageTypeTest extends TestCase
{
    #[DataProvider('descriptionProvider')]
    public function testGetDescriptionReturnsExpectedLabel(?int $pageView, string $expected): void
    {
        $this->assertSame($expected, PlayerPageType::getDescription($pageView));
    }

    /**
     * @return array<string, array{?int, string}>
     */
    public static function descriptionProvider(): array
    {
        return [
            'overview' => [PlayerPageType::OVERVIEW, 'Player Overview'],
            'awards and news' => [PlayerPageType::AWARDS_AND_NEWS, 'Awards and News'],
            'one on one' => [PlayerPageType::ONE_ON_ONE, 'One-on-One Results'],
            'regular season totals' => [PlayerPageType::REGULAR_SEASON_TOTALS, 'Regular Season Totals'],
            'regular season averages' => [PlayerPageType::REGULAR_SEASON_AVERAGES, 'Regular Season Averages'],
            'playoff totals' => [PlayerPageType::PLAYOFF_TOTALS, 'Playoff Totals'],
            'playoff averages' => [PlayerPageType::PLAYOFF_AVERAGES, 'Playoff Averages'],
            'heat totals' => [PlayerPageType::HEAT_TOTALS, 'H.E.A.T. Totals'],
            'heat averages' => [PlayerPageType::HEAT_AVERAGES, 'H.E.A.T. Averages'],
            'ratings and salary' => [PlayerPageType::RATINGS_AND_SALARY, 'Ratings and Salary History'],
            'sim stats' => [PlayerPageType::SIM_STATS, 'Season Sim Stats'],
            'olympic totals' => [PlayerPageType::OLYMPIC_TOTALS, 'Olympic Totals'],
            'olympic averages' => [PlayerPageType::OLYMPIC_AVERAGES, 'Olympic Averages'],
            'unknown value' => [99, 'Unknown Page Type'],
        ];
    }

    public function testGetUrlForOverviewOmitsPageViewParam(): void
    {
        $url = PlayerPageType::getUrl(12345, PlayerPageType::OVERVIEW);

        $this->assertSame('modules.php?name=Player&pa=showpage&pid=12345', $url);
        $this->assertStringNotContainsString('pageView', $url);
    }

    public function testGetUrlForNonOverviewIncludesPageViewParam(): void
    {
        $url = PlayerPageType::getUrl(12345, PlayerPageType::RATINGS_AND_SALARY);

        $this->assertSame('modules.php?name=Player&pa=showpage&pid=12345&pageView=9', $url);
    }

    public function testGetUrlIncludesPlayerIdInUrl(): void
    {
        $url = PlayerPageType::getUrl(99999, PlayerPageType::AWARDS_AND_NEWS);

        $this->assertStringContainsString('pid=99999', $url);
    }
}
