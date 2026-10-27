<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversSubmissionServiceInterface;
use Waivers\Contracts\WaiversProcessorInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Waivers\Contracts\WaiversRosterSlotCounterInterface;

/**
 * @see WaiversSubmissionServiceInterface
 */
class WaiversSubmissionService implements WaiversSubmissionServiceInterface
{
    private WaiversProcessorInterface $processor;
    private SalaryCapRepositoryInterface $salaryCapRepo;
    private readonly Season $season;
    private readonly WaiversRosterSlotCounterInterface $slotCounter;
    private readonly TeamIdentityRepositoryInterface $teamIdentityRepo;

    public function __construct(
        WaiversProcessorInterface $processor,
        SalaryCapRepositoryInterface $salaryCapRepo,
        Season $season,
        WaiversRosterSlotCounterInterface $slotCounter,
        TeamIdentityRepositoryInterface $teamIdentityRepo
    ) {
        $this->processor = $processor;
        $this->salaryCapRepo = $salaryCapRepo;
        $this->season = $season;
        $this->slotCounter = $slotCounter;
        $this->teamIdentityRepo = $teamIdentityRepo;
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

        // backlog#1538 — roster counts come from the DB for the verified team, never from POST.
        $teamID = $this->teamIdentityRepo->getTidFromTeamname($teamName);
        if ($teamID === null) {
            return ['success' => false, 'error' => 'Unable to determine your team.'];
        }
        $slots = $this->slotCounter->countOpenSlots($teamID, $this->season);

        // Backlog 13.14 — during phases that advance the contract year (Playoffs, Draft,
        // Free Agency) the live cap basis is next_year_salary, not current_salary.
        $totalSalary = $this->season->advancesContractYears()
            ? $this->salaryCapRepo->getTeamNextYearSalary($teamName)
            : $this->salaryCapRepo->getTeamTotalSalary($teamName);

        if ($action === 'waive') {
            return $this->processor->processDrop($playerID, $teamName, $slots['open'], $totalSalary);
        }

        return $this->processor->processAdd($playerID, $teamName, $slots['open'], $slots['healthy'], $totalSalary);
    }
}
