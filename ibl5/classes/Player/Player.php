<?php

declare(strict_types=1);

namespace Player;

use Player\Contract\PlayerContractCalculator;
use Player\Contract\PlayerContractValidator;
use Player\Contracts\PlayerInterface;
use Season\Season;

/**
 * Player - Facade for player-related operations
 *
 * This class provides a unified interface for accessing player data and
 * delegating to specialized calculator and validator classes for business logic.
 *
 * @see PlayerInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-import-type HistoricalPlayerRow from \Player\Contracts\PlayerRepositoryInterface
 */
class Player implements PlayerInterface
{
    /** @var \mysqli Database connection */
    protected \mysqli $db;

    /** @var PlayerData|null Player data object */
    protected ?PlayerData $playerData = null;

    /** @var PlayerRepository Player repository for database operations */
    protected PlayerRepository $repository;

    /** @var PlayerContractCalculator Contract calculation helper */
    protected PlayerContractCalculator $contractCalculator;

    /** @var PlayerContractValidator Contract validation helper */
    protected PlayerContractValidator $contractValidator;

    /** @var PlayerNameDecorator Name decoration helper */
    protected PlayerNameDecorator $nameDecorator;

    /** @var PlayerInjuryCalculator Injury calculation helper */
    protected PlayerInjuryCalculator $injuryCalculator;

    /**
     * Create a new Player instance
     */
    public function __construct()
    {
        $this->contractCalculator = new PlayerContractCalculator();
        $this->contractValidator = new PlayerContractValidator();
        $this->nameDecorator = new PlayerNameDecorator();
        $this->injuryCalculator = new PlayerInjuryCalculator();
    }

    /**
     * Create a Player instance from a player ID
     *
     * @param \mysqli $db Database connection
     * @param int $playerID Player unique identifier
     * @return self Populated Player instance
     */
    public static function withPlayerID(\mysqli $db, int $playerID): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->loadByID($playerID);
        return $instance;
    }

    /**
     * Create a Player instance from a player row array
     *
     * @param \mysqli $db Database connection
     * @param PlayerRow $plrRow Player row data from database
     * @return self Populated Player instance
     */
    public static function withPlrRow(\mysqli $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromCurrentRow($plrRow);
        return $instance;
    }

    /**
     * Create a Player instance from a historical player row
     *
     * @param \mysqli $db Database connection
     * @param HistoricalPlayerRow $plrRow Historical player row data
     * @return self Populated Player instance
     */
    public static function withHistoricalPlrRow(\mysqli $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromHistoricalRow($plrRow);
        return $instance;
    }

    /**
     * Initialize the Player instance with database and repository
     *
     * @param \mysqli $db Database connection
     */
    protected function initialize(\mysqli $db): void
    {
        $this->db = $db;
        $this->repository = new PlayerRepository($db);
    }

    /**
     * Get the PlayerData object, throwing if not initialized
     *
     * @throws \RuntimeException If player data has not been loaded
     */
    private function getPlayerData(): PlayerData
    {
        if ($this->playerData === null) {
            throw new \RuntimeException('Player data has not been loaded');
        }
        return $this->playerData;
    }

    // --- Typed getters delegating to PlayerData ---

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

    /** @see PlayerInterface::getTeamCity() */
    public function getTeamCity(): ?string
    {
        return null;
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

    /** @see PlayerInterface::getPosition() */
    public function getPosition(): ?string
    {
        return $this->playerData?->position;
    }

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

    /** @see PlayerInterface::getYearsOfExperience() */
    public function getYearsOfExperience(): ?int
    {
        return $this->playerData?->yearsOfExperience;
    }

    /** @see PlayerInterface::getBirdYears() */
    public function getBirdYears(): ?int
    {
        return $this->playerData?->birdYears;
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

    /** @see PlayerInterface::getTimeDroppedOnWaivers() */
    public function getTimeDroppedOnWaivers(): ?int
    {
        return $this->playerData?->timeDroppedOnWaivers;
    }

    /** @see PlayerInterface::getDecoratedName() */
    public function getDecoratedName(): ?string
    {
        return $this->playerData !== null ? $this->decoratePlayerName() : null;
    }

    /** @see PlayerInterface::getNameStatusClass() */
    public function getNameStatusClass(): string
    {
        return $this->playerData !== null
            ? $this->nameDecorator->getNameStatusClass($this->getPlayerData())
            : '';
    }

    /**
     * @see PlayerInterface::decoratePlayerName()
     */
    public function decoratePlayerName(): string
    {
        return $this->nameDecorator->decoratePlayerName($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getCurrentSeasonSalary()
     */
    public function getCurrentSeasonSalary(): int
    {
        return $this->contractCalculator->getCurrentSeasonSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getFreeAgencyDemands()
     *
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}
     */
    public function getFreeAgencyDemands(): array
    {
        return $this->repository->getFreeAgencyDemands($this->getPlayerID() ?? 0);
    }

    /**
     * @see PlayerInterface::getInjuryReturnDate()
     */
    public function getInjuryReturnDate(string $rawLastSimEndDate): string
    {
        return $this->injuryCalculator->getInjuryReturnDate($this->getPlayerData(), $rawLastSimEndDate);
    }

    /**
     * @see PlayerInterface::getNextSeasonSalary()
     */
    public function getNextSeasonSalary(): int
    {
        return $this->contractCalculator->getNextSeasonSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getLongBuyoutArray()
     */
    public function getLongBuyoutArray(): array
    {
        return $this->contractCalculator->getLongBuyoutArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getShortBuyoutArray()
     */
    public function getShortBuyoutArray(): array
    {
        return $this->contractCalculator->getShortBuyoutArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getRemainingContractArray()
     *
     * @return array<int, int>
     */
    public function getRemainingContractArray(): array
    {
        return $this->contractCalculator->getRemainingContractArray($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getTotalRemainingSalary()
     */
    public function getTotalRemainingSalary(): int
    {
        return $this->contractCalculator->getTotalRemainingSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::getFutureSalaries()
     */
    public function getFutureSalaries(): array
    {
        return $this->contractCalculator->getFutureSalaries($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::canRenegotiateContract()
     */
    public function canRenegotiateContract(?Season $season = null): bool
    {
        return $this->contractValidator->canRenegotiateContract($this->getPlayerData(), $season);
    }

    /**
     * @see PlayerInterface::canRookieOption()
     */
    public function canRookieOption(string $seasonPhase): bool
    {
        return $this->contractValidator->canRookieOption($this->getPlayerData(), $seasonPhase);
    }

    /**
     * @see PlayerInterface::getFinalYearRookieContractSalary()
     */
    public function getFinalYearRookieContractSalary(): int
    {
        return $this->contractValidator->getFinalYearRookieContractSalary($this->getPlayerData());
    }

    /**
     * @see PlayerInterface::isPlayerFreeAgent()
     */
    public function isPlayerFreeAgent(Season $season): bool
    {
        return $this->contractValidator->isPlayerFreeAgent($this->getPlayerData(), $season);
    }

    /**
     * @see PlayerInterface::isSalaryPlaceholder()
     */
    public function isSalaryPlaceholder(): bool
    {
        // Cash and buyout entries are no longer stored in ibl_plr.
        // They live in ibl_cash_considerations. No player loaded from
        // ibl_plr can be a salary placeholder.
        return false;
    }

    /**
     * @see PlayerInterface::wasRookieOptioned()
     */
    public function wasRookieOptioned(): bool
    {
        return $this->contractValidator->wasRookieOptioned($this->getPlayerData());
    }
}
