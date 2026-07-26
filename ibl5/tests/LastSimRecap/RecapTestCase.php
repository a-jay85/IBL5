<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\Dto\RecapStarter;
use PHPUnit\Framework\TestCase;

abstract class RecapTestCase extends TestCase
{
    protected function makeInjury(
        int $pid = 101,
        string $name = 'J. Allen',
        string $pos = 'C',
        string $description = 'Hamstring',
        int $gamesMissed = 5,
        int $daysRemaining = 5,
        string $returnDate = '2030-06-01',
        bool $isNew = false,
    ): RecapInjury {
        return new RecapInjury(
            pid: $pid,
            name: $name,
            pos: $pos,
            description: $description,
            gamesMissed: $gamesMissed,
            daysRemaining: $daysRemaining,
            returnDate: $returnDate,
            isNew: $isNew,
        );
    }

    protected function makeStarter(
        string $pos = 'PG',
        int $youPts = 18,
        int $youReb = 5,
        int $youAst = 7,
        int $youStl = 2,
        int $youBlk = 0,
        bool $youHurt = false,
    ): RecapStarter {
        return new RecapStarter(
            pos: $pos,
            youPid: 1,
            youName: 'D. Garland',
            youPts: $youPts,
            youReb: $youReb,
            youAst: $youAst,
            youStl: $youStl,
            youBlk: $youBlk,
            youHurt: $youHurt,
            oppPid: 2,
            oppName: 'C. Cunningham',
            oppPts: 24,
            oppReb: 8,
            oppAst: 4,
            oppStl: 1,
            oppBlk: 3,
        );
    }

    /**
     * @param list<RecapInjury> $yourInjuries
     * @param list<RecapInjury> $oppInjuries
     * @param list<int>|null $margins
     * @param list<string>|null $qLabels
     * @param list<RecapStarter>|null $starters
     */
    protected function makeGame(
        int $schedId = 1,
        int $boxId = 0,
        int $gameOfThatDay = 1,
        string $date = '2026-05-13',
        bool $home = true,
        bool $won = true,
        int $margin = 4,
        bool $ot = false,
        array $yourInjuries = [],
        array $oppInjuries = [],
        ?array $margins = null,
        ?array $qLabels = null,
        ?array $starters = null,
    ): RecapGame {
        return new RecapGame(
            schedId: $schedId,
            boxId: $boxId,
            gameOfThatDay: $gameOfThatDay,
            date: $date,
            home: $home,
            won: $won,
            yourScore: 110,
            oppScore: 110 - $margin,
            margin: $margin,
            ot: $ot,
            margins: $margins ?? ($ot ? [-2, -6, 12, -4, 4] : [3, -2, 5, 0]),
            qLabels: $qLabels ?? ($ot ? ['Q1', 'Q2', 'Q3', 'Q4', 'OT'] : ['Q1', 'Q2', 'Q3', 'Q4']),
            oppTid: 2,
            oppCity: 'Detroit',
            oppName: 'Pistons',
            oppPreWins: 60,
            oppPreLosses: 22,
            yourInjuries: $yourInjuries,
            oppInjuries: $oppInjuries,
            starters: $starters ?? [
                $this->makeStarter('PG'),
                $this->makeStarter('SG'),
                $this->makeStarter('SF'),
                $this->makeStarter('PF'),
                $this->makeStarter('C'),
            ],
        );
    }

    /**
     * @param list<RecapGame> $games
     */
    protected function makeSlate(
        string $bestLabel = '+11 vs PIS',
        string $worstLabel = '−13 @ HEA',
        int $netMargin = -11,
        array $games = [],
    ): RecapSlate {
        return new RecapSlate(
            teamTid: 1,
            teamCity: 'Cleveland',
            teamName: 'Cavaliers',
            simNumber: 42,
            startDate: '2026-05-01',
            endDate: '2026-05-13',
            wins: 4,
            losses: 3,
            netMargin: $netMargin,
            bestLabel: $bestLabel,
            worstLabel: $worstLabel,
            teamWins: 52,
            teamLosses: 30,
            games: $games,
        );
    }
}
