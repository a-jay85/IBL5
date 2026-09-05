<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerInterface;

/**
 * Pure field-accessor split of Player's contract getters.
 *
 * Single-consumer trait per ADR-0119: every method here is a pure read of
 * Player's own $playerData property. No state, no constructor, no collaborator
 * call — anything reaching $this->contractCalculator, $this->contractValidator,
 * $this->nameDecorator, $this->injuryCalculator or $this->repository stays in
 * Player.php.
 *
 * @see PlayerInterface
 */
trait PlayerContractGetters
{
    /** @see PlayerInterface::getTeamid() */
    public function getTeamid(): ?int
    {
        return $this->playerData?->teamid;
    }

    /** @see PlayerInterface::getTeamName() */
    public function getTeamName(): ?string
    {
        return $this->playerData?->teamName;
    }

    /** @see PlayerInterface::getTeamColor1() */
    public function getTeamColor1(): ?string
    {
        return $this->playerData?->teamColor1;
    }

    /** @see PlayerInterface::getTeamColor2() */
    public function getTeamColor2(): ?string
    {
        return $this->playerData?->teamColor2;
    }

    /** @see PlayerInterface::getContractCurrentYear() */
    public function getContractCurrentYear(): ?int
    {
        return $this->playerData?->contractCurrentYear;
    }

    /** @see PlayerInterface::getContractTotalYears() */
    public function getContractTotalYears(): ?int
    {
        return $this->playerData?->contractTotalYears;
    }

    /** @see PlayerInterface::getContractYear1Salary() */
    public function getContractYear1Salary(): ?int
    {
        return $this->playerData?->contractYear1Salary;
    }

    /** @see PlayerInterface::getContractYear2Salary() */
    public function getContractYear2Salary(): ?int
    {
        return $this->playerData?->contractYear2Salary;
    }

    /** @see PlayerInterface::getContractYear3Salary() */
    public function getContractYear3Salary(): ?int
    {
        return $this->playerData?->contractYear3Salary;
    }

    /** @see PlayerInterface::getContractYear4Salary() */
    public function getContractYear4Salary(): ?int
    {
        return $this->playerData?->contractYear4Salary;
    }

    /** @see PlayerInterface::getContractYear5Salary() */
    public function getContractYear5Salary(): ?int
    {
        return $this->playerData?->contractYear5Salary;
    }

    /** @see PlayerInterface::getContractYear6Salary() */
    public function getContractYear6Salary(): ?int
    {
        return $this->playerData?->contractYear6Salary;
    }

    /** @see PlayerInterface::getSalaryJSB() */
    public function getSalaryJSB(): ?int
    {
        return $this->playerData?->salaryJSB;
    }

    /** @see PlayerInterface::getBirdYears() */
    public function getBirdYears(): ?int
    {
        return $this->playerData?->birdYears;
    }

    /** @see PlayerInterface::getTimeDroppedOnWaivers() */
    public function getTimeDroppedOnWaivers(): ?int
    {
        return $this->playerData?->timeDroppedOnWaivers;
    }

    /** @see PlayerInterface::getFreeAgencyLoyalty() */
    public function getFreeAgencyLoyalty(): ?int
    {
        return $this->playerData?->freeAgencyLoyalty;
    }

    /** @see PlayerInterface::getFreeAgencyPlayingTime() */
    public function getFreeAgencyPlayingTime(): ?int
    {
        return $this->playerData?->freeAgencyPlayingTime;
    }

    /** @see PlayerInterface::getFreeAgencyPlayForWinner() */
    public function getFreeAgencyPlayForWinner(): ?int
    {
        return $this->playerData?->freeAgencyPlayForWinner;
    }

    /** @see PlayerInterface::getFreeAgencyTradition() */
    public function getFreeAgencyTradition(): ?int
    {
        return $this->playerData?->freeAgencyTradition;
    }

    /** @see PlayerInterface::getFreeAgencySecurity() */
    public function getFreeAgencySecurity(): ?int
    {
        return $this->playerData?->freeAgencySecurity;
    }
}
