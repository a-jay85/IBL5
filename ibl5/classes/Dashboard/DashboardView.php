<?php

declare(strict_types=1);

namespace Dashboard;

use BasketballStats\StatsFormatter;
use Dashboard\Contracts\DashboardViewInterface;
use Security\HtmlSanitizer;

/**
 * Renders the GM Dashboard as compact, escaped section cards.
 *
 * Every interpolated player/team name and headline is escaped through
 * {@see HtmlSanitizer::e()}. Each card links to the existing full module page.
 *
 * @phpstan-import-type DashboardData from \Dashboard\Contracts\DashboardServiceInterface
 *
 * @see DashboardViewInterface
 */
class DashboardView implements DashboardViewInterface
{
    /**
     * @see DashboardViewInterface::render()
     */
    public function render(array $dashboardData): string
    {
        $teamName = HtmlSanitizer::e($dashboardData['teamName']);

        $output = "<h1 class=\"ibl-title\">{$teamName} Dashboard</h1>";
        $output .= '<div class="gm-dashboard-grid">';
        $output .= $this->renderPendingTrades($dashboardData['pendingTrades']);
        $output .= $this->renderNextSim($dashboardData['nextSim']);
        $output .= $this->renderCap($dashboardData['cap']);
        $output .= $this->renderUpcomingFreeAgents($dashboardData['upcomingFreeAgents']);
        $output .= $this->renderInjuries($dashboardData['injuries']);
        $output .= $this->renderNews($dashboardData['news']);
        $output .= '</div>';

        return $output;
    }

    /**
     * Render one section card wrapper.
     *
     * @param string $heading Plain heading text (rendered as-is — caller controls it).
     * @param string $body Pre-built, already-escaped inner HTML.
     * @param string $fullPageUrl Link to the full module page.
     */
    private function card(string $heading, string $body, string $fullPageUrl): string
    {
        $url = HtmlSanitizer::e($fullPageUrl);

        return '<section class="gm-dashboard-card">'
            . "<h2 class=\"ibl-title\">{$heading}</h2>"
            . $body
            . "<p class=\"gm-dashboard-link\"><a href=\"{$url}\">View full page &rarr;</a></p>"
            . '</section>';
    }

    /**
     * @param array{count: int, offers: list<array{oppositeTeam: string, approval: string, hasHammer: bool}>} $pendingTrades
     */
    private function renderPendingTrades(array $pendingTrades): string
    {
        if ($pendingTrades['count'] === 0) {
            $body = '<p class="gm-dashboard-empty">No pending trade offers.</p>';

            return $this->card('Pending Trades', $body, 'modules.php?name=Trading&op=reviewtrade');
        }

        $rows = '';
        foreach ($pendingTrades['offers'] as $offer) {
            $oppositeTeam = HtmlSanitizer::e($offer['oppositeTeam']);
            $approval = HtmlSanitizer::e($offer['approval']);
            $hammer = $offer['hasHammer'] ? ' &#9794;' : '';
            $rows .= "<li>{$oppositeTeam} &mdash; {$approval}{$hammer}</li>";
        }
        $count = (string) $pendingTrades['count'];
        $body = "<p>{$count} pending offer(s):</p><ul class=\"gm-dashboard-list\">{$rows}</ul>";

        return $this->card('Pending Trades', $body, 'modules.php?name=Trading&op=reviewtrade');
    }

    /**
     * @param array{opponent: string, location: string, tier: string, date: string}|null $nextSim
     */
    private function renderNextSim(?array $nextSim): string
    {
        if ($nextSim === null) {
            $body = '<p class="gm-dashboard-empty">No upcoming game scheduled.</p>';

            return $this->card('Next Sim', $body, 'modules.php?name=NextSim');
        }

        $opponent = HtmlSanitizer::e($nextSim['opponent']);
        $location = HtmlSanitizer::e($nextSim['location']);
        $tier = HtmlSanitizer::e($nextSim['tier']);
        $date = HtmlSanitizer::e($nextSim['date']);
        $body = "<p>{$location} <strong>{$opponent}</strong></p>"
            . "<p>Tier: {$tier}</p>"
            . "<p>Date: {$date}</p>";

        return $this->card('Next Sim', $body, 'modules.php?name=NextSim');
    }

    /**
     * @param array{headroom: int} $cap
     */
    private function renderCap(array $cap): string
    {
        $headroom = HtmlSanitizer::e(StatsFormatter::formatTotal($cap['headroom']));
        $body = "<p class=\"gm-dashboard-stat\">Cap headroom: <strong>{$headroom}</strong></p>";

        return $this->card('Cap Space', $body, 'modules.php?name=CapSpace');
    }

    /**
     * @param list<array{pid: int, name: string, pos: string, teamid: int}> $freeAgents
     */
    private function renderUpcomingFreeAgents(array $freeAgents): string
    {
        if ($freeAgents === []) {
            $body = '<p class="gm-dashboard-empty">No upcoming free agents on your roster.</p>';

            return $this->card('Upcoming Free Agents', $body, 'modules.php?name=FreeAgencyPreview');
        }

        $rows = '';
        foreach ($freeAgents as $player) {
            $name = HtmlSanitizer::e($player['name']);
            $pos = HtmlSanitizer::e($player['pos']);
            $rows .= "<li>{$pos} {$name}</li>";
        }
        $body = "<ul class=\"gm-dashboard-list\">{$rows}</ul>";

        return $this->card('Upcoming Free Agents', $body, 'modules.php?name=FreeAgencyPreview');
    }

    /**
     * @param list<array{playerID: int, name: string, position: string, daysRemaining: int, teamid: int}> $injuries
     */
    private function renderInjuries(array $injuries): string
    {
        if ($injuries === []) {
            $body = '<p class="gm-dashboard-empty">No injured players on your roster.</p>';

            return $this->card('Injuries', $body, 'modules.php?name=Injuries');
        }

        $rows = '';
        foreach ($injuries as $player) {
            $name = HtmlSanitizer::e($player['name']);
            $position = HtmlSanitizer::e($player['position']);
            $days = (string) $player['daysRemaining'];
            $rows .= "<li>{$position} {$name} &mdash; {$days} day(s)</li>";
        }
        $body = "<ul class=\"gm-dashboard-list\">{$rows}</ul>";

        return $this->card('Injuries', $body, 'modules.php?name=Injuries');
    }

    /**
     * @param list<array{sid: int, title: string, catTitle: string}> $news
     */
    private function renderNews(array $news): string
    {
        if ($news === []) {
            $body = '<p class="gm-dashboard-empty">No recent league news.</p>';

            return $this->card('League News', $body, 'modules.php?name=Topics');
        }

        $rows = '';
        foreach ($news as $item) {
            $title = HtmlSanitizer::e($item['title']);
            $catTitle = HtmlSanitizer::e($item['catTitle']);
            $rows .= "<li>{$title} <span class=\"gm-dashboard-cat\">({$catTitle})</span></li>";
        }
        $body = "<ul class=\"gm-dashboard-list\">{$rows}</ul>";

        return $this->card('League News', $body, 'modules.php?name=Topics');
    }
}
