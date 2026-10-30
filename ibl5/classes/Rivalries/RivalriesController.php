<?php

declare(strict_types=1);

namespace Rivalries;

use League\League;
use Player\Views\TeamColorHelper;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Rivalries\Contracts\RivalriesRepositoryInterface;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalriesViewInterface;

/**
 * RivalriesController - Validates GET input and routes to the right view.
 *
 * Every id and name is checked before any service or DB call. No limit or
 * loop bound is read from input, and raw input is never echoed.
 *
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 * @phpstan-import-type ScoredRivalry from \Rivalries\Contracts\RivalryScorerInterface
 */
final class RivalriesController
{
    public const OPS = ['index', 'teams', 'gms', 'gm', 'team'];
    public const MAX_GM_NAME_LENGTH = 60;
    public const TOP_LIMIT = 10;

    // Matches the caption's default background in design/components/tables/rivalries.css (navy-800 at 10% on white).
    private const NO_TEAM_COLOR = 'e9eaeb';

    public function __construct(
        private readonly RivalriesServiceInterface $service,
        private readonly RivalriesViewInterface $view,
        private readonly RivalriesRepositoryInterface $repo,
        private readonly TeamIdentityRepositoryInterface $teams,
    ) {
    }

    /**
     * @param array<mixed> $get
     */
    public function main(array $get, ?string $userTeamKey, ?string $userGm): void
    {
        // @phpstan-ignore ibl.echoInNonView
        echo $this->handle($get, $userTeamKey, $userGm);
    }

    /**
     * @param array<mixed> $get
     */
    public function handle(array $get, ?string $userTeamKey, ?string $userGm): string
    {
        $op = is_string($get['op'] ?? null) && in_array($get['op'], self::OPS, true) ? $get['op'] : 'index';

        switch ($op) {
            case 'teams':
                $a = $this->parseTeamId($get['a'] ?? null);
                $b = $this->parseTeamId($get['b'] ?? null);
                if ($a === null || $b === null || $a === $b) {
                    return $this->view->renderEmpty('Pick two different franchises.');
                }
                $page = $this->service->getFranchisePair($a, $b);
                $teamColors = $this->teamColors($page, [$a, $b]);
                $colors = ['a' => $teamColors[$a]['color1'], 'b' => $teamColors[$b]['color1']];
                $shortLabels = [];
                foreach ([$a, $b] as $id) {
                    $nickname = $this->teams->getTeamnameFromTeamID($id);
                    if ($nickname !== null) {
                        $shortLabels[$id] = $nickname;
                    }
                }
                return $this->view->renderPair($page, 'teams', $colors, $teamColors, $userTeamKey, $shortLabels);

            case 'gms':
                $a = $this->parseGmName($get['a'] ?? null);
                $b = $a === null ? null : $this->parseGmName($get['b'] ?? null);
                if ($a === null || $b === null || $a === $b) {
                    return $this->view->renderEmpty('Pick two different general managers.');
                }
                $page = $this->service->getGmPair($a, $b);
                $teamA = $this->repo->findColorFranchise($a);
                $teamB = $this->repo->findColorFranchise($b);
                $teamColors = $this->teamColors($page, array_values(array_filter(
                    [$teamA, $teamB],
                    static fn (?int $id): bool => $id !== null,
                )));
                $colors = [
                    'a' => $teamA === null ? self::NO_TEAM_COLOR : $teamColors[$teamA]['color1'],
                    'b' => $teamB === null ? self::NO_TEAM_COLOR : $teamColors[$teamB]['color1'],
                ];
                return $this->view->renderPair($page, 'gms', $colors, $teamColors, $userGm);

            case 'team':
                $id = $this->parseTeamId($get['team'] ?? null);
                if ($id === null) {
                    return $this->view->renderEmpty('No franchise by that id.');
                }
                $name = $this->teams->getTeamnameFromTeamID($id);
                if ($name === null || $name === '') {
                    return $this->view->renderEmpty('No franchise by that id.');
                }
                $teamRivals = $this->service->getTopFranchiseRivals($id, self::TOP_LIMIT);
                $owner = $this->teams->getOwnerName($id);
                $gm = $owner !== null && $owner !== '' ? $this->repo->findGmName($owner) : null;
                $gmRivals = $gm === null ? [] : $this->service->getTopGmRivals($gm, self::TOP_LIMIT);
                $team = $this->teams->getTeamByName($name);
                $teamLabel = $team !== null && $team['team_city'] !== '' ? $team['team_city'] . ' ' . $name : $name;
                $captionColor = TeamColorHelper::resolveTeamColors($this->teams->getTeamColorRow($id))['color1'];
                // On your own page every row is yours, so the viewer highlight drops out.
                $highlightTeam = $userTeamKey === (string) $id ? null : $userTeamKey;
                $highlightGm = $gm !== null && $userGm === $gm ? null : $userGm;
                return $this->view->renderTeam(
                    $teamLabel,
                    $teamRivals,
                    $this->teamSideColors($teamRivals),
                    $gm,
                    $gmRivals,
                    $this->gmSideColors($gmRivals),
                    $highlightTeam,
                    $highlightGm,
                    $captionColor,
                );

            case 'gm':
                // No gm param means "my rivals".
                $name = array_key_exists('gm', $get) ? $this->parseGmName($get['gm']) : $userGm;
                if ($name === null) {
                    return $this->view->renderEmpty('No general manager by that name.');
                }
                $rivals = $this->service->getTopGmRivals($name, self::TOP_LIMIT);
                $highlightGm = $userGm === $name ? null : $userGm;
                return $this->view->renderGm($name, $rivals, true, $highlightGm, $this->gmSideColors($rivals));

            default:
                $league = $this->service->getLeagueTop(self::TOP_LIMIT);
                return $this->view->renderIndex($league, $userTeamKey, $userGm, $this->teamSideColors($league['franchises']), $this->gmSideColors($league['gms']));
        }
    }

