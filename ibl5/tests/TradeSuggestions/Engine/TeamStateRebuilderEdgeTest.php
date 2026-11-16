<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Projection\ProjectedSeason;

final class TeamStateRebuilderEdgeTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(4);
    }

    private static function salarySum(LeagueSnapshot $s, int $tid, int $offset): int
    {
        $sum = 0;
        foreach (array_keys($s->lineup($offset, $tid)->players) as $pid) {
            $sum += $s->projections[$pid][$offset]->salary;
        }

        return $sum;
    }

    public function testPayrollAddsTheTeamsCashAndTreatsMissingCashAsZero(): void
    {
        $s = self::league();
        $snapshot = new LeagueSnapshot(...[...get_object_vars($s), 'cashByTeamAndOffset' => [1 => [1 => 250]]]);

        $withCash = TeamStateRebuilder::beforeWithout($snapshot, 1, 1, new PairRecordMemo(), []);
        $otherTeam = TeamStateRebuilder::beforeWithout($snapshot, 2, 1, new PairRecordMemo(), []);
        $otherOffset = TeamStateRebuilder::beforeWithout($snapshot, 1, 2, new PairRecordMemo(), []);

        self::assertSame(self::salarySum($s, 1, 1) + 250, $withCash->payroll);
        self::assertSame(self::salarySum($s, 2, 1), $otherTeam->payroll);
        self::assertSame(self::salarySum($s, 1, 2), $otherOffset->payroll);
    }

    public function testRosteredPlayerWhoseSeasonIsOffTheBooksAddsNoSalary(): void
    {
        $s = self::league();
        $offset = 1;
        $tid = 2;
        $lineup = $s->lineups[$offset][$tid];
        $inRotation = [];
        foreach ($lineup->rotation as $pids) {
            foreach ($pids as $pid) {
                $inRotation[$pid] = true;
            }
        }
        $target = null;
        foreach (array_keys($lineup->players) as $pid) {
            if (!isset($inRotation[$pid]) && $lineup->depth->slotOf($pid) === null) {
                $target = $pid;
                break;
            }
        }
        self::assertNotNull($target, 'fixture has a roster player outside every rotation');
        $projections = $s->projections;
        $old = $projections[$target][$offset];
        $projections[$target][$offset] = new ProjectedSeason($old->offset, $old->age, $old->salary, false, $old->ratings, $old->per36);
        $snapshot = new LeagueSnapshot(...[...get_object_vars($s), 'projections' => $projections, 'cashByTeamAndOffset' => []]);

        $state = TeamStateRebuilder::beforeWithout($snapshot, $tid, $offset, new PairRecordMemo(), []);

        self::assertSame(self::salarySum($s, $tid, $offset) - $old->salary, $state->payroll);
        self::assertSame(count($lineup->players) - 1, $state->rosterCount);
    }

    public function testPlayerWithNoSeasonAtTheOffsetIsIgnored(): void
    {
        $s = self::league();
        $offset = 2;
        $gone = null;
        foreach ($s->players as $pid => $player) {
            if ($player->salaryAt($offset) === 0) {
                $gone = $pid;
                break;
            }
        }
        self::assertNotNull($gone, 'fixture has a player whose deal ends before offset 2');
        $projections = $s->projections;
        unset($projections[$gone][$offset]);
        $snapshot = new LeagueSnapshot(...[...get_object_vars($s), 'projections' => $projections]);

        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message, 0, $no);
        });
        try {
            $state = TeamStateRebuilder::beforeWithout($snapshot, 1, $offset, new PairRecordMemo(), []);
        } finally {
            restore_error_handler();
        }

        self::assertSame(count($s->lineups[$offset][1]->players), $state->rosterCount);
        self::assertSame($s->payrollByOffset[$offset][1], $state->payroll);
    }
}
