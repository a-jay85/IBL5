<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerInterface;

/**
 * Player - Facade for player-related operations
 *
 * This class provides a unified interface for accessing player data and
 * delegating to specialized calculator and validator classes for business logic.
 *
 * @see PlayerInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class Player implements PlayerInterface
{
    /** @var object Database connection */
    protected object $db;

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

    // Keep all public properties for backward compatibility
    /** @var int|null Player unique identifier */
    public ?int $playerID = null;

    /** @var array<string, mixed>|null Raw player row data */
    public ?array $plr = null;

    /** @var int|null Player ordinal position */
    public ?int $ordinal = null;

    /** @var string|null Player full name */
    public ?string $name = null;

    /** @var string|null Player nickname */
    public ?string $nickname = null;

    /** @var int|null Player age in years */
    public ?int $age = null;

    /** @var int|null Historical year for retired/historical players */
    public ?int $historicalYear = null;

    /** @var int|null Team ID (0 for free agents) */
    public ?int $teamID = null;

    /** @var string|null Team name */
    public ?string $teamName = null;

    /** @var string|null Team city */
    public ?string $teamCity = null;

    /** @var string|null Team primary color (hex without #) */
    public ?string $teamColor1 = null;

    /** @var string|null Team secondary color (hex without #) */
    public ?string $teamColor2 = null;

    /** @var string|null Player position (PG, SG, SF, PF, C) */
    public ?string $position = null;

    /** @var int|null Rating: Field goal attempts tendency (1-5) */
    public ?int $ratingFieldGoalAttempts = null;

    /** @var int|null Rating: Field goal percentage (1-5) */
    public ?int $ratingFieldGoalPercentage = null;

    /** @var int|null Rating: Free throw attempts tendency (1-5) */
    public ?int $ratingFreeThrowAttempts = null;

    /** @var int|null Rating: Free throw percentage (1-5) */
    public ?int $ratingFreeThrowPercentage = null;

    /** @var int|null Rating: Three point attempts tendency (1-5) */
    public ?int $ratingThreePointAttempts = null;

    /** @var int|null Rating: Three point percentage (1-5) */
    public ?int $ratingThreePointPercentage = null;

    /** @var int|null Rating: Offensive rebounds (1-5) */
    public ?int $ratingOffensiveRebounds = null;

    /** @var int|null Rating: Defensive rebounds (1-5) */
    public ?int $ratingDefensiveRebounds = null;

    /** @var int|null Rating: Assists (1-5) */
    public ?int $ratingAssists = null;

    /** @var int|null Rating: Steals (1-5) */
    public ?int $ratingSteals = null;

    /** @var int|null Rating: Turnovers (1-5, higher = more turnovers) */
    public ?int $ratingTurnovers = null;

    /** @var int|null Rating: Blocks (1-5) */
    public ?int $ratingBlocks = null;

    /** @var int|null Rating: Fouls (1-5, higher = more fouls) */
    public ?int $ratingFouls = null;

    /** @var int|null Rating: Outside offense (1-5) */
    public ?int $ratingOutsideOffense = null;

    /** @var int|null Rating: Outside defense (1-5) */
    public ?int $ratingOutsideDefense = null;

    /** @var int|null Rating: Drive offense (1-5) */
    public ?int $ratingDriveOffense = null;

    /** @var int|null Rating: Drive defense (1-5) */
    public ?int $ratingDriveDefense = null;

    /** @var int|null Rating: Post offense (1-5) */
    public ?int $ratingPostOffense = null;

    /** @var int|null Rating: Post defense (1-5) */
    public ?int $ratingPostDefense = null;

    /** @var int|null Rating: Transition offense (1-5) */
    public ?int $ratingTransitionOffense = null;

    /** @var int|null Rating: Transition defense (1-5) */
    public ?int $ratingTransitionDefense = null;

    /** @var int|null Rating: Clutch performance (1-5) */
    public ?int $ratingClutch = null;

    /** @var int|null Rating: Consistency (1-5) */
    public ?int $ratingConsistency = null;

    /** @var int|null Rating: Overall talent (1-5) */
    public ?int $ratingTalent = null;

    /** @var int|null Rating: Skill level (1-5) */
    public ?int $ratingSkill = null;

    /** @var int|null Rating: Intangibles (1-5) */
    public ?int $ratingIntangibles = null;

    /** @var int|null Free agency: Loyalty factor (1-5) */
    public ?int $freeAgencyLoyalty = null;

    /** @var int|null Free agency: Playing time preference (1-5) */
    public ?int $freeAgencyPlayingTime = null;

    /** @var int|null Free agency: Play for winner preference (1-5) */
    public ?int $freeAgencyPlayForWinner = null;

    /** @var int|null Free agency: Team tradition preference (1-5) */
    public ?int $freeAgencyTradition = null;

    /** @var int|null Free agency: Job security preference (1-5) */
    public ?int $freeAgencySecurity = null;

    /** @var int|null Years of professional experience */
    public ?int $yearsOfExperience = null;

    /** @var int|null Bird rights years accumulated */
    public ?int $birdYears = null;

    /** @var int|null Current year of contract (1-6) */
    public ?int $contractCurrentYear = null;

    /** @var int|null Total years in contract */
    public ?int $contractTotalYears = null;

    /** @var int|null Contract year 1 salary (in thousands) */
    public ?int $contractYear1Salary = null;

    /** @var int|null Contract year 2 salary (in thousands) */
    public ?int $contractYear2Salary = null;

    /** @var int|null Contract year 3 salary (in thousands) */
    public ?int $contractYear3Salary = null;

    /** @var int|null Contract year 4 salary (in thousands) */
    public ?int $contractYear4Salary = null;

    /** @var int|null Contract year 5 salary (in thousands) */
    public ?int $contractYear5Salary = null;

    /** @var int|null Contract year 6 salary (in thousands) */
    public ?int $contractYear6Salary = null;

    /** @var int|null Current season salary (calculated) */
    public ?int $currentSeasonSalary = null;

    /** @var int|null JSB simulation salary value */
    public ?int $salaryJSB = null;

    /** @var int|null Year player was drafted */
    public ?int $draftYear = null;

    /** @var int|null Draft round (1 or 2, 0 if undrafted) */
    public ?int $draftRound = null;

    /** @var int|null Overall draft pick number */
    public ?int $draftPickNumber = null;

    /** @var string|null Original team name that drafted player */
    public ?string $draftTeamOriginalName = null;

    /** @var string|null Current name of team that drafted player */
    public ?string $draftTeamCurrentName = null;

    /** @var string|null College/university name */
    public ?string $collegeName = null;

    /** @var int|null Days remaining on injury (0 = healthy) */
    public ?int $daysRemainingForInjury = null;

    /** @var int|null Height in feet */
    public ?int $heightFeet = null;

    /** @var int|null Height in inches (additional) */
    public ?int $heightInches = null;

    /** @var int|null Weight in pounds */
    public ?int $weightPounds = null;

    /** @var int|null Is player retired (0 or 1) */
    public ?int $isRetired = null;

    /** @var int|null Unix timestamp when dropped on waivers */
    public ?int $timeDroppedOnWaivers = null;

    /** @var string|null Decorated name with status indicators */
    public ?string $decoratedName = null;

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
     * @param object $db Database connection
     * @param int $playerID Player unique identifier
     * @return self Populated Player instance
     */
    public static function withPlayerID(object $db, int $playerID): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->loadByID($playerID);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    /**
     * Create a Player instance from a player row array
     *
     * @param object $db Database connection
     * @param PlayerRow $plrRow Player row data from database
     * @return self Populated Player instance
     */
    public static function withPlrRow(object $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromCurrentRow($plrRow);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    /**
     * Create a Player instance from a historical player row
     *
     * @param object $db Database connection
     * @param array<string, mixed> $plrRow Historical player row data
     * @return self Populated Player instance
     */
    public static function withHistoricalPlrRow(object $db, array $plrRow): self
    {
        $instance = new self();
        $instance->initialize($db);
        /** @phpstan-ignore argument.type (HistoricalRow from SELECT * has all required fields) */
        $instance->playerData = $instance->repository->fillFromHistoricalRow($plrRow);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    /**
     * Initialize the Player instance with database and repository
     *
     * @param object $db Database connection
     * @throws \RuntimeException If no mysqli connection is available
     */
    protected function initialize(object $db): void
    {
        $this->db = $db;

        // Use mysqli connection for PlayerRepository
        // If $db is mysqli, use it; otherwise get global $mysqli_db
        // In tests, global $mysqli_db should be a proper mysqli mock
        if ($db instanceof \mysqli) {
            $this->repository = new PlayerRepository($db);
        } else {
            // Legacy path: try global mysqli_db
            global $mysqli_db;
            if ($mysqli_db instanceof \mysqli) {
                $this->repository = new PlayerRepository($mysqli_db);
            } elseif (is_object($mysqli_db)) {
                // Test environment: global $mysqli_db exists but is a mock duck-type
                // Temporarily accept duck-typed objects during mysqli migration
                $this->repository = new PlayerRepository($mysqli_db);
            } else {
                // No mysqli connection available
                throw new \RuntimeException('PlayerRepository requires a mysqli connection. Please set up global $mysqli_db in tests.');
            }
        }
    }

    /**
     * Sync all public properties from PlayerData for backward compatibility
     */
    protected function syncPropertiesFromPlayerData(): void
    {
        if ($this->playerData === null) {
            return;
        }

        $this->playerID = $this->playerData->playerID;
        $this->ordinal = $this->playerData->ordinal;
        $this->name = $this->playerData->name;
        $this->nickname = $this->playerData->nickname;
        $this->age = $this->playerData->age;
        $this->historicalYear = $this->playerData->historicalYear;

        $this->teamID = $this->playerData->teamID;
        $this->teamName = $this->playerData->teamName;
        $this->position = $this->playerData->position;

        $this->ratingFieldGoalAttempts = $this->playerData->ratingFieldGoalAttempts;
        $this->ratingFieldGoalPercentage = $this->playerData->ratingFieldGoalPercentage;
        $this->ratingFreeThrowAttempts = $this->playerData->ratingFreeThrowAttempts;
        $this->ratingFreeThrowPercentage = $this->playerData->ratingFreeThrowPercentage;
        $this->ratingThreePointAttempts = $this->playerData->ratingThreePointAttempts;
        $this->ratingThreePointPercentage = $this->playerData->ratingThreePointPercentage;
        $this->ratingOffensiveRebounds = $this->playerData->ratingOffensiveRebounds;
        $this->ratingDefensiveRebounds = $this->playerData->ratingDefensiveRebounds;
        $this->ratingAssists = $this->playerData->ratingAssists;
        $this->ratingSteals = $this->playerData->ratingSteals;
        $this->ratingTurnovers = $this->playerData->ratingTurnovers;
        $this->ratingBlocks = $this->playerData->ratingBlocks;
        $this->ratingFouls = $this->playerData->ratingFouls;
        $this->ratingOutsideOffense = $this->playerData->ratingOutsideOffense;
        $this->ratingOutsideDefense = $this->playerData->ratingOutsideDefense;
        $this->ratingDriveOffense = $this->playerData->ratingDriveOffense;
        $this->ratingDriveDefense = $this->playerData->ratingDriveDefense;
        $this->ratingPostOffense = $this->playerData->ratingPostOffense;
        $this->ratingPostDefense = $this->playerData->ratingPostDefense;
        $this->ratingTransitionOffense = $this->playerData->ratingTransitionOffense;
        $this->ratingTransitionDefense = $this->playerData->ratingTransitionDefense;
        $this->ratingClutch = $this->playerData->ratingClutch;
        $this->ratingConsistency = $this->playerData->ratingConsistency;
        $this->ratingTalent = $this->playerData->ratingTalent;
        $this->ratingSkill = $this->playerData->ratingSkill;
        $this->ratingIntangibles = $this->playerData->ratingIntangibles;

        $this->freeAgencyLoyalty = $this->playerData->freeAgencyLoyalty;
        $this->freeAgencyPlayingTime = $this->playerData->freeAgencyPlayingTime;
        $this->freeAgencyPlayForWinner = $this->playerData->freeAgencyPlayForWinner;
        $this->freeAgencyTradition = $this->playerData->freeAgencyTradition;
        $this->freeAgencySecurity = $this->playerData->freeAgencySecurity;

        $this->yearsOfExperience = $this->playerData->yearsOfExperience;
        $this->birdYears = $this->playerData->birdYears;
        $this->contractCurrentYear = $this->playerData->contractCurrentYear;
        $this->contractTotalYears = $this->playerData->contractTotalYears;
        $this->contractYear1Salary = $this->playerData->contractYear1Salary;
        $this->contractYear2Salary = $this->playerData->contractYear2Salary;
        $this->contractYear3Salary = $this->playerData->contractYear3Salary;
        $this->contractYear4Salary = $this->playerData->contractYear4Salary;
        $this->contractYear5Salary = $this->playerData->contractYear5Salary;
        $this->contractYear6Salary = $this->playerData->contractYear6Salary;
        $this->currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($this->playerData);
        $this->salaryJSB = $this->playerData->salaryJSB;

        $this->draftYear = $this->playerData->draftYear;
        $this->draftRound = $this->playerData->draftRound;
        $this->draftPickNumber = $this->playerData->draftPickNumber;
        $this->draftTeamOriginalName = $this->playerData->draftTeamOriginalName;
        $this->draftTeamCurrentName = $this->playerData->draftTeamCurrentName;
        $this->collegeName = $this->playerData->collegeName;

        $this->daysRemainingForInjury = $this->playerData->daysRemainingForInjury;

        $this->heightFeet = $this->playerData->heightFeet;
        $this->heightInches = $this->playerData->heightInches;
        $this->weightPounds = $this->playerData->weightPounds;

        $this->isRetired = $this->playerData->isRetired;

        $this->timeDroppedOnWaivers = $this->playerData->timeDroppedOnWaivers;

        $this->decoratedName = $this->decoratePlayerName();
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
        return $this->repository->getFreeAgencyDemands($this->name ?? '');
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
    public function canRenegotiateContract(): bool
    {
        return $this->contractValidator->canRenegotiateContract($this->getPlayerData());
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
     *
     * @param int|\Season $season Season object or ending year to check
     */
    public function isPlayerFreeAgent(int|\Season $season): bool
    {
        if ($season instanceof \Season) {
            return $this->contractValidator->isPlayerFreeAgent($this->getPlayerData(), $season);
        }

        // For int argument, calculate directly: int represents the ending year
        $playerData = $this->getPlayerData();
        $yearPlayerIsFreeAgent = ($playerData->draftYear ?? 0)
            + ($playerData->yearsOfExperience ?? 0)
            + ($playerData->contractTotalYears ?? 0)
            - ($playerData->contractCurrentYear ?? 0);

        return $yearPlayerIsFreeAgent === $season;
    }

    /**
     * @see PlayerInterface::wasRookieOptioned()
     */
    public function wasRookieOptioned(): bool
    {
        return $this->contractValidator->wasRookieOptioned($this->getPlayerData());
    }
}
