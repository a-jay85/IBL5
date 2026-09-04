<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerInterface;

/**
 * Pure field-accessor split of Player's identity getters.
 *
 * Single-consumer trait per ADR-0119: every method here is a pure read of
 * Player's own $playerData property. No state, no constructor, no collaborator
 * call — anything reaching $this->contractCalculator, $this->contractValidator,
 * $this->nameDecorator, $this->injuryCalculator or $this->repository stays in
 * Player.php.
 *
 * @see PlayerInterface
 */
trait PlayerIdentityGetters
{
    /** @see PlayerInterface::getPlayerID() */
    public function getPlayerID(): ?int
    {
        return $this->playerData?->playerID;
    }

    /** @see PlayerInterface::getPlrRow() */
    public function getPlrRow(): ?array
    {
        return $this->playerData?->plr;
    }

    /** @see PlayerInterface::getOrdinal() */
    public function getOrdinal(): ?int
    {
        return $this->playerData?->ordinal;
    }

    /** @see PlayerInterface::getName() */
    public function getName(): ?string
    {
        return $this->playerData?->name;
    }

    /** @see PlayerInterface::getNickname() */
    public function getNickname(): ?string
    {
        return $this->playerData?->nickname;
    }

    /** @see PlayerInterface::getAge() */
    public function getAge(): ?int
    {
        return $this->playerData?->age;
    }

    /** @see PlayerInterface::getHistoricalYear() */
    public function getHistoricalYear(): ?int
    {
        return $this->playerData?->historicalYear;
    }

    /** @see PlayerInterface::getPosition() */
    public function getPosition(): ?string
    {
        return $this->playerData?->position;
    }

    /** @see PlayerInterface::getYearsOfExperience() */
    public function getYearsOfExperience(): ?int
    {
        return $this->playerData?->yearsOfExperience;
    }

    /** @see PlayerInterface::getDraftYear() */
    public function getDraftYear(): ?int
    {
        return $this->playerData?->draftYear;
    }

    /** @see PlayerInterface::getDraftRound() */
    public function getDraftRound(): ?int
    {
        return $this->playerData?->draftRound;
    }

    /** @see PlayerInterface::getDraftPickNumber() */
    public function getDraftPickNumber(): ?int
    {
        return $this->playerData?->draftPickNumber;
    }

    /** @see PlayerInterface::getDraftTeamOriginalName() */
    public function getDraftTeamOriginalName(): ?string
    {
        return $this->playerData?->draftTeamOriginalName;
    }

    /** @see PlayerInterface::getDraftTeamCurrentName() */
    public function getDraftTeamCurrentName(): ?string
    {
        return $this->playerData?->draftTeamCurrentName;
    }

    /** @see PlayerInterface::getCollegeName() */
    public function getCollegeName(): ?string
    {
        return $this->playerData?->collegeName;
    }

    /** @see PlayerInterface::getDaysRemainingForInjury() */
    public function getDaysRemainingForInjury(): ?int
    {
        return $this->playerData?->daysRemainingForInjury;
    }

    /** @see PlayerInterface::getHeightFeet() */
    public function getHeightFeet(): ?int
    {
        return $this->playerData?->heightFeet;
    }

    /** @see PlayerInterface::getHeightInches() */
    public function getHeightInches(): ?int
    {
        return $this->playerData?->heightInches;
    }

    /** @see PlayerInterface::getWeightPounds() */
    public function getWeightPounds(): ?int
    {
        return $this->playerData?->weightPounds;
    }

    /** @see PlayerInterface::getIsRetired() */
    public function getIsRetired(): ?int
    {
        return $this->playerData?->isRetired;
    }
}
