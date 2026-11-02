<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use Rivalries\Contracts\RivalriesRepositoryInterface;

class RivalriesSpyRepository implements RivalriesRepositoryInterface
{
    /** @var array<string, int> */
    public array $calls = [];
    /** @var list<int|string> */
    public array $lastArgs = [];
    /** @var array<string, int> GM name => franchise id of their current tenure */
    public array $colorFranchises = [];
    /** @var list<array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}> */
    public array $franchiseStats = [
        ['a' => '3', 'b' => '7', 'close_games' => 2, 'recent_games' => 4, 'playoff_games' => 0, 'playoff_series' => 0],
    ];
    /** @var list<array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}> */
    public array $gmStats = [
        ['a' => 'Amy', 'b' => 'Zed', 'close_games' => 1, 'recent_games' => 2, 'playoff_games' => 0, 'playoff_series' => 0],
    ];

    public function getFranchisePairStats(int $recentFromSeasonYear): array
    {
        $this->record(__FUNCTION__, [$recentFromSeasonYear]);
        return $this->franchiseStats;
    }

    public function getGmPairStats(int $recentFromSeasonYear): array
    {
        $this->record(__FUNCTION__, [$recentFromSeasonYear]);
        return $this->gmStats;
    }

    public function getFranchisePairGames(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getGmPairGames(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getFranchisePlayoffSeries(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getGmPlayoffSeries(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getFranchiseTopPerformers(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return ['totals' => [], 'highs' => []];
    }

    public function getGmTopPerformers(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return ['totals' => [], 'highs' => []];
    }

    public function findGmName(string $gmName): ?string
    {
        $this->record(__FUNCTION__, [$gmName]);
        return null;
    }

    public function findColorFranchise(string $gmName): ?int
    {
        $this->record(__FUNCTION__, [$gmName]);
        return $this->colorFranchises[$gmName] ?? null;
    }

    /** @param list<int|string> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;
        $this->lastArgs = $args;
    }
}
