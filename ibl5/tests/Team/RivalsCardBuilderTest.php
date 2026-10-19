<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalryScorerInterface;
use Team\RivalsCardBuilder;

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 */
final class RivalsCardBuilderTest extends TestCase
{
    public function testBuildPassesOwnerNameAndLimitThree(): void
    {
        $service = new RivalsCardFakeService();
        $service->rivals = [['a' => '5', 'b' => '7', 'a_label' => '5', 'b_label' => '7', 'games' => 12, 'a_wins' => 7, 'b_wins' => 5, 'score' => 40]];

        $html = (new RivalsCardBuilder($service, static fn (int $tid): string => 'Rival Gm'))->build(5, 'FF0000', '000000');

        self::assertSame([['getTopFranchiseRivals', 5, 3], ['getTopGmRivals', 'Rival Gm', 3]], $service->calls);
        self::assertStringContainsString('Top Rivals', $html);
    }

    public function testBuildSkipsGmLookupWhenOwnerUnknown(): void
    {
        $service = new RivalsCardFakeService();

        (new RivalsCardBuilder($service, static fn (int $tid): ?string => null))->build(5, 'FF0000', '000000');

        self::assertSame([['getTopFranchiseRivals', 5, 3]], $service->calls);
    }

    public function testBuildReturnsEmptyWhenNoRivals(): void
    {
        $service = new RivalsCardFakeService();

        $html = (new RivalsCardBuilder($service, static fn (int $tid): string => 'Rival Gm'))->build(5, 'FF0000', '000000');

        self::assertSame('', $html);
        self::assertCount(2, $service->calls);
    }
}

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 */
final class RivalsCardFakeService implements RivalriesServiceInterface
{
    /** @var list<list<int|string>> */
    public array $calls = [];
    /** @var list<ScoredRivalry> */
    public array $rivals = [];

    public function getLeagueTop(int $limit): array
    {
        return ['franchises' => [], 'gms' => []];
    }

    public function getTopFranchiseRivals(int $teamId, int $limit): array
    {
        $this->calls[] = ['getTopFranchiseRivals', $teamId, $limit];
        return $this->rivals;
    }

    public function getTopGmRivals(string $gmName, int $limit): array
    {
        $this->calls[] = ['getTopGmRivals', $gmName, $limit];
        return [];
    }

    public function getFranchisePair(int $a, int $b): ?array
    {
        return null;
    }

    public function getGmPair(string $a, string $b): ?array
    {
        return null;
    }
}
