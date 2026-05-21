<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\Dto\RecapGame;
use LastSimRecap\Dto\RecapInjury;
use LastSimRecap\Dto\RecapSlate;
use LastSimRecap\Dto\RecapStarter;
use LastSimRecap\LastSimRecapView;
use PHPUnit\Framework\TestCase;

class LastSimRecapViewTest extends TestCase
{
    public function testHtmlHasTablistAriaRoles(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$this->makeGame()]));

        self::assertStringContainsString('role="tablist"', $html);
        self::assertStringContainsString('role="tab"', $html);
        self::assertStringContainsString('role="tabpanel"', $html);
    }

    public function testExactlyOneTabHasAriaSelectedTrue(): void
    {
        $games = [
            $this->makeGame(schedId: 1),
            $this->makeGame(schedId: 2, won: false, margin: -7),
            $this->makeGame(schedId: 3),
        ];
        $html = (new LastSimRecapView())->render($this->makeSlate(games: $games));

        self::assertSame(1, substr_count($html, 'aria-selected="true"'));
        self::assertSame(2, substr_count($html, 'aria-selected="false"'));
    }

    public function testAllPanelsRenderedAndOnlyFirstVisible(): void
    {
        $games = [
            $this->makeGame(schedId: 1),
            $this->makeGame(schedId: 2),
            $this->makeGame(schedId: 3),
        ];
        $html = (new LastSimRecapView())->render($this->makeSlate(games: $games));

        // 3 panels total.
        self::assertSame(3, substr_count($html, 'role="tabpanel"'));
        // The non-active panels get a `hidden` attribute. The active one does not.
        $matches = [];
        preg_match_all('/data-panel-index="(\d+)"([^>]*)/i', $html, $matches);
        self::assertCount(3, $matches[0]);

        foreach ($matches[1] as $i => $idxStr) {
            $extra = $matches[2][$i];
            if ($idxStr === '0') {
                self::assertStringNotContainsString(' hidden', $extra);
            } else {
                self::assertStringContainsString(' hidden', $extra);
            }
        }
    }

    public function testWinLossModifiersOnTab(): void
    {
        $games = [
            $this->makeGame(won: true),
            $this->makeGame(won: false, margin: -5),
        ];
        $html = (new LastSimRecapView())->render($this->makeSlate(games: $games));

        self::assertStringContainsString('last-sim-recap__tab--win', $html);
        self::assertStringContainsString('last-sim-recap__tab--loss', $html);
        self::assertStringContainsString('last-sim-recap__tab--active', $html);
    }

    public function testInjuryFlagOnlyAppearsWhenNewInjuryPresent(): void
    {
        $gWith = $this->makeGame(
            yourInjuries: [$this->makeInjury(isNew: true)],
        );
        $gWithout = $this->makeGame();

        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$gWith, $gWithout]));

        // Flag should appear exactly once (in the first tab).
        self::assertSame(1, substr_count($html, 'last-sim-recap__tab-flag'));
    }

    public function testVerdictStripFlipsToLossClass(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$this->makeGame(won: false, margin: -11)]));

        self::assertStringContainsString('last-sim-recap__strip--loss', $html);
        self::assertStringNotContainsString('last-sim-recap__strip--win', $html);
    }

    public function testHealthyLabelRendersWhenNoInjuries(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$this->makeGame()]));

        self::assertStringContainsString('last-sim-recap__inj-healthy', $html);
        self::assertStringContainsString('Healthy', $html);
        self::assertStringNotContainsString('inj-row--empty', $html);
    }

    public function testMaliciousInjuryDescriptionIsEscaped(): void
    {
        $injection = '<script>alert(1)</script>';
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [
            $this->makeGame(
                yourInjuries: [$this->makeInjury(description: $injection)],
            ),
        ]));

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNoBannedHtmlTags(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [
            $this->makeGame(yourInjuries: [$this->makeInjury(isNew: true)]),
        ]));

        self::assertSame(0, preg_match('/<\s*b\b[^>]*>/i', $html), 'no <b>');
        self::assertSame(0, preg_match('/<\s*i\b[^>]*>/i', $html), 'no <i>');
        self::assertSame(0, preg_match('/<\s*center\b[^>]*>/i', $html));
        self::assertSame(0, preg_match('/<\s*font\b[^>]*>/i', $html));
        self::assertSame(0, preg_match('/<\s*u\b[^>]*>/i', $html));
    }

    public function testOnlyCustomPropInlineStyles(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(games: [$this->makeGame()]));

        // Capture every style="..." attribute and ensure each only contains
        // `--`-prefixed declarations (CSS custom properties).
        if (preg_match_all('/style="([^"]+)"/i', $html, $matches) === 0) {
            self::assertTrue(true); // no inline styles at all is fine
            return;
        }
        foreach ($matches[1] as $declList) {
            foreach (array_filter(array_map('trim', explode(';', $declList))) as $decl) {
                self::assertStringStartsWith('--', $decl, "Inline style must be a custom property: '{$decl}'");
            }
        }
    }

    public function testBestWorstMetaFormat(): void
    {
        $html = (new LastSimRecapView())->render($this->makeSlate(
            bestLabel: '+11 vs CLE',
            worstLabel: '−13 @ MIA',
            games: [$this->makeGame()],
        ));

        self::assertStringContainsString('Best:</span>&nbsp;<span class="last-sim-recap__meta-value">+11 vs CLE', $html);
        self::assertStringContainsString('Worst:</span>&nbsp;<span class="last-sim-recap__meta-value">−13 @ MIA', $html);
    }

    private function makeInjury(
        int $pid = 101,
        string $name = 'J. Allen',
        string $pos = 'C',
        string $description = 'Hamstring',
        int $gamesMissed = 5,
        int $daysRemaining = 5,
        bool $isNew = false,
    ): RecapInjury {
        return new RecapInjury($pid, $name, $pos, $description, $gamesMissed, $daysRemaining, $isNew);
    }

    private function makeStarter(string $pos = 'PG'): RecapStarter
    {
        return new RecapStarter(
            pos: $pos,
            youPid: 1, youName: 'D. Garland', youPts: 18, youReb: 5, youAst: 7, youStl: 2, youBlk: 0, youHurt: false,
            oppPid: 2, oppName: 'C. Cunningham', oppPts: 24, oppReb: 8, oppAst: 4, oppStl: 1, oppBlk: 3,
        );
    }

    /**
     * @param list<RecapInjury> $yourInjuries
     * @param list<RecapInjury> $oppInjuries
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
    ): RecapGame {
        $yourScore = $won ? 110 : 100;
        $oppScore = $yourScore - $margin;
        return new RecapGame(
            schedId: $schedId,
            boxId: 0,
            gameOfThatDay: 1,
            date: $date,
            home: $home,
            won: $won,
            yourScore: $yourScore,
            oppScore: $oppScore,
            margin: $margin,
            ot: $ot,
            margins: $ot ? [-2, -6, 12, -4, 4] : [3, -2, 5, 0],
            qLabels: $ot ? ['Q1', 'Q2', 'Q3', 'Q4', 'OT'] : ['Q1', 'Q2', 'Q3', 'Q4'],
            oppTid: 2,
            oppCity: 'Detroit',
            oppName: 'Pistons',
            oppPreWins: 60,
            oppPreLosses: 22,
            yourInjuries: $yourInjuries,
            oppInjuries: $oppInjuries,
            starters: [
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
