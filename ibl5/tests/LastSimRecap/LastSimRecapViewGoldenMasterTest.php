<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\Dto\RecapStarter;
use LastSimRecap\LastSimRecapView;
use PHPUnit\Framework\TestCase;

/**
 * Golden master pin for the LastSimRecapView extraction (backlog 1.29).
 *
 * Snapshots are generated against the PRE-extraction view and committed with
 * the PR. Any byte-level change to the rendered HTML during the extraction
 * fails here.
 *
 * @covers \LastSimRecap\LastSimRecapView
 */
final class LastSimRecapViewGoldenMasterTest extends TestCase
{
    public function testInjuriesBothSidesSnapshot(): void
    {
        $game = $this->makeGame(
            yourInjuries: [
                $this->makeInjury(pid: 101, name: 'J. Allen', pos: 'C', description: 'Hamstring', isNew: true),
                $this->makeInjury(pid: 102, name: 'M. Mobley', pos: 'PF', description: '', daysRemaining: 0, returnDate: ''),
            ],
            oppInjuries: [
                $this->makeInjury(pid: 201, name: 'C. Cunningham', pos: 'PG', description: 'Shin', isNew: false),
            ],
        );

        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$game]));

        $this->assertSnapshotMatches($html, 'render-injuries-both-sides.html');
    }

    public function testBattlesNoInjuriesSnapshot(): void
    {
        $game = $this->makeGame(won: false, margin: -7, home: false, ot: true);

        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$game]));

        $this->assertSnapshotMatches($html, 'render-battles-no-injuries.html');
    }

    public function testNoStartersNoInjuriesSnapshot(): void
    {
        $game = $this->makeGame(starters: []);

        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$game]));

        $this->assertSnapshotMatches($html, 'render-no-starters-no-injuries.html');
    }

    public function testEmptySlateSnapshot(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: []));

        $this->assertSnapshotMatches($html, 'render-empty-slate.html');
    }

    public function testNoMarginsSnapshot(): void
    {
        $game = $this->makeGame(margins: [], qLabels: []);

        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$game]));

        $this->assertSnapshotMatches($html, 'render-no-margins.html');
    }

    private function assertSnapshotMatches(string $actual, string $snapshotFilename): void
    {
        $snapshotDir = __DIR__ . '/__snapshots__';
        $path = $snapshotDir . '/' . $snapshotFilename;

        if (!file_exists($path)) {
            if (!is_dir($snapshotDir)) {
                mkdir($snapshotDir, 0755, true);
            }
            file_put_contents($path, $actual);
            $this->assertFileExists($path, "Snapshot $snapshotFilename was not created");
            return;
        }

        $expected = file_get_contents($path);
        $this->assertSame($expected, $actual, "Golden master mismatch for $snapshotFilename");
    }

    private function makeInjury(
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

    private function makeStarter(string $pos = 'PG'): RecapStarter
    {
        return new RecapStarter(
            pos: $pos,
            youPid: 1,
            youName: 'D. Garland',
            youPts: 18,
            youReb: 5,
            youAst: 7,
            youStl: 2,
            youBlk: 0,
            youHurt: false,
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
    private function makeGame(
        int $schedId = 1,
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
            boxId: 0,
            gameOfThatDay: 1,
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
    private function makeSlate(
        string $bestLabel = '+11 vs PIS',
        string $worstLabel = '−13 @ HEA',
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
            netMargin: -11,
            bestLabel: $bestLabel,
            worstLabel: $worstLabel,
            teamWins: 52,
            teamLosses: 30,
            games: $games,
        );
    }
}
