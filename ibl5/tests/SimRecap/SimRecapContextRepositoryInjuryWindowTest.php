<?php

declare(strict_types=1);

namespace Tests\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\SimRecapContextRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * findAllWindowInjuries() without a real DB: the season years it binds and the
 * shape it maps each row into.
 */
final class SimRecapContextRepositoryInjuryWindowTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function windowProvider(): array
    {
        return [
            'September opens the next season' => ['2031-09-01', '2031-09-07', 2032, 2032],
            'October' => ['2031-10-01', '2031-10-07', 2032, 2032],
            'December' => ['2031-12-25', '2031-12-31', 2032, 2032],
            'spring stays in the same year' => ['2032-03-01', '2032-03-07', 2032, 2032],
            'August stays in the same year' => ['2032-08-01', '2032-08-31', 2032, 2032],
            'window crossing the season start' => ['2031-08-31', '2031-09-02', 2031, 2032],
        ];
    }

    #[DataProvider('windowProvider')]
    public function testBindsSeasonEndingYears(string $start, string $end, int $startSeason, int $endSeason): void
    {
        $db = new MockDatabase();
        $db->onQuery('ibl_jsb_transactions', []);

        self::assertSame([], (new SimRecapContextRepository($db))->findAllWindowInjuries($start, $end));
        self::assertSame([$startSeason, $endSeason, $start, $end], $db->getLastBoundParams());
    }

    public function testMapsEveryRowWithFranchiseNameAsLabel(): void
    {
        $db = new MockDatabase();
        $db->onQuery('ibl_jsb_transactions', [
            [
                'pid' => 11, 'name' => 'Bobby Portis', 'injury_date' => '2032-01-04',
                'injury_description' => 'left shoulder strain', 'injury_games_missed' => 4,
                'from_teamid' => 16, 'team_name' => 'Timberwolves',
            ],
            [
                'pid' => 12, 'name' => 'Nene', 'injury_date' => '2032-01-05',
                'injury_description' => null, 'injury_games_missed' => 2,
                'from_teamid' => 99, 'team_name' => null,
            ],
        ]);

        self::assertSame([
            [
                'pid' => 11, 'name' => 'Bobby Portis', 'date' => '2032-01-04',
                'injuryDescription' => 'left shoulder strain', 'injuryGamesMissed' => 4,
                'teamid' => 16, 'teamName' => 'Timberwolves', 'teamAbbrev' => 'Timberwolves',
            ],
            [
                'pid' => 12, 'name' => 'Nene', 'date' => '2032-01-05',
                'injuryDescription' => '', 'injuryGamesMissed' => 2,
                'teamid' => 99, 'teamName' => '', 'teamAbbrev' => '',
            ],
        ], (new SimRecapContextRepository($db))->findAllWindowInjuries('2032-01-04', '2032-01-05'));
    }
}
