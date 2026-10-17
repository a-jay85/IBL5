<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;

class TeamModeGuesserTest extends TestCase
{
    private TeamModeGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new TeamModeGuesser();
    }

    public function testGuessWinNowAtOrAboveWinNowPct(): void
    {
        // 31-25 = .554: WinNow even with a young roster.
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(31, 25, 22.0));
        // 11-9 = exactly .550: the boundary is inclusive.
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(11, 9, 22.0));
        // 30-26 = .536 falls in the middle band; a 26-year-old roster gives Rebuild.
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(30, 26, 26.0));
    }

    public function testGuessRebuildBelowRebuildPct(): void
    {
        // 15-25 = .375: Rebuild even with a veteran roster.
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(15, 25, 31.0));
        // 8-12 = exactly .400 is the middle band, not Rebuild: age decides.
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(8, 12, 31.0));
    }

    public function testGuessMiddleBandUsesAge(): void
    {
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(25, 25, 28.0));
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(25, 25, 27.9));
    }

    public function testGuessPreseasonUsesAgeOnly(): void
    {
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(0, 0, 28.0));
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(0, 0, 27.9));
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(5, 4, 30.0));
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(5, 4, 24.0));
        // 9-0 is below MIN_GAMES, so the record is ignored.
        self::assertSame(TeamMode::Rebuild, $this->guesser->guess(9, 0, 24.0));
        // 10-0 reaches MIN_GAMES, so the record decides.
        self::assertSame(TeamMode::WinNow, $this->guesser->guess(10, 0, 24.0));
    }
}
