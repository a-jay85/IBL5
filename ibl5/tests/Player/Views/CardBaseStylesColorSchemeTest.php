<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use Player\Views\CardBaseStyles;
use Player\Views\TeamColorHelper;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * CardBaseStyles::getColorSchemeForTeam() resolves colors through the
 * TeamIdentityRepositoryInterface row and the TeamColorHelper fallback.
 */
final class CardBaseStylesColorSchemeTest extends \PHPUnit\Framework\TestCase
{
    public function testColorSchemeForTeamUsesRepositoryRow(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(['color1' => 'ABC123', 'color2' => '0F0F0F']);

        self::assertSame(
            TeamColorHelper::generateColorScheme('ABC123', '0F0F0F'),
            CardBaseStyles::getColorSchemeForTeam($repo, 1),
        );
    }

    public function testColorSchemeForTeamFallsBackWhenRowMissing(): void
    {
        $repo = self::createStub(TeamIdentityRepositoryInterface::class);
        $repo->method('getTeamColorRow')->willReturn(null);

        self::assertSame(
            TeamColorHelper::generateColorScheme('D4AF37', '1e3a5f'),
            CardBaseStyles::getColorSchemeForTeam($repo, 1),
        );
    }

    public function testColorSchemeForTeamDefaultsWithoutRepository(): void
    {
        self::assertSame(
            TeamColorHelper::getDefaultColorScheme(),
            CardBaseStyles::getColorSchemeForTeam(null, 1),
        );
    }

    public function testColorSchemeForTeamSkipsLookupForTeamZero(): void
    {
        $repo = $this->createMock(TeamIdentityRepositoryInterface::class);
        $repo->expects(self::never())->method('getTeamColorRow');

        self::assertSame(
            TeamColorHelper::getDefaultColorScheme(),
            CardBaseStyles::getColorSchemeForTeam($repo, 0),
        );
    }
}
