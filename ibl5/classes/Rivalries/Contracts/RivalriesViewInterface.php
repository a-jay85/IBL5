<?php

declare(strict_types=1);

namespace Rivalries\Contracts;

/**
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 */
interface RivalriesViewInterface
{
    /** @param array{franchises: list<ScoredRivalry>, gms: list<ScoredRivalry>} $league */
    public function renderIndex(array $league, ?string $userTeamKey, ?string $userGm): string;

    /**
     * @param PairPage|null $page
     * @param array{a: string, b: string}|null $colors Each side's primary team color as hex,
     *        oriented like $page; null renders the neutral caption strips
     * @param array<int, array{color1: string, color2: string}> $teamColors Colors for each team id in
     *        the game tables, plus a and b on a franchise page; a missing id gets the fallback colors
     * @param string|null $viewerKey The viewer's side key (teamid on a franchise page, GM name on a GM
     *        page). When it is one of the pair, game margins are signed and colored from that side.
     * @param array<array-key, string> $shortLabels Nicknames keyed by side, used in table team cells on a
     *        franchise page; a missing side falls back to its full label
     */
    public function renderPair(?array $page, string $kind, ?array $colors = null, array $teamColors = [], ?string $viewerKey = null, array $shortLabels = []): string;

    /**
     * @param list<ScoredRivalry> $rivals
     * @param array<array-key, string> $sideColors Primary team color hex keyed by GM name. A row whose
     *        two GMs both have a color gets a gradient Rivalry cell.
     */
    public function renderGm(string $gmName, array $rivals, bool $known, ?string $userGm, array $sideColors = []): string;

    /**
     * One franchise's rivals page: its franchise rivals, then its current GM's rivals, stacked.
     *
     * @param string $teamLabel The franchise's city and name, e.g. "Boston Celtics"
     * @param list<ScoredRivalry> $teamRivals
     * @param array<array-key, string> $teamSideColors Primary team color hex keyed by teamid
     * @param string|null $gmName The franchise's current GM in stored spelling; null when it has none
     * @param list<ScoredRivalry> $gmRivals
     * @param array<array-key, string> $gmSideColors Primary team color hex keyed by GM name
     * @param string|null $captionColor The page franchise's primary color hex for the caption strips;
     *        null keeps the neutral strips
     */
    public function renderTeam(
        string $teamLabel,
        array $teamRivals,
        array $teamSideColors,
        ?string $gmName,
        array $gmRivals,
        array $gmSideColors,
        ?string $userTeamKey,
        ?string $userGm,
        ?string $captionColor = null,
    ): string;

    public function renderEmpty(string $message): string;
}
