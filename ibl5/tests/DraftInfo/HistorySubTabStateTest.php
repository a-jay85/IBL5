<?php

declare(strict_types=1);

namespace Tests\DraftInfo;

use DraftInfo\HistorySubTabState;
use League\League;
use PHPUnit\Framework\TestCase;

class HistorySubTabStateTest extends TestCase
{
    public function testResolveNoTeamNoViewIsLeague(): void
    {
        $state = HistorySubTabState::resolve(null, null, '', 5);

        $this->assertSame('league', $state->activeSubTab);
        $this->assertSame('My team', $state->teamTabLabel);
        $this->assertNull($state->teamIdToRender);
        $this->assertFalse($state->showLoginPrompt);
    }

    public function testResolveUnknownViewValueIsLeague(): void
    {
        $state = HistorySubTabState::resolve(null, null, 'banana', 5);

        $this->assertSame('league', $state->activeSubTab);
        $this->assertNull($state->teamIdToRender);
        $this->assertFalse($state->showLoginPrompt);
    }

    public function testResolveViewTeamWithViewerRendersOwnTeam(): void
    {
        $state = HistorySubTabState::resolve(null, null, 'team', 5);

        $this->assertSame('team', $state->activeSubTab);
        $this->assertSame('My team', $state->teamTabLabel);
        $this->assertSame(5, $state->teamIdToRender);
        $this->assertNull($state->otherTeamId);
        $this->assertFalse($state->showLoginPrompt);
    }

    public function testResolveViewTeamWithoutViewerShowsLoginPrompt(): void
    {
        $state = HistorySubTabState::resolve(null, null, 'team', null);

        $this->assertSame('team', $state->activeSubTab);
        $this->assertTrue($state->showLoginPrompt);
        $this->assertNull($state->teamIdToRender);
    }

    public function testResolveOwnTeamidLabelsMyTeam(): void
    {
        $state = HistorySubTabState::resolve(5, 'Bulls', '', 5);

        $this->assertSame('team', $state->activeSubTab);
        $this->assertSame('My team', $state->teamTabLabel);
        $this->assertNull($state->otherTeamId);
        $this->assertSame(5, $state->teamIdToRender);
    }

    public function testResolveOtherTeamidLabelsTeamNameAndKeepsTeamid(): void
    {
        $state = HistorySubTabState::resolve(7, 'Celtics', '', 5);

        $this->assertSame('Celtics', $state->teamTabLabel);
        $this->assertSame(7, $state->otherTeamId);
        $this->assertSame(7, $state->teamIdToRender);
        $this->assertFalse($state->showLoginPrompt);
    }

    public function testResolveOtherTeamidWhenLoggedOut(): void
    {
        $state = HistorySubTabState::resolve(7, 'Celtics', '', null);

        $this->assertSame('Celtics', $state->teamTabLabel);
        $this->assertSame(7, $state->otherTeamId);
    }

    public function testResolveUnknownTeamidFallsBackToLeague(): void
    {
        $state = HistorySubTabState::resolve(99, null, '', 5);

        $this->assertSame('league', $state->activeSubTab);
        $this->assertNull($state->teamIdToRender);
        $this->assertNull($state->otherTeamId);
    }

    public function testResolveTeamidWinsOverViewLeague(): void
    {
        $state = HistorySubTabState::resolve(7, 'Celtics', 'league', 5);

        $this->assertSame('team', $state->activeSubTab);
        $this->assertSame(7, $state->teamIdToRender);
    }

    public function testResolveViewerTeamIdSkipsLookupForNullUsername(): void
    {
        $fail = static function (string $arg): never {
            self::fail('lookup must not run for an anonymous visitor');
        };

        $this->assertNull(HistorySubTabState::resolveViewerTeamId(null, $fail, $fail));
        $this->assertNull(HistorySubTabState::resolveViewerTeamId('', $fail, $fail));
    }

    public function testResolveViewerTeamIdNullForFreeAgentsTeamName(): void
    {
        $result = HistorySubTabState::resolveViewerTeamId(
            'someone',
            static fn (string $u): ?string => $u === 'someone' ? League::FREE_AGENTS_TEAM_NAME : null,
            static function (string $name): never {
                self::fail('tid lookup must not run for Free Agents');
            }
        );

        $this->assertNull($result);
    }

    public function testResolveViewerTeamIdNullForZeroTid(): void
    {
        $result = HistorySubTabState::resolveViewerTeamId(
            'someone',
            static fn (string $u): ?string => $u === 'someone' ? 'Bulls' : null,
            static fn (string $name): ?int => $name === 'Bulls' ? 0 : null
        );

        $this->assertNull($result);
    }

    public function testResolveViewerTeamIdReturnsTid(): void
    {
        $result = HistorySubTabState::resolveViewerTeamId(
            'someone',
            static fn (string $u): ?string => $u === 'someone' ? 'Bulls' : null,
            static fn (string $name): ?int => $name === 'Bulls' ? 5 : null
        );

        $this->assertSame(5, $result);
    }

    public function testHistorySubTabStateHoldsNoDbHandle(): void
    {
        $state = HistorySubTabState::resolve(null, null, '', null);
        $violations = [];
        foreach ((new \ReflectionClass($state))->getProperties() as $prop) {
            $type = $prop->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeName = ltrim($type->getName(), '?\\');
                if (str_contains($typeName, 'mysqli') || str_contains($typeName, 'Repository') || str_contains($typeName, 'Database')) {
                    $violations[] = $typeName;
                }
            }
            if (in_array($prop->getName(), ['db', 'repository'], true)) {
                $violations[] = $prop->getName();
            }
        }

        $this->assertSame([], $violations);
    }
}
