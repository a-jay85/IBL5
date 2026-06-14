<?php

declare(strict_types=1);

namespace Dashboard;

use CapSpace\CapSpaceService;
use Dashboard\Contracts\DashboardServiceInterface;
use FreeAgencyPreview\Contracts\FreeAgencyPreviewServiceInterface;
use Injuries\Contracts\InjuriesServiceInterface;
use NextSim\Contracts\NextSimServiceInterface;
use Season\Season;
use Topics\Contracts\TopicsServiceInterface;
use Trading\Contracts\TradingServiceInterface;

/**
 * DashboardService - Aggregator for the GM Dashboard module.
 *
 * Delegates to six read-only collaborators and composes owner-scoped data
 * into the DashboardData DTO. Writes no SQL and touches no database directly.
 *
 * @phpstan-import-type DashboardData from DashboardServiceInterface
 */
final class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly TradingServiceInterface $tradingService,
        private readonly NextSimServiceInterface $nextSimService,
        private readonly CapSpaceService $capSpaceService,
        private readonly FreeAgencyPreviewServiceInterface $freeAgencyPreviewService,
        private readonly InjuriesServiceInterface $injuriesService,
        private readonly TopicsServiceInterface $topicsService,
    ) {
    }

    /**
     * @return DashboardData
     */
    public function getDashboardData(
        int $ownerTeamId,
        string $ownerTeamName,
        string $ownerUsername,
        Season $season,
    ): array {
        return [
            'teamId'              => $ownerTeamId,
            'teamName'            => $ownerTeamName,
            'pendingTrades'       => $this->buildPendingTrades($ownerUsername),
            'nextSim'             => $this->buildNextSim($ownerTeamId, $season),
            'cap'                 => $this->buildCap($ownerTeamId, $season),
            'upcomingFreeAgents'  => $this->buildUpcomingFreeAgents($ownerTeamId, $season),
            'injuries'            => $this->buildInjuries($ownerTeamId),
            'news'                => $this->buildNews(),
        ];
    }

    /**
     * @return array{count: int, offers: list<array{oppositeTeam: string, approval: string, hasHammer: bool}>}
     */
    private function buildPendingTrades(string $ownerUsername): array
    {
        $offers = $this->tradingService->getTradeReviewPageData($ownerUsername)['tradeOffers'];
        $summary = array_map(
            static fn(array $offer): array => [
                'oppositeTeam' => $offer['oppositeTeam'],
                'approval'     => $offer['approval'],
                'hasHammer'    => $offer['hasHammer'],
            ],
            $offers,
        );

        return [
            'count'  => count($offers),
            'offers' => array_values($summary),
        ];
    }

    /**
     * @return array{opponent: string, location: string, tier: string, date: string}|null
     */
    private function buildNextSim(int $ownerTeamId, Season $season): array|null
    {
        $games = $this->nextSimService->getNextSimGames($ownerTeamId, $season);
        if ($games === []) {
            return null;
        }

        $first = $games[array_key_first($games)];

        return [
            'opponent' => $first['opposingTeam']->name,
            'location' => $first['locationPrefix'],
            'tier'     => $first['opponentTier'],
            'date'     => $first['date']->format('Y-m-d'),
        ];
    }

    /**
     * @return array{headroom: int}
     */
    private function buildCap(int $ownerTeamId, Season $season): array
    {
        $rows = $this->capSpaceService->getTeamsCapData($season);
        foreach ($rows as $row) {
            if ($row['teamId'] === $ownerTeamId) {
                return ['headroom' => $row['availableSalary']['year1']];
            }
        }

        return ['headroom' => 0];
    }

    /**
     * @return list<array{pid: int, name: string, pos: string, teamid: int}>
     */
    private function buildUpcomingFreeAgents(int $ownerTeamId, Season $season): array
    {
        $all = $this->freeAgencyPreviewService->getUpcomingFreeAgents($season->endingYear);
        $filtered = array_filter(
            $all,
            static fn(array $p): bool => $p['teamid'] === $ownerTeamId,
        );

        return array_values(array_map(
            static fn(array $p): array => [
                'pid'    => $p['pid'],
                'name'   => $p['name'],
                'pos'    => $p['pos'],
                'teamid' => $p['teamid'],
            ],
            $filtered,
        ));
    }

    /**
     * @return list<array{playerID: int, name: string, position: string, daysRemaining: int, teamid: int}>
     */
    private function buildInjuries(int $ownerTeamId): array
    {
        $all = $this->injuriesService->getInjuredPlayersWithTeams();
        $filtered = array_filter(
            $all,
            static fn(array $p): bool => $p['teamid'] === $ownerTeamId,
        );

        return array_values(array_map(
            static fn(array $p): array => [
                'playerID'      => $p['playerID'],
                'name'          => $p['name'],
                'position'      => $p['position'],
                'daysRemaining' => $p['daysRemaining'],
                'teamid'        => $p['teamid'],
            ],
            $filtered,
        ));
    }

    /**
     * @return list<array{sid: int, title: string, catTitle: string}>
     */
    private function buildNews(): array
    {
        $topics = $this->topicsService->getPageData(false)['topics'];

        /** @var list<array{sid: int, title: string, catTitle: string}> $articles */
        $articles = [];
        foreach ($topics as $topic) {
            foreach ($topic['recentArticles'] as $article) {
                $articles[] = [
                    'sid'      => $article['sid'],
                    'title'    => $article['title'],
                    'catTitle' => $article['catTitle'],
                ];
            }
        }

        usort($articles, static fn(array $a, array $b): int => $b['sid'] <=> $a['sid']);

        return array_values(array_slice($articles, 0, 5));
    }
}
