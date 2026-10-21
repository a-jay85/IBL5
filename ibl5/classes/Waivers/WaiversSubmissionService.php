<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversSubmissionServiceInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Season\Season;

/**
 * @see WaiversSubmissionServiceInterface
 */
class WaiversSubmissionService implements WaiversSubmissionServiceInterface
{
    private WaiversProcessorInterface $processor;
    private SalaryCapRepositoryInterface $salaryCapRepo;
    private TeamQueryRepositoryInterface $teamQueryRepo;
    private TeamIdentityRepositoryInterface $teamIdentityRepo;
    private readonly Season $season;

    public function __construct(
        WaiversProcessorInterface $processor,
        SalaryCapRepositoryInterface $salaryCapRepo,
        TeamQueryRepositoryInterface $teamQueryRepo,
        TeamIdentityRepositoryInterface $teamIdentityRepo,
        Season $season
    ) {
        $this->processor = $processor;
        $this->salaryCapRepo = $salaryCapRepo;
        $this->teamQueryRepo = $teamQueryRepo;
        $this->teamIdentityRepo = $teamIdentityRepo;
        $this->season = $season;
    }

    /**
     * @see WaiversSubmissionServiceInterface::submit()
     */
    public function submit(array $postData, ?string $verifiedTeamName): array
    {
        // Acting team is the verified session team — never read from POST (IDOR fix D-08).
        // Authz verdict, not a redirect: the caller owns the response (backlog 6.22).
        if ($verifiedTeamName === null || $verifiedTeamName === '') {
            return ['success' => false, 'error' => 'Unable to determine your team.'];
        }
        $teamName = $verifiedTeamName;
        $action = isset($postData['Action']) && is_string($postData['Action']) ? $postData['Action'] : '';
        $playerID = isset($postData['Player_ID']) && is_numeric($postData['Player_ID']) ? (int) $postData['Player_ID'] : null;

        if (!in_array($action, ['add', 'waive'], true)) {
            return ['success' => false, 'error' => 'Invalid submission data.'];
        }

        // Roster counts come from the database for the session team. The posted
        // 'rosterslots' / 'healthyrosterslots' form fields are client-controlled and ignored.
        $teamId = $this->teamIdentityRepo->getTidFromTeamname($teamName);
        if ($teamId === null) {
            return ['success' => false, 'error' => 'Team not found.'];
        }
        $rosterSlots = 15 - count($this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($teamId, $this->season));
        $healthyRosterSlots = 15 - count($this->teamQueryRepo->getHealthyPlayersOrderedByName($teamId, $this->season));

        // Backlog 13.14 — during phases that advance the contract year (Playoffs, Draft,
        // Free Agency) the live cap basis is next_year_salary, not current_salary.
        $totalSalary = $this->season->advancesContractYears()
            ? $this->salaryCapRepo->getTeamNextYearSalary($teamName)
            : $this->salaryCapRepo->getTeamTotalSalary($teamName);

        if ($action === 'waive') {
            return $this->processor->processDrop($playerID, $teamName, $rosterSlots, $totalSalary);
        }

        return $this->processor->processAdd($playerID, $teamName, $rosterSlots, $healthyRosterSlots, $totalSalary);
    }
}
