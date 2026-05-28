<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use PHPUnit\Framework\Attributes\Group;
use Team\Team;

#[Group('database')]
class TeamOlympicsLoadTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        \BaseMysqliRepository::clearSharedLeagueContext();
        parent::tearDown();
    }

    /**
     * Regression: the Olympics team_info table omits IBL-only salary-cap columns
     * (used_extension_this_chunk, has_mle, has_lle). Once the table-name rewrite
     * was fixed so Team::load() actually resolves the Olympics table, SELECT *
     * returns a row WITHOUT those keys — and Team::fill() fataled with
     * "Cannot assign null to property ...::$hasUsedExtensionThisSim of type int".
     * Loading an Olympics team must default the missing IBL-only fields to 0.
     */
    public function testLoadsOlympicsTeamThatOmitsIblOnlyColumns(): void
    {
        $this->insertRow('ibl_olympics_team_info', [
            'teamid' => 99,
            'team_city' => 'Test City',
            'team_name' => 'Test Oly',
            'color1' => '000000',
            'color2' => 'FFFFFF',
            'arena' => 'Test Arena',
            'capacity' => 0,
            'owner_name' => 'GM Test',
            'owner_email' => 'gm@test',
        ]);

        $context = $this->createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);
        \BaseMysqliRepository::setSharedLeagueContext($context);

        // Must not throw — this is the exact path that fataled in CI.
        $team = Team::initialize($this->db, 99);

        self::assertSame(99, $team->teamid);
        self::assertSame('Test Oly', $team->name);
        self::assertSame(0, $team->hasUsedExtensionThisSim);
        self::assertSame(0, $team->hasUsedExtensionThisSeason);
        self::assertSame(0, $team->has_mle);
        self::assertSame(0, $team->has_lle);
    }
}
