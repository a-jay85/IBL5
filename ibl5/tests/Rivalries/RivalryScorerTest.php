<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalryScorerInterface;
use Rivalries\RivalryScorer;

/**
 * @phpstan-import-type RivalryInput from RivalryScorerInterface
 */
final class RivalryScorerTest extends TestCase
{
    private RivalryScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new RivalryScorer();
    }

    public function testPlayoffMeetingsOutweighFiveTimesTheVolume(): void
    {
        $a = $this->input(['games' => 12, 'a_wins' => 6, 'b_wins' => 6, 'playoff_series' => 2, 'playoff_games' => 10, 'close_games' => 3]);
        $b = $this->input(['games' => 60, 'a_wins' => 30, 'b_wins' => 30, 'close_games' => 15]);

        self::assertSame(48, $this->scorer->score($a));
        self::assertSame(44, $this->scorer->score($b));
        self::assertGreaterThan($this->scorer->score($b), $this->scorer->score($a));
    }

    public function testEvenRecordScoresTwentyMoreThanLopsided(): void
    {
        $even = $this->scorer->score($this->input(['games' => 20, 'a_wins' => 10, 'b_wins' => 10]));
        $lopsided = $this->scorer->score($this->input(['games' => 20, 'a_wins' => 20, 'b_wins' => 0]));

        self::assertSame(27, $even);
        self::assertSame(7, $lopsided);
    }

    public function testWeightsSumToOneHundred(): void
    {
        $max = $this->input([
            'games' => 100, 'a_wins' => 50, 'b_wins' => 50, 'playoff_series' => 5,
            'playoff_games' => 20, 'close_games' => 200, 'recent_games' => 30,
        ]);
        self::assertSame(100, $this->scorer->score($max));
    }

    public function testNineGamesDoesNotQualifyAndTenDoes(): void
    {
        $nine = $this->input(['b' => '9', 'games' => 9, 'a_wins' => 5, 'b_wins' => 4]);
        $ten = $this->input(['b' => '10', 'games' => 10, 'a_wins' => 5, 'b_wins' => 5]);

        self::assertFalse($this->scorer->qualifies($nine));
        self::assertTrue($this->scorer->qualifies($ten));

        $ranked = $this->scorer->rank([$nine, $ten]);
        self::assertCount(1, $ranked);
        self::assertSame('10', $ranked[0]['b']);
    }

    public function testZeroGamesScoresZero(): void
    {
        self::assertSame(0, $this->scorer->score($this->input([])));
    }

    public function testRankTieBreakIsDeterministic(): void
    {
        // 12 games at 6-6 and 10 games at 5-5 with one recent game all score 24.
        $bigger = $this->input(['b' => '9', 'games' => 12, 'a_wins' => 6, 'b_wins' => 6]);
        $seven = $this->input(['b' => '7', 'games' => 10, 'a_wins' => 5, 'b_wins' => 5, 'recent_games' => 1]);
        $three = $this->input(['b' => '3', 'games' => 10, 'a_wins' => 5, 'b_wins' => 5, 'recent_games' => 1]);

        self::assertSame(24, $this->scorer->score($bigger));
        self::assertSame(24, $this->scorer->score($seven));
        self::assertSame(24, $this->scorer->score($three));

        $first = $this->scorer->rank([$seven, $three, $bigger]);
        $second = $this->scorer->rank([$three, $bigger, $seven]);

        self::assertSame($first, $second);
        self::assertSame(['9', '3', '7'], array_map(static fn (array $r): string => $r['b'], $first));
    }

    public function testRecentWindowStartAnchorsOnPassedEndingYear(): void
    {
        self::assertSame(1997, RivalryScorer::recentWindowStart(2001));
        self::assertSame(2022, RivalryScorer::recentWindowStart(2026));
    }

    /**
     * @param array<string, int|string> $over
     * @return RivalryInput
     */
    private function input(array $over): array
    {
        $in = [
            'a' => '1', 'b' => '2', 'games' => 0, 'a_wins' => 0, 'b_wins' => 0,
            'playoff_games' => 0, 'playoff_series' => 0, 'close_games' => 0, 'recent_games' => 0,
        ];
        foreach ($over as $k => $v) {
            $in[$k] = $v;
        }
        /** @var RivalryInput $in */
        return $in;
    }
}