    /**
     * Colors for every team in the pair page's game tables, plus $extraIds.
     *
     * @param PairPage|null $page
     * @param list<int> $extraIds
     * @return array<int, array{color1: string, color2: string}>
     */
    private function teamColors(?array $page, array $extraIds): array
    {
        $ids = $extraIds;
        foreach ([...$page['closest'] ?? [], ...$page['blowouts'] ?? []] as $g) {
            $ids[] = $g['visitor_teamid'];
            $ids[] = $g['home_teamid'];
        }

        $colors = [];
        foreach (array_unique($ids) as $id) {
            $colors[$id] = TeamColorHelper::resolveTeamColors($this->teams->getTeamColorRow($id));
        }

        return $colors;
    }

    /**
     * Each franchise's primary color, keyed by teamid, for the rivalry cell gradients.
     *
     * @param list<ScoredRivalry> $rivals
     * @return array<string, string>
     */
    private function teamSideColors(array $rivals): array
    {
        $colors = [];
        foreach ($rivals as $r) {
            foreach ([$r['a'], $r['b']] as $key) {
                $colors[$key] ??= TeamColorHelper::resolveTeamColors($this->teams->getTeamColorRow((int) $key))['color1'];
            }
        }

        return $colors;
    }

    /**
     * Each GM's color, keyed by GM name, picked as on the GM pair page: their
     * color franchise's primary color, or gray when they have none.
     *
     * @param list<ScoredRivalry> $rivals
     * @return array<string, string>
     */
    private function gmSideColors(array $rivals): array
    {
        $colors = [];
        foreach ($rivals as $r) {
            foreach ([$r['a'], $r['b']] as $gm) {
                if (isset($colors[$gm])) {
                    continue;
                }
                $teamId = $this->repo->findColorFranchise($gm);
                $colors[$gm] = $teamId === null
                    ? self::NO_TEAM_COLOR
                    : TeamColorHelper::resolveTeamColors($this->teams->getTeamColorRow($teamId))['color1'];
            }
        }

        return $colors;
    }

    private function parseTeamId(mixed $raw): ?int
    {
        if (!is_string($raw) || preg_match('/^[1-9][0-9]?$/D', $raw) !== 1) {
            return null;
        }
        $id = (int) $raw;

        return $id <= League::MAX_REAL_TEAMID ? $id : null;
    }

    private function parseGmName(mixed $raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $name = trim($raw);
        if ($name === '' || mb_strlen($name) > self::MAX_GM_NAME_LENGTH) {
            return null;
        }

        return $this->repo->findGmName($name);
    }
}
