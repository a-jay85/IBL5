<?php

declare(strict_types=1);

namespace Tests\Module;

use Module\ModuleRedirect;
use PHPUnit\Framework\TestCase;

class ModuleRedirectTest extends TestCase
{
    private const BASE = 'modules.php?name=Host';

    public function testTargetForMapsEachRetiredModule(): void
    {
        $this->assertSame('modules.php?name=ApiKeys', ModuleRedirect::targetFor('PlayerExportGuide'));
        $this->assertSame('modules.php?name=Voting', ModuleRedirect::targetFor('VotingResults'));
        $this->assertSame(
            'modules.php?name=RecordHolders&op=allstar',
            ModuleRedirect::targetFor('AllStarAppearances')
        );
        $this->assertSame(
            'modules.php?name=Leaderboards&tab=season',
            ModuleRedirect::targetFor('SeasonLeaderboards')
        );
        $this->assertSame(
            'modules.php?name=Leaderboards&tab=career',
            ModuleRedirect::targetFor('CareerLeaderboards')
        );
    }

    public function testTargetForReturnsNullForUnknownModule(): void
    {
        $this->assertNull(ModuleRedirect::targetFor('Voting'));
        $this->assertNull(ModuleRedirect::targetFor(''));
        // Lookup is case-sensitive: a case-insensitive map would resolve this.
        $this->assertNull(ModuleRedirect::targetFor('votingresults'));
    }

    public function testTargetForNeverRedirectsTheLeaderboardsHost(): void
    {
        // The host module mapping to itself would loop the browser forever.
        $this->assertNull(ModuleRedirect::targetFor('Leaderboards'));
    }

    public function testPassthroughUrlIncludesWhitelistedParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => '5']);
        $this->assertSame(self::BASE . '&teamid=5', $url);
    }

    public function testPassthroughUrlDropsUnlistedParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => '5', 'unlisted' => 'x']);
        $this->assertSame(self::BASE . '&teamid=5', $url);
        $this->assertStringNotContainsString('unlisted', $url);
    }

    public function testPassthroughUrlDropsNonStringValue(): void
    {
        $url1 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => 5]);
        $this->assertSame(self::BASE, $url1);
        $url2 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => ['5']]);
        $this->assertSame(self::BASE, $url2);
    }

    public function testPassthroughUrlDropsEmptyValue(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => '']);
        $this->assertSame(self::BASE, $url);
    }

    public function testPassthroughUrlValidatorRejectsNonDigits(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => 'abc'], ['teamid' => 'ctype_digit']);
        $this->assertSame(self::BASE, $url);
    }

    public function testPassthroughUrlValidatorPassesDigits(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid', 'year'], ['teamid' => '12', 'year' => '2024x'], ['teamid' => 'ctype_digit']);
        $this->assertSame(self::BASE . '&teamid=12&year=2024x', $url);
    }

    public function testPassthroughUrlCrlfInValueIsPercentEncoded(): void
    {
        /** passthroughUrl() builds the URL exclusively via http_build_query with RFC3986 encoding; param values from the request never appear unencoded in the output. */
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => "5\r\nLocation: https://evil.example"]);
        $this->assertStringNotContainsString("\r", $url);
        $this->assertStringNotContainsString("\n", $url);
        $this->assertStringContainsString('%0D%0A', $url);
        $this->assertStringContainsString('Location%3A%20https', $url);
        $this->assertStringNotContainsString('+https', $url);
    }

    public function testPassthroughUrlAbsoluteUrlInValueIsPercentEncoded(): void
    {
        /** passthroughUrl() builds the URL exclusively via http_build_query with RFC3986 encoding; param values from the request never appear unencoded in the output. */
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['ref'], ['ref' => 'https://evil.example/x']);
        $this->assertStringContainsString('https%3A%2F%2Fevil.example%2Fx', $url);
        $this->assertStringNotContainsString('https://evil', $url);
        $this->assertStringNotContainsString('//evil', $url);
        $this->assertStringStartsWith(self::BASE, $url);
    }

    public function testPassthroughUrlNoParamsReturnsBaseTarget(): void
    {
        $url1 = ModuleRedirect::passthroughUrl(self::BASE, [], ['teamid' => '5']);
        $this->assertSame(self::BASE, $url1);
        $url2 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], []);
        $this->assertSame(self::BASE, $url2);
    }

    public function testPassthroughUrlNoMatchingParamsReturnsBaseTarget(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => 'abc'], ['teamid' => 'ctype_digit']);
        $this->assertSame(self::BASE, $url);
        $this->assertStringNotContainsString('teamid', $url);
    }

    public function testPassthroughUrlIsStatelessFunction(): void
    {
        $rc = new \ReflectionClass(\Module\ModuleRedirect::class);
        $m = $rc->getMethod('passthroughUrl');
        foreach ($m->getParameters() as $param) {
            $this->assertNotContains(
                $param->getName(),
                ['db', 'session', 'loggedInTeamID', 'userId', 'username'],
                "passthroughUrl must not take stateful param \${$param->getName()}"
            );
            $paramType = $param->getType();
            if ($paramType instanceof \ReflectionNamedType) {
                $typeName = ltrim($paramType->getName(), '?\\');
                $this->assertStringNotContainsString('mysqli', $typeName);
                $this->assertStringNotContainsString('Database', $typeName);
            }
        }
    }
}
