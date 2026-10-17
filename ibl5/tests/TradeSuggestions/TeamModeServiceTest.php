<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TeamModeGuesser;
use TradeSuggestions\TeamModeService;
use TradeSuggestions\TeamModeSetting;

class TeamModeServiceTest extends TestCase
{
    public function testResolveUsesGuessWhenAuto(): void
    {
        $repo = new class (TeamModeSetting::Auto, 10, 30) implements TeamModeRepositoryInterface {
            public function __construct(
                private readonly TeamModeSetting $stored,
                private readonly int $wins,
                private readonly int $losses,
            ) {
            }

            public function getStoredSetting(int $tid): TeamModeSetting
            {
                return $this->stored;
            }

            public function getAllStoredSettings(): array
            {
                return [];
            }

            public function saveSetting(int $tid, TeamModeSetting $setting): void
            {
            }

            public function getModeInputs(int $tid): array
            {
                return ['wins' => $this->wins, 'losses' => $this->losses, 'avgAge' => 30.0];
            }
        };

        $resolved = (new TeamModeService($repo, new TeamModeGuesser()))->resolve(5);

        self::assertSame(TeamMode::Rebuild, $resolved->mode);
        self::assertSame(TeamMode::Rebuild, $resolved->guessed);
        self::assertFalse($resolved->isOverride);
    }

    public function testResolveOverrideBeatsGuess(): void
    {
        $repo = new class (TeamModeSetting::WinNow, 10, 30) implements TeamModeRepositoryInterface {
            public function __construct(
                private readonly TeamModeSetting $stored,
                private readonly int $wins,
                private readonly int $losses,
            ) {
            }

            public function getStoredSetting(int $tid): TeamModeSetting
            {
                return $this->stored;
            }

            public function getAllStoredSettings(): array
            {
                return [];
            }

            public function saveSetting(int $tid, TeamModeSetting $setting): void
            {
            }

            public function getModeInputs(int $tid): array
            {
                return ['wins' => $this->wins, 'losses' => $this->losses, 'avgAge' => 30.0];
            }
        };

        $resolved = (new TeamModeService($repo, new TeamModeGuesser()))->resolve(5);

        self::assertSame(TeamMode::WinNow, $resolved->mode);
        self::assertSame(TeamMode::Rebuild, $resolved->guessed);
        self::assertTrue($resolved->isOverride);
    }

    public function testResolveFromInputsMatchesResolve(): void
    {
        foreach ([TeamModeSetting::Auto, TeamModeSetting::WinNow, TeamModeSetting::Rebuild] as $setting) {
            $repo = new class ($setting) implements TeamModeRepositoryInterface {
                public function __construct(private readonly TeamModeSetting $stored)
                {
                }

                public function getStoredSetting(int $tid): TeamModeSetting
                {
                    return $this->stored;
                }

                public function getAllStoredSettings(): array
                {
                    return [];
                }

                public function saveSetting(int $tid, TeamModeSetting $setting): void
                {
                }

                public function getModeInputs(int $tid): array
                {
                    return ['wins' => 10, 'losses' => 30, 'avgAge' => 30.0];
                }
            };

            $service = new TeamModeService($repo, new TeamModeGuesser());

            $this->assertEquals(
                $service->resolve(5),
                $service->resolveFromInputs($setting, 10, 30, 30.0),
                $setting->value,
            );
        }
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

            public function getModeInputs(int $tid): array
            {
                return ['wins' => 0, 'losses' => 0, 'avgAge' => 0.0];
            }
        };

        (new TeamModeService($repo, new TeamModeGuesser()))->setOverride(7, TeamModeSetting::Rebuild);

        self::assertSame([['tid' => 7, 'setting' => TeamModeSetting::Rebuild]], $repo->saves);
    }
}
