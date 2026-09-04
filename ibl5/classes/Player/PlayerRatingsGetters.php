<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerInterface;

/**
 * Pure field-accessor split of Player's rating getters.
 *
 * Single-consumer trait per ADR-0119: every method here is a pure read of
 * Player's own $playerData property. No state, no constructor, no collaborator
 * call — anything reaching $this->contractCalculator, $this->contractValidator,
 * $this->nameDecorator, $this->injuryCalculator or $this->repository stays in
 * Player.php.
 *
 * @see PlayerInterface
 */
trait PlayerRatingsGetters
{
    /** @see PlayerInterface::getRatingFieldGoalAttempts() */
    public function getRatingFieldGoalAttempts(): ?int
    {
        return $this->playerData?->ratingFieldGoalAttempts;
    }

    /** @see PlayerInterface::getRatingFieldGoalPercentage() */
    public function getRatingFieldGoalPercentage(): ?int
    {
        return $this->playerData?->ratingFieldGoalPercentage;
    }

    /** @see PlayerInterface::getRatingFreeThrowAttempts() */
    public function getRatingFreeThrowAttempts(): ?int
    {
        return $this->playerData?->ratingFreeThrowAttempts;
    }

    /** @see PlayerInterface::getRatingFreeThrowPercentage() */
    public function getRatingFreeThrowPercentage(): ?int
    {
        return $this->playerData?->ratingFreeThrowPercentage;
    }

    /** @see PlayerInterface::getRatingThreePointAttempts() */
    public function getRatingThreePointAttempts(): ?int
    {
        return $this->playerData?->ratingThreePointAttempts;
    }

    /** @see PlayerInterface::getRatingThreePointPercentage() */
    public function getRatingThreePointPercentage(): ?int
    {
        return $this->playerData?->ratingThreePointPercentage;
    }

    /** @see PlayerInterface::getRatingOffensiveRebounds() */
    public function getRatingOffensiveRebounds(): ?int
    {
        return $this->playerData?->ratingOffensiveRebounds;
    }

    /** @see PlayerInterface::getRatingDefensiveRebounds() */
    public function getRatingDefensiveRebounds(): ?int
    {
        return $this->playerData?->ratingDefensiveRebounds;
    }

    /** @see PlayerInterface::getRatingAssists() */
    public function getRatingAssists(): ?int
    {
        return $this->playerData?->ratingAssists;
    }

    /** @see PlayerInterface::getRatingSteals() */
    public function getRatingSteals(): ?int
    {
        return $this->playerData?->ratingSteals;
    }

    /** @see PlayerInterface::getRatingTurnovers() */
    public function getRatingTurnovers(): ?int
    {
        return $this->playerData?->ratingTurnovers;
    }

    /** @see PlayerInterface::getRatingBlocks() */
    public function getRatingBlocks(): ?int
    {
        return $this->playerData?->ratingBlocks;
    }

    /** @see PlayerInterface::getRatingFouls() */
    public function getRatingFouls(): ?int
    {
        return $this->playerData?->ratingFouls;
    }

    /** @see PlayerInterface::getRatingOutsideOffense() */
    public function getRatingOutsideOffense(): ?int
    {
        return $this->playerData?->ratingOutsideOffense;
    }

    /** @see PlayerInterface::getRatingOutsideDefense() */
    public function getRatingOutsideDefense(): ?int
    {
        return $this->playerData?->ratingOutsideDefense;
    }

    /** @see PlayerInterface::getRatingDriveOffense() */
    public function getRatingDriveOffense(): ?int
    {
        return $this->playerData?->ratingDriveOffense;
    }

    /** @see PlayerInterface::getRatingDriveDefense() */
    public function getRatingDriveDefense(): ?int
    {
        return $this->playerData?->ratingDriveDefense;
    }

    /** @see PlayerInterface::getRatingPostOffense() */
    public function getRatingPostOffense(): ?int
    {
        return $this->playerData?->ratingPostOffense;
    }

    /** @see PlayerInterface::getRatingPostDefense() */
    public function getRatingPostDefense(): ?int
    {
        return $this->playerData?->ratingPostDefense;
    }

    /** @see PlayerInterface::getRatingTransitionOffense() */
    public function getRatingTransitionOffense(): ?int
    {
        return $this->playerData?->ratingTransitionOffense;
    }

    /** @see PlayerInterface::getRatingTransitionDefense() */
    public function getRatingTransitionDefense(): ?int
    {
        return $this->playerData?->ratingTransitionDefense;
    }

    /** @see PlayerInterface::getRatingClutch() */
    public function getRatingClutch(): ?int
    {
        return $this->playerData?->ratingClutch;
    }

    /** @see PlayerInterface::getRatingConsistency() */
    public function getRatingConsistency(): ?int
    {
        return $this->playerData?->ratingConsistency;
    }

    /** @see PlayerInterface::getRatingTalent() */
    public function getRatingTalent(): ?int
    {
        return $this->playerData?->ratingTalent;
    }

    /** @see PlayerInterface::getRatingSkill() */
    public function getRatingSkill(): ?int
    {
        return $this->playerData?->ratingSkill;
    }

    /** @see PlayerInterface::getRatingIntangibles() */
    public function getRatingIntangibles(): ?int
    {
        return $this->playerData?->ratingIntangibles;
    }
}
