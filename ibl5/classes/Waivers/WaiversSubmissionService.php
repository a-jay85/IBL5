<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversSubmissionServiceInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Season\Season;

/**
 * @see WaiversSubmissionServiceInterface
 */
class WaiversSubmissionService implements WaiversSubmissionServiceInterface
{
    private WaiversProcessorInterface $processor;
    private SalaryCapRepositoryInterface $salaryCapRepo;
    private readonly Season $season;

    public function __construct(
        WaiversProcessorInterface $processor,
        SalaryCapRepositoryInterface $salaryCapRepo,
        Season $season
    ) {
        $this->processor = $processor;
        $this->salaryCapRepo = $salaryCapRepo;
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
        $rosterSlots = isset($postData['rosterslots']) && is_numeric($postData['rosterslots']) ? (int) $postData['rosterslots'] : 0;
        $healthyRosterSlots = isset($postData['healthyrosterslots']) && is_numeric($postData['healthyrosterslots']) ? (int) $postData['healthyrosterslots'] : 0;

        if (!in_array($action, ['add', 'waive'], true)) {
            return ['success' => false, 'error' => 'Invalid submission data.'];
        }

        // Backlog 13.14 — during phases that advance the contract year (Playoffs, Draft,
        // Free Agency) the live cap basis is next_year_salary, not current_salary.
        $totalSalary = $this->season->advancesContractYears()
            ? $this->salaryCapRepo->getTeamNextYearSalary($teamName)
            : $this->salaryCapRepo->getTeamTotalSalary($teamName);

        if ($action === 'waive') {
            return $this->processor->processDrop($playerID, $teamName, $rosterSlots, $totalSalary);
        }

        return $this->processor->processAdd($playerID, $teamName, $healthyRosterSlots, $totalSalary);
    }
}
