<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Services\CommonMysqliRepository;

/**
 * @phpstan-import-type Dimension from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Phase from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type Scope from HeadToHeadRecordsRepositoryInterface
 */
class HeadToHeadRecordsController
{
    /** @var list<Scope> */
    private const VALID_SCOPES = ['current', 'all_time'];

    /** @var list<Dimension> */
    private const VALID_DIMENSIONS = ['active_teams', 'all_time_teams', 'gms'];

    /** @var list<Phase> */
    private const VALID_PHASES = ['heat', 'regular', 'playoffs', 'all'];

    /** @var array<string, Phase> */
    private const SEASON_PHASE_TO_FILTER = [
        'HEAT' => 'heat',
        'Regular Season' => 'regular',
        'Playoffs' => 'playoffs',
    ];

    private HeadToHeadRecordsRepositoryInterface $repository;
    private HeadToHeadRecordsView $view;
    private CommonMysqliRepository $commonRepository;
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(
        HeadToHeadRecordsRepositoryInterface $repository,
        HeadToHeadRecordsView $view,
        CommonMysqliRepository $commonRepository,
        ?\Utilities\NukeCompat $nukeCompat = null
    ) {
        $this->repository = $repository;
        $this->view = $view;
        $this->commonRepository = $commonRepository;
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
    }

    public function main(mixed $user, \Season\Season $season): void
    {
        \PageLayout\PageLayout::header();

        $postScope = isset($_POST['scope']) && is_string($_POST['scope']) ? $_POST['scope'] : null;
        $postDimension = isset($_POST['dimension']) && is_string($_POST['dimension']) ? $_POST['dimension'] : null;
        $postPhase = isset($_POST['phase']) && is_string($_POST['phase']) ? $_POST['phase'] : null;

        /** @var Scope $scope */
        $scope = ($postScope !== null && in_array($postScope, self::VALID_SCOPES, true))
            ? $postScope : 'current';

        /** @var Dimension $dimension */
        $dimension = ($postDimension !== null && in_array($postDimension, self::VALID_DIMENSIONS, true))
            ? $postDimension : 'active_teams';

        /** @var Phase $phase */
        $phase = $this->resolvePhase($postPhase, $season->phase);

        $currentSeasonYear = $season->endingYear;

        $loggedInUsername = $this->resolveLoggedInUsername($user);
        $franchiseId = $this->resolveUserFranchiseId($loggedInUsername);

        $payload = $this->repository->getMatrix($scope, $dimension, $phase, $currentSeasonYear);

        $userMatchKeys = $this->resolveUserMatchKeys($dimension, $loggedInUsername, $franchiseId, $payload['axis']);

        echo '<h2 class="ibl-title">Head-to-Head Records</h2>';
        echo $this->view->renderFilterForm($scope, $dimension, $phase);
        echo $this->view->renderMatrix($payload, $userMatchKeys);
        echo $this->view->renderTapTooltipScript();

        \PageLayout\PageLayout::footer();
    }

    /**
     * @return Phase
     */
    private function resolvePhase(?string $postPhase, string $seasonPhase): string
    {
        if ($postPhase !== null && in_array($postPhase, self::VALID_PHASES, true)) {
            /** @var Phase $postPhase */
            return $postPhase;
        }

        return self::SEASON_PHASE_TO_FILTER[$seasonPhase] ?? 'all';
    }

    private function resolveLoggedInUsername(mixed $user): string
    {
        if (!$this->nukeCompat->isUser($user)) {
            return '';
        }

        $cookie = $this->nukeCompat->cookieDecode($user);
        return $cookie[1] ?? '';
    }

    private function resolveUserFranchiseId(string $username): int
    {
        if ($username === '') {
            return 0;
        }

        $teamName = $this->commonRepository->getTeamnameFromUsername($username);
        if ($teamName === null || $teamName === \League\League::FREE_AGENTS_TEAM_NAME) {
            return 0;
        }

        return $this->commonRepository->getTidFromTeamname($teamName) ?? 0;
    }

    /**
     * @param Dimension $dimension
     * @param list<array{key: string|int, label: string, logo: string, franchise_id: int}> $axis
     * @return list<string|int>
     */
    private function resolveUserMatchKeys(string $dimension, string $username, int $franchiseId, array $axis): array
    {
        if ($username === '' && $franchiseId === 0) {
            return [];
        }

        /** @var list<string|int> $keys */
        $keys = [];

        foreach ($axis as $entry) {
            $match = match ($dimension) {
                'active_teams' => $entry['franchise_id'] === $franchiseId,
                'all_time_teams' => $entry['franchise_id'] === $franchiseId,
                'gms' => $entry['key'] === $username,
            };

            if ($match) {
                $keys[] = $entry['key'];
            }
        }

        return $keys;
    }
}
