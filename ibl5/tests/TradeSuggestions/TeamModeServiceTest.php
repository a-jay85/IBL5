<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\ModeInputs;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

class TeamModeServiceTest extends TestCase
{
    public function testAutoUsesGuess(): void
    {
        $resolved = $this->service()->resolveFromInputs(TeamModeSetting::Auto, self::losingTeam());

        self::assertSame(TeamMode::Rebuild, $resolved->mode);
        self::assertSame(TeamMode::Rebuild, $resolved->guessed);
        self::assertFalse($resolved->isOverride);
        self::assertNotNull($resolved->guess);
        self::assertSame(self::losingTeam()->wins, $resolved->guess->inputs->wins);
    }

    public function testOverrideBeatsGuessAndKeepsIt(): void
    {
        $resolved = $this->service()->resolveFromInputs(TeamModeSetting::WinNow, self::losingTeam());

        self::assertSame(TeamMode::WinNow, $resolved->mode);
        self::assertSame(TeamMode::Rebuild, $resolved->guessed);
        self::assertTrue($resolved->isOverride);
        self::assertNotNull($resolved->guess);
    }

    public function testSetOverrideWritesOnlyGivenTeam(): void
    {
        $repo = new class implements TeamModeRepositoryInterface {
            /** @var list<array{tid: int, setting: TeamModeSetting}> */
            public array $saves = [];

            public function getStoredSetting(int $tid): TeamModeSetting
            {
                return TeamModeSetting::Auto;
            }

            public function getAllStoredSettings(): array
            {
                return [];
            }

            public function saveSetting(int $tid, TeamModeSetting $setting): void
            {
                $this->saves[] = ['tid' => $tid, 'setting' => $setting];
            }
        };

        (new TeamModeService($repo, new TeamModeGuesser()))->setOverride(7, TeamModeSetting::Rebuild);

        self::assertSame([['tid' => 7, 'setting' => TeamModeSetting::Rebuild]], $repo->saves);
    }

    /**
     * The Middle flag reaches the Auto arm only. A WinNow override near .500
     * keeps the two-way guess (WinNow), so its guessed mode is never Middle.
     */
    public function testAutoResolvesMiddleOnlyWhenAllowed(): void
    {
        $nearFiveHundred = new ModeInputs(41, 41, 41, 41, 26.5, 0.5);

        $autoWithout = $this->service()->resolveFromInputs(TeamModeSetting::Auto, $nearFiveHundred);
        $autoWith = $this->service()->resolveFromInputs(TeamModeSetting::Auto, $nearFiveHundred, true);
        $override = $this->service()->resolveFromInputs(TeamModeSetting::WinNow, $nearFiveHundred, true);
        $rebuild = $this->service()->resolveFromInputs(TeamModeSetting::Rebuild, $nearFiveHundred, true);

        self::assertSame(TeamMode::WinNow, $autoWithout->mode);
        self::assertSame(TeamMode::Middle, $autoWith->mode);
        self::assertSame(TeamMode::Middle, $autoWith->guessed);
        self::assertFalse($autoWith->isOverride);
        self::assertSame(TeamMode::WinNow, $override->mode);
        self::assertSame(TeamMode::WinNow, $override->guessed);
        self::assertTrue($override->isOverride);
        self::assertSame(TeamMode::Rebuild, $rebuild->mode);
        self::assertSame(TeamMode::WinNow, $rebuild->guessed);
    }

    private function service(): TeamModeService
    {
        return new TeamModeService(self::createStub(TeamModeRepositoryInterface::class), new TeamModeGuesser());
    }

    private static function losingTeam(): ModeInputs
    {
        return new ModeInputs(10, 30, 20, 62, 29.0, 0.4);
    }
}
