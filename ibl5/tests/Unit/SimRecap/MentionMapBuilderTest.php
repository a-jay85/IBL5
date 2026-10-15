<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use SimRecap\MentionMapBuilder;

final class MentionMapBuilderTest extends TestCase
{
    public function testBuildReturnsDiscordIdAsString(): void
    {
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getAllRealTeams')->willReturn([['team_name' => 'Metros']]);
        $teams->method('getTeamDiscordID')->willReturnMap([['Metros', 123456789012345678]]);

        self::assertSame(
            ['Metros' => '123456789012345678'],
            (new MentionMapBuilder($teams))->build()
        );
    }

    public function testBuildOmitsTeamWithNullDiscordId(): void
    {
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getAllRealTeams')->willReturn([
            ['team_name' => 'Metros'],
            ['team_name' => 'Stars'],
        ]);
        $teams->method('getTeamDiscordID')->willReturnMap([
            ['Metros', 123456789012345678],
            ['Stars', null],
        ]);

        $map = (new MentionMapBuilder($teams))->build();

        self::assertSame(['Metros' => '123456789012345678'], $map);
        self::assertArrayNotHasKey('Stars', $map);
    }

    public function testBuildReturnsEmptyMapWhenNoRealTeams(): void
    {
        $teams = self::createStub(TeamIdentityRepositoryInterface::class);
        $teams->method('getAllRealTeams')->willReturn([]);

        self::assertSame([], (new MentionMapBuilder($teams))->build());
    }
}
