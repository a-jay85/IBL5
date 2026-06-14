<?php

declare(strict_types=1);

namespace Dashboard\Contracts;

use Season\Season;

/**
 * Service interface for the GM Dashboard module.
 *
 * Aggregates six existing read-only data sources for the logged-in GM's own
 * team. Every team-scoped section is filtered server-side to the owner's
 * teamid; the news section is league-wide by design.
 *
 * @phpstan-type DashboardTradeOffer array{oppositeTeam: string, approval: string, hasHammer: bool}
 * @phpstan-type DashboardPendingTrades array{count: int, offers: list<DashboardTradeOffer>}
 * @phpstan-type DashboardNextSim array{opponent: string, location: string, tier: string, date: string}
 * @phpstan-type DashboardCap array{headroom: int}
 * @phpstan-type DashboardFreeAgent array{pid: int, name: string, pos: string, teamid: int}
 * @phpstan-type DashboardInjury array{playerID: int, name: string, position: string, daysRemaining: int, teamid: int}
 * @phpstan-type DashboardNewsItem array{sid: int, title: string, catTitle: string}
 * @phpstan-type DashboardData array{
 *     teamId: int,
 *     teamName: string,
 *     pendingTrades: DashboardPendingTrades,
 *     nextSim: DashboardNextSim|null,
 *     cap: DashboardCap,
 *     upcomingFreeAgents: list<DashboardFreeAgent>,
 *     injuries: list<DashboardInjury>,
 *     news: list<DashboardNewsItem>
 * }
 */
interface DashboardServiceInterface
{
    /**
     * Aggregate the dashboard data for a single owner's team.
     *
     * @param int $ownerTeamId Resolved server-side from the session; never a request param.
     * @param string $ownerTeamName Resolved server-side from the session.
     * @param string $ownerUsername Resolved server-side from the session.
     * @param Season $season Current season.
     * @return DashboardData Aggregated, owner-scoped dashboard sections.
     */
    public function getDashboardData(int $ownerTeamId, string $ownerTeamName, string $ownerUsername, Season $season): array;
}
