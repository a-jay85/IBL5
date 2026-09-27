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
    }

    public function testTargetForReturnsNullForUnknownModule(): void
    {
        $this->assertNull(ModuleRedirect::targetFor('Voting'));
        $this->assertNull(ModuleRedirect::targetFor(''));
        // Lookup is case-sensitive: a case-insensitive map would resolve this.
        $this->assertNull(ModuleRedirect::targetFor('votingresults'));
    }

    public function testPassthroughUrlIncludesWhitelistedStringParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => '5']);
        $this->assertSame(self::BASE . '&teamid=5', $url);
    }

    public function testPassthroughUrlDropsUnlistedParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => '5', 'unlisted' => 'x']);
        $this->assertSame(self::BASE . '&teamid=5', $url);
        $this->assertStringNotContainsString('unlisted', $url);
    }

    public function testPassthroughUrlDropsNonStringValue(): void
    {
        $url1 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => 5]);
        $this->assertSame(self::BASE, $url1);
        $url2 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => ['5']]);
        $this->assertSame(self::BASE, $url2);
    }

    public function testPassthroughUrlDropsEmptyValue(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => '']);
        $this->assertSame(self::BASE, $url);
    }

    public function testPassthroughUrlValidatorRejectsNonDigits(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], ['teamid' => 'ctype_digit'], ['teamid' => 'abc']);
        $this->assertSame(self::BASE, $url);
    }

    public function testPassthroughUrlValidatorPassesDigits(): void
    {
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid', 'year'], ['teamid' => 'ctype_digit'], ['teamid' => '12', 'year' => '2024x']);
        $this->assertSame(self::BASE . '&teamid=12&year=2024x', $url);
    }

    public function testSecurityCrlfInWhitelistedParamIsPercentEncoded(): void
    {
        /** passthroughUrl() builds the URL exclusively via http_build_query with RFC3986 encoding; param values from the request never appear unencoded in the output. */
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, ['teamid' => "5\r\nLocation: https://evil.example"]);
        $this->assertStringNotContainsString("\r", $url);
        $this->assertStringNotContainsString("\n", $url);
        $this->assertStringContainsString('%0D%0A', $url);
        $this->assertStringContainsString('Location%3A%20https', $url);
        $this->assertStringNotContainsString('+https', $url);
    }

    public function testSecurityAbsoluteUrlInWhitelistedParamIsPercentEncoded(): void
    {
        /** passthroughUrl() builds the URL exclusively via http_build_query with RFC3986 encoding; param values from the request never appear unencoded in the output. */
        $url = ModuleRedirect::passthroughUrl(self::BASE, ['ref'], null, ['ref' => 'https://evil.example/x']);
        $this->assertStringContainsString('https%3A%2F%2Fevil.example%2Fx', $url);
        $this->assertStringNotContainsString('https://evil', $url);
        $this->assertStringNotContainsString('//evil', $url);
        $this->assertStringStartsWith(self::BASE, $url);
    }

    public function testPassthroughUrlNoParamsReturnsBaseTarget(): void
    {
        $url1 = ModuleRedirect::passthroughUrl(self::BASE, [], null, ['teamid' => '5']);
        $this->assertSame(self::BASE, $url1);
        $url2 = ModuleRedirect::passthroughUrl(self::BASE, ['teamid'], null, []);
        $this->assertSame(self::BASE, $url2);
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
