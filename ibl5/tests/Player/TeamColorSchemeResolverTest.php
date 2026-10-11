<?php

declare(strict_types=1);

namespace Tests\Player;

use Player\TeamColorSchemeResolver;
use Player\Views\TeamColorHelper;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @covers \Player\TeamColorSchemeResolver
 */
final class TeamColorSchemeResolverTest extends \PHPUnit\Framework\TestCase
{
    public function testForTradingCardUsesRepositoryRow(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(['color1' => 'ABC123', 'color2' => '0F0F0F']);

        self::assertSame(
            TeamColorHelper::generateColorScheme('ABC123', '0F0F0F'),
            TeamColorSchemeResolver::forTradingCard($repo, 1),
        );
    }

    public function testForTradingCardFallsBackWhenRowMissing(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(null);

        self::assertSame(
            TeamColorHelper::generateColorScheme('D4AF37', '1e3a5f'),
            TeamColorSchemeResolver::forTradingCard($repo, 1),
        );
    }

    public function testForTradingCardSkipsLookupForTeamZero(): void
    {
        $repo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repo->expects(self::never())->method('getTeamColorRow');

        self::assertSame(
            TeamColorHelper::getDefaultColorScheme(),
            TeamColorSchemeResolver::forTradingCard($repo, 0),
        );
    }

    public function testForTradingCardSkipsLookupForNegativeTeamid(): void
    {
        $repo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repo->expects(self::never())->method('getTeamColorRow');

        self::assertSame(
            TeamColorHelper::getDefaultColorScheme(),
            TeamColorSchemeResolver::forTradingCard($repo, -1),
        );
    }

    public function testForTeamUsesRepositoryRow(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(['color1' => 'ABC123', 'color2' => '0F0F0F']);

        self::assertSame(
            TeamColorHelper::generateColorScheme('ABC123', '0F0F0F'),
            TeamColorSchemeResolver::forTeam($repo, 1),
        );
    }

    public function testForTeamLooksUpTeamZeroRow(): void
    {
        $repo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('getTeamColorRow')
            ->with(0)
            ->willReturn(['color1' => '888888', 'color2' => 'cccccc']);

        $scheme = TeamColorSchemeResolver::forTeam($repo, 0);

        self::assertSame(TeamColorHelper::generateColorScheme('888888', 'cccccc'), $scheme);
        self::assertNotSame(TeamColorHelper::getDefaultColorScheme(), $scheme);
    }

    public function testForTeamFallsBackWhenRowMissing(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(null);

        self::assertSame(
            TeamColorHelper::generateColorScheme('D4AF37', '1e3a5f'),
            TeamColorSchemeResolver::forTeam($repo, 1),
        );
    }
}
