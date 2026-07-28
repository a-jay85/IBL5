<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use SimRecap\MentionMap;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Pure-unit tests for MentionMap. No real DB — every test seeds rows via
 * MockDatabase and calls MentionMap::fromDatabase() through the real
 * TeamIdentityRepository path (wide-unit style: real classes, mock DB).
 */
final class MentionMapTest extends WideUnitTestCase
{
    // ── byTeamId() ─────────────────────────────────────────────────────────────

    public function testByTeamIdOmitsTeamWithNullDiscordId(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [
            ['teamid' => 1, 'team_city' => 'New York', 'team_name' => 'Metros', 'discord_id' => null],
            ['teamid' => 2, 'team_city' => 'Boston',   'team_name' => 'Minutemen', 'discord_id' => 123456789012345678],
        ]);

        $map = MentionMap::fromDatabase($this->mockDb)->byTeamId();

        self::assertArrayNotHasKey(1, $map, 'team with null discord_id must be omitted from byTeamId()');
        self::assertArrayHasKey(2, $map);
        self::assertSame('123456789012345678', $map[2]);
    }

    // ── byTeamName() ───────────────────────────────────────────────────────────

    public function testByTeamNameMatchesMentionMapVerbShape(): void
    {
        // The existing mention-map verb emitted nickname => raw-snowflake-string.
        // This shape must be preserved exactly so the verb's JSON output is unchanged.
        $this->mockDb->onQuery('ibl_team_info', [
            ['teamid' => 1, 'team_city' => 'New York', 'team_name' => 'Metros',    'discord_id' => null],
            ['teamid' => 2, 'team_city' => 'Boston',   'team_name' => 'Minutemen', 'discord_id' => 123456789012345678],
        ]);

        $map = MentionMap::fromDatabase($this->mockDb)->byTeamName();

        self::assertArrayNotHasKey('Metros', $map, 'team with null discord_id must be omitted from byTeamName()');
        self::assertArrayHasKey('Minutemen', $map);
        self::assertSame('123456789012345678', $map['Minutemen']);
    }

    public function testSnowflakeNear2Pow53SurvivesAsExactString(): void
    {
        // 2^53 = 9007199254740992; 9007199254740993 = 2^53 + 1 cannot be represented
        // exactly as an IEEE-754 double, so jq <= 1.6 corrupts it. PHP ints are 64-bit
        // and represent this exactly; the (string) cast must preserve every digit.
        $snowflake = 9007199254740993;
        $this->mockDb->onQuery('ibl_team_info', [
            ['teamid' => 3, 'team_city' => 'Chicago', 'team_name' => 'Bulls', 'discord_id' => $snowflake],
        ]);

        $map = MentionMap::fromDatabase($this->mockDb)->byTeamName();

        self::assertSame('9007199254740993', $map['Bulls'], 'snowflake near 2^53 must survive (string) cast as exact digit string');
    }

    // ── displayNamesByTeamId() ─────────────────────────────────────────────────

    public function testDisplayNamesByTeamIdReturnsCityAndNickname(): void
    {
        // ActivityTrackerView:30 precedent: trim($row['team_city'] . ' ' . $row['team_name'])
        $this->mockDb->onQuery('ibl_team_info', [
            ['teamid' => 1, 'team_city' => 'New York', 'team_name' => 'Metros', 'discord_id' => null],
        ]);

        $map = MentionMap::fromDatabase($this->mockDb)->displayNamesByTeamId();

        self::assertSame('New York Metros', $map[1]);
    }

    public function testDisplayNamesByTeamIdNoDoubleSpaceWithEmptyCity(): void
    {
        // When team_city is empty, trim() must remove the leading space so the
        // result is just the nickname, not " Minutemen".
        $this->mockDb->onQuery('ibl_team_info', [
            ['teamid' => 2, 'team_city' => '', 'team_name' => 'Minutemen', 'discord_id' => null],
        ]);

        $map = MentionMap::fromDatabase($this->mockDb)->displayNamesByTeamId();

        self::assertSame('Minutemen', $map[2], 'empty team_city must not produce a leading space');
    }
}
