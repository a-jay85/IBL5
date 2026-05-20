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

    // Public properties — deprecated. Use typed getters instead.

    /**
     * @var int|null Player unique identifier
     * @deprecated Use $player->getPlayerID() instead.
     */
    public ?int $playerID = null;

    /**
     * @var array<string, mixed>|null Raw player row data
     * @deprecated Use $player->getPlrRow() instead.
     */
    public ?array $plr = null;

    /**
     * @var int|null Player ordinal position
     * @deprecated Use $player->getOrdinal() instead.
     */
    public ?int $ordinal = null;

    /**
     * @var string|null Player full name
     * @deprecated Use $player->getName() instead.
     */
    public ?string $name = null;

    /**
     * @var string|null Player nickname
     * @deprecated Use $player->getNickname() instead.
     */
    public ?string $nickname = null;

    /**
     * @var int|null Player age in years
     * @deprecated Use $player->getAge() instead.
     */
    public ?int $age = null;

    /**
     * @var int|null Historical year for retired/historical players
     * @deprecated Use $player->getHistoricalYear() instead.
     */
    public ?int $historicalYear = null;

    /**
     * @var int|null Team ID (0 for free agents)
     * @deprecated Use $player->getTeamid() instead.
     */
    public ?int $teamid = null;

    /**
     * @var string|null Team name
     * @deprecated Use $player->getTeamName() instead.
     */
    public ?string $teamName = null;

    /**
     * @var string|null Team city
     * @deprecated Use $player->getTeamCity() instead.
     */
    public ?string $teamCity = null;

    /**
     * @var string|null Team primary color (hex without #)
     * @deprecated Use $player->getTeamColor1() instead.
     */
    public ?string $teamColor1 = null;

    /**
     * @var string|null Team secondary color (hex without #)
     * @deprecated Use $player->getTeamColor2() instead.
     */
    public ?string $teamColor2 = null;

    /**
     * @var string|null Player position (PG, SG, SF, PF, C)
     * @deprecated Use $player->getPosition() instead.
     */
    public ?string $position = null;

    /**
     * @var int|null Rating: Field goal attempts tendency (1-5)
     * @deprecated Use $player->getRatingFieldGoalAttempts() instead.
     */
    public ?int $ratingFieldGoalAttempts = null;

    /**
     * @var int|null Rating: Field goal percentage (1-5)
     * @deprecated Use $player->getRatingFieldGoalPercentage() instead.
     */
    public ?int $ratingFieldGoalPercentage = null;

    /**
     * @var int|null Rating: Free throw attempts tendency (1-5)
     * @deprecated Use $player->getRatingFreeThrowAttempts() instead.
     */
    public ?int $ratingFreeThrowAttempts = null;

    /**
     * @var int|null Rating: Free throw percentage (1-5)
     * @deprecated Use $player->getRatingFreeThrowPercentage() instead.
     */
    public ?int $ratingFreeThrowPercentage = null;

    /**
     * @var int|null Rating: Three point attempts tendency (1-5)
     * @deprecated Use $player->getRatingThreePointAttempts() instead.
     */
    public ?int $ratingThreePointAttempts = null;

    /**
     * @var int|null Rating: Three point percentage (1-5)
     * @deprecated Use $player->getRatingThreePointPercentage() instead.
     */
    public ?int $ratingThreePointPercentage = null;

    /**
     * @var int|null Rating: Offensive rebounds (1-5)
     * @deprecated Use $player->getRatingOffensiveRebounds() instead.
     */
    public ?int $ratingOffensiveRebounds = null;

    /**
     * @var int|null Rating: Defensive rebounds (1-5)
     * @deprecated Use $player->getRatingDefensiveRebounds() instead.
     */
    public ?int $ratingDefensiveRebounds = null;

    /**
     * @var int|null Rating: Assists (1-5)
     * @deprecated Use $player->getRatingAssists() instead.
     */
    public ?int $ratingAssists = null;

    /**
     * @var int|null Rating: Steals (1-5)
     * @deprecated Use $player->getRatingSteals() instead.
     */
    public ?int $ratingSteals = null;

    /**
     * @var int|null Rating: Turnovers (1-5, higher = more turnovers)
     * @deprecated Use $player->getRatingTurnovers() instead.
     */
    public ?int $ratingTurnovers = null;

    /**
     * @var int|null Rating: Blocks (1-5)
     * @deprecated Use $player->getRatingBlocks() instead.
     */
    public ?int $ratingBlocks = null;

    /**
     * @var int|null Rating: Fouls (1-5, higher = more fouls)
     * @deprecated Use $player->getRatingFouls() instead.
     */
    public ?int $ratingFouls = null;

    /**
     * @var int|null Rating: Outside offense (1-5)
     * @deprecated Use $player->getRatingOutsideOffense() instead.
     */
    public ?int $ratingOutsideOffense = null;

    /**
     * @var int|null Rating: Outside defense (1-5)
     * @deprecated Use $player->getRatingOutsideDefense() instead.
     */
    public ?int $ratingOutsideDefense = null;

    /**
     * @var int|null Rating: Drive offense (1-5)
     * @deprecated Use $player->getRatingDriveOffense() instead.
     */
    public ?int $ratingDriveOffense = null;

    /**
     * @var int|null Rating: Drive defense (1-5)
     * @deprecated Use $player->getRatingDriveDefense() instead.
     */
    public ?int $ratingDriveDefense = null;

    /**
     * @var int|null Rating: Post offense (1-5)
     * @deprecated Use $player->getRatingPostOffense() instead.
     */
    public ?int $ratingPostOffense = null;

    /**
     * @var int|null Rating: Post defense (1-5)
     * @deprecated Use $player->getRatingPostDefense() instead.
     */
    public ?int $ratingPostDefense = null;

    /**
     * @var int|null Rating: Transition offense (1-5)
     * @deprecated Use $player->getRatingTransitionOffense() instead.
     */
    public ?int $ratingTransitionOffense = null;

    /**
     * @var int|null Rating: Transition defense (1-5)
     * @deprecated Use $player->getRatingTransitionDefense() instead.
     */
    public ?int $ratingTransitionDefense = null;

    /**
     * @var int|null Rating: Clutch performance (1-5)
     * @deprecated Use $player->getRatingClutch() instead.
     */
    public ?int $ratingClutch = null;

    /**
     * @var int|null Rating: Consistency (1-5)
     * @deprecated Use $player->getRatingConsistency() instead.
     */
    public ?int $ratingConsistency = null;

    /**
     * @var int|null Rating: Overall talent (1-5)
     * @deprecated Use $player->getRatingTalent() instead.
     */
    public ?int $ratingTalent = null;

    /**
     * @var int|null Rating: Skill level (1-5)
     * @deprecated Use $player->getRatingSkill() instead.
     */
    public ?int $ratingSkill = null;

    /**
     * @var int|null Rating: Intangibles (1-5)
     * @deprecated Use $player->getRatingIntangibles() instead.
     */
    public ?int $ratingIntangibles = null;

    /**
     * @var int|null Free agency: Loyalty factor (1-5)
     * @deprecated Use $player->getFreeAgencyLoyalty() instead.
     */
    public ?int $freeAgencyLoyalty = null;

    /**
     * @var int|null Free agency: Playing time preference (1-5)
     * @deprecated Use $player->getFreeAgencyPlayingTime() instead.
     */
    public ?int $freeAgencyPlayingTime = null;

    /**
     * @var int|null Free agency: Play for winner preference (1-5)
     * @deprecated Use $player->getFreeAgencyPlayForWinner() instead.
     */
    public ?int $freeAgencyPlayForWinner = null;

    /**
     * @var int|null Free agency: Team tradition preference (1-5)
     * @deprecated Use $player->getFreeAgencyTradition() instead.
     */
    public ?int $freeAgencyTradition = null;

    /**
     * @var int|null Free agency: Job security preference (1-5)
     * @deprecated Use $player->getFreeAgencySecurity() instead.
     */
    public ?int $freeAgencySecurity = null;

    /**
     * @var int|null Years of professional experience
     * @deprecated Use $player->getYearsOfExperience() instead.
     */
    public ?int $yearsOfExperience = null;

    /**
     * @var int|null Bird rights years accumulated
     * @deprecated Use $player->getBirdYears() instead.
     */
    public ?int $birdYears = null;

    /**
     * @var int|null Current year of contract (1-6)
     * @deprecated Use $player->getContractCurrentYear() instead.
     */
    public ?int $contractCurrentYear = null;

    /**
     * @var int|null Total years in contract
     * @deprecated Use $player->getContractTotalYears() instead.
     */
    public ?int $contractTotalYears = null;

    /**
     * @var int|null Contract year 1 salary (in thousands)
     * @deprecated Use $player->getContractYear1Salary() instead.
     */
    public ?int $contractYear1Salary = null;

    /**
     * @var int|null Contract year 2 salary (in thousands)
     * @deprecated Use $player->getContractYear2Salary() instead.
     */
    public ?int $contractYear2Salary = null;

    /**
     * @var int|null Contract year 3 salary (in thousands)
     * @deprecated Use $player->getContractYear3Salary() instead.
     */
    public ?int $contractYear3Salary = null;

    /**
     * @var int|null Contract year 4 salary (in thousands)
     * @deprecated Use $player->getContractYear4Salary() instead.
     */
    public ?int $contractYear4Salary = null;

    /**
     * @var int|null Contract year 5 salary (in thousands)
     * @deprecated Use $player->getContractYear5Salary() instead.
     */
    public ?int $contractYear5Salary = null;

    /**
     * @var int|null Contract year 6 salary (in thousands)
     * @deprecated Use $player->getContractYear6Salary() instead.
     */
    public ?int $contractYear6Salary = null;

    /**
     * @var int|null Current season salary (calculated)
     * @deprecated Use $player->getCurrentSeasonSalary() instead.
     */
    public ?int $currentSeasonSalary = null;

    /**
     * @var int|null JSB simulation salary value
     * @deprecated Use $player->getSalaryJSB() instead.
     */
    public ?int $salaryJSB = null;

    /**
     * @var int|null Year player was drafted
     * @deprecated Use $player->getDraftYear() instead.
     */
    public ?int $draftYear = null;

    /**
     * @var int|null Draft round (1 or 2, 0 if undrafted)
     * @deprecated Use $player->getDraftRound() instead.
     */
    public ?int $draftRound = null;

    /**
     * @var int|null Overall draft pick number
     * @deprecated Use $player->getDraftPickNumber() instead.
     */
    public ?int $draftPickNumber = null;

    /**
     * @var string|null Original team name that drafted player
     * @deprecated Use $player->getDraftTeamOriginalName() instead.
     */
    public ?string $draftTeamOriginalName = null;

    /**
     * @var string|null Current name of team that drafted player
     * @deprecated Use $player->getDraftTeamCurrentName() instead.
     */
    public ?string $draftTeamCurrentName = null;

    /**
     * @var string|null College/university name
     * @deprecated Use $player->getCollegeName() instead.
     */
    public ?string $collegeName = null;

    /**
     * @var int|null Days remaining on injury (0 = healthy)
     * @deprecated Use $player->getDaysRemainingForInjury() instead.
     */
    public ?int $daysRemainingForInjury = null;

    /**
     * @var int|null Height in feet
     * @deprecated Use $player->getHeightFeet() instead.
     */
    public ?int $heightFeet = null;

    /**
     * @var int|null Height in inches (additional)
     * @deprecated Use $player->getHeightInches() instead.
     */
    public ?int $heightInches = null;

    /**
     * @var int|null Weight in pounds
     * @deprecated Use $player->getWeightPounds() instead.
     */
    public ?int $weightPounds = null;

    /**
     * @var int|null Is player retired (0 or 1)
     * @deprecated Use $player->getIsRetired() instead.
     */
    public ?int $isRetired = null;

    /**
     * @var int|null Unix timestamp when dropped on waivers
     * @deprecated Use $player->getTimeDroppedOnWaivers() instead.
     */
    public ?int $timeDroppedOnWaivers = null;

    /**
     * @var string|null Decorated name with status indicators
     * @deprecated Use $player->getDecoratedName() instead.
     */
    public ?string $decoratedName = null;

    /**
     * @var string CSS class for player name status indicator (waived/expiring)
     * @deprecated Use $player->getNameStatusClass() instead.
     */
    public string $nameStatusClass = '';

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
        $instance->syncPropertiesFromPlayerData();
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
        $instance->syncPropertiesFromPlayerData();
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
        $instance->syncPropertiesFromPlayerData();
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

        $this->teamid = $this->playerData->teamid;
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
        $this->nameStatusClass = $this->nameDecorator->getNameStatusClass($this->getPlayerData());
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
        return $this->getPlayerData()->playerID;
    }

    /** @see PlayerInterface::getPlrRow() */
    public function getPlrRow(): ?array
    {
        return $this->playerData?->plr;
    }

    /** @see PlayerInterface::getOrdinal() */
    public function getOrdinal(): ?int
    {
        return $this->getPlayerData()->ordinal;
    }

    /** @see PlayerInterface::getName() */
    public function getName(): ?string
    {
        return $this->getPlayerData()->name;
    }

    /** @see PlayerInterface::getNickname() */
    public function getNickname(): ?string
    {
        return $this->getPlayerData()->nickname;
    }

    /** @see PlayerInterface::getAge() */
    public function getAge(): ?int
    {
        return $this->getPlayerData()->age;
    }

    /** @see PlayerInterface::getHistoricalYear() */
    public function getHistoricalYear(): ?int
    {
        return $this->getPlayerData()->historicalYear;
    }

    /** @see PlayerInterface::getTeamid() */
    public function getTeamid(): ?int
    {
        return $this->getPlayerData()->teamid;
    }

    /** @see PlayerInterface::getTeamName() */
    public function getTeamName(): ?string
    {
        return $this->getPlayerData()->teamName;
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
        return $this->getPlayerData()->position;
    }

    /** @see PlayerInterface::getRatingFieldGoalAttempts() */
    public function getRatingFieldGoalAttempts(): ?int
    {
        return $this->getPlayerData()->ratingFieldGoalAttempts;
    }

    /** @see PlayerInterface::getRatingFieldGoalPercentage() */
    public function getRatingFieldGoalPercentage(): ?int
    {
        return $this->getPlayerData()->ratingFieldGoalPercentage;
    }

    /** @see PlayerInterface::getRatingFreeThrowAttempts() */
    public function getRatingFreeThrowAttempts(): ?int
    {
        return $this->getPlayerData()->ratingFreeThrowAttempts;
    }

    /** @see PlayerInterface::getRatingFreeThrowPercentage() */
    public function getRatingFreeThrowPercentage(): ?int
    {
        return $this->getPlayerData()->ratingFreeThrowPercentage;
    }

    /** @see PlayerInterface::getRatingThreePointAttempts() */
    public function getRatingThreePointAttempts(): ?int
    {
        return $this->getPlayerData()->ratingThreePointAttempts;
    }

    /** @see PlayerInterface::getRatingThreePointPercentage() */
    public function getRatingThreePointPercentage(): ?int
    {
        return $this->getPlayerData()->ratingThreePointPercentage;
    }

    /** @see PlayerInterface::getRatingOffensiveRebounds() */
    public function getRatingOffensiveRebounds(): ?int
    {
        return $this->getPlayerData()->ratingOffensiveRebounds;
    }

    /** @see PlayerInterface::getRatingDefensiveRebounds() */
    public function getRatingDefensiveRebounds(): ?int
    {
        return $this->getPlayerData()->ratingDefensiveRebounds;
    }

    /** @see PlayerInterface::getRatingAssists() */
    public function getRatingAssists(): ?int
    {
        return $this->getPlayerData()->ratingAssists;
    }

    /** @see PlayerInterface::getRatingSteals() */
    public function getRatingSteals(): ?int
    {
        return $this->getPlayerData()->ratingSteals;
    }

    /** @see PlayerInterface::getRatingTurnovers() */
    public function getRatingTurnovers(): ?int
    {
        return $this->getPlayerData()->ratingTurnovers;
    }

    /** @see PlayerInterface::getRatingBlocks() */
    public function getRatingBlocks(): ?int
    {
        return $this->getPlayerData()->ratingBlocks;
    }

    /** @see PlayerInterface::getRatingFouls() */
    public function getRatingFouls(): ?int
    {
        return $this->getPlayerData()->ratingFouls;
    }

    /** @see PlayerInterface::getRatingOutsideOffense() */
    public function getRatingOutsideOffense(): ?int
    {
        return $this->getPlayerData()->ratingOutsideOffense;
    }

    /** @see PlayerInterface::getRatingOutsideDefense() */
    public function getRatingOutsideDefense(): ?int
    {
        return $this->getPlayerData()->ratingOutsideDefense;
    }

    /** @see PlayerInterface::getRatingDriveOffense() */
    public function getRatingDriveOffense(): ?int
    {
        return $this->getPlayerData()->ratingDriveOffense;
    }

    /** @see PlayerInterface::getRatingDriveDefense() */
    public function getRatingDriveDefense(): ?int
    {
        return $this->getPlayerData()->ratingDriveDefense;
    }

    /** @see PlayerInterface::getRatingPostOffense() */
    public function getRatingPostOffense(): ?int
    {
        return $this->getPlayerData()->ratingPostOffense;
    }

    /** @see PlayerInterface::getRatingPostDefense() */
    public function getRatingPostDefense(): ?int
    {
        return $this->getPlayerData()->ratingPostDefense;
    }

    /** @see PlayerInterface::getRatingTransitionOffense() */
    public function getRatingTransitionOffense(): ?int
    {
        return $this->getPlayerData()->ratingTransitionOffense;
    }

    /** @see PlayerInterface::getRatingTransitionDefense() */
    public function getRatingTransitionDefense(): ?int
    {
        return $this->getPlayerData()->ratingTransitionDefense;
    }

    /** @see PlayerInterface::getRatingClutch() */
    public function getRatingClutch(): ?int
    {
        return $this->getPlayerData()->ratingClutch;
    }

    /** @see PlayerInterface::getRatingConsistency() */
    public function getRatingConsistency(): ?int
    {
        return $this->getPlayerData()->ratingConsistency;
    }

    /** @see PlayerInterface::getRatingTalent() */
    public function getRatingTalent(): ?int
    {
        return $this->getPlayerData()->ratingTalent;
    }

    /** @see PlayerInterface::getRatingSkill() */
    public function getRatingSkill(): ?int
    {
        return $this->getPlayerData()->ratingSkill;
    }

    /** @see PlayerInterface::getRatingIntangibles() */
    public function getRatingIntangibles(): ?int
    {
        return $this->getPlayerData()->ratingIntangibles;
    }

    /** @see PlayerInterface::getFreeAgencyLoyalty() */
    public function getFreeAgencyLoyalty(): ?int
    {
        return $this->getPlayerData()->freeAgencyLoyalty;
    }

    /** @see PlayerInterface::getFreeAgencyPlayingTime() */
    public function getFreeAgencyPlayingTime(): ?int
    {
        return $this->getPlayerData()->freeAgencyPlayingTime;
    }

    /** @see PlayerInterface::getFreeAgencyPlayForWinner() */
    public function getFreeAgencyPlayForWinner(): ?int
    {
        return $this->getPlayerData()->freeAgencyPlayForWinner;
    }

    /** @see PlayerInterface::getFreeAgencyTradition() */
    public function getFreeAgencyTradition(): ?int
    {
        return $this->getPlayerData()->freeAgencyTradition;
    }

    /** @see PlayerInterface::getFreeAgencySecurity() */
    public function getFreeAgencySecurity(): ?int
    {
        return $this->getPlayerData()->freeAgencySecurity;
    }

    /** @see PlayerInterface::getYearsOfExperience() */
    public function getYearsOfExperience(): ?int
    {
        return $this->getPlayerData()->yearsOfExperience;
    }

    /** @see PlayerInterface::getBirdYears() */
    public function getBirdYears(): ?int
    {
        return $this->getPlayerData()->birdYears;
    }

    /** @see PlayerInterface::getContractCurrentYear() */
    public function getContractCurrentYear(): ?int
    {
        return $this->getPlayerData()->contractCurrentYear;
    }

    /** @see PlayerInterface::getContractTotalYears() */
    public function getContractTotalYears(): ?int
    {
        return $this->getPlayerData()->contractTotalYears;
    }

    /** @see PlayerInterface::getContractYear1Salary() */
    public function getContractYear1Salary(): ?int
    {
        return $this->getPlayerData()->contractYear1Salary;
    }

    /** @see PlayerInterface::getContractYear2Salary() */
    public function getContractYear2Salary(): ?int
    {
        return $this->getPlayerData()->contractYear2Salary;
    }

    /** @see PlayerInterface::getContractYear3Salary() */
    public function getContractYear3Salary(): ?int
    {
        return $this->getPlayerData()->contractYear3Salary;
    }

    /** @see PlayerInterface::getContractYear4Salary() */
    public function getContractYear4Salary(): ?int
    {
        return $this->getPlayerData()->contractYear4Salary;
    }

    /** @see PlayerInterface::getContractYear5Salary() */
    public function getContractYear5Salary(): ?int
    {
        return $this->getPlayerData()->contractYear5Salary;
    }

    /** @see PlayerInterface::getContractYear6Salary() */
    public function getContractYear6Salary(): ?int
    {
        return $this->getPlayerData()->contractYear6Salary;
    }

    /** @see PlayerInterface::getSalaryJSB() */
    public function getSalaryJSB(): ?int
    {
        return $this->getPlayerData()->salaryJSB;
    }

    /** @see PlayerInterface::getDraftYear() */
    public function getDraftYear(): ?int
    {
        return $this->getPlayerData()->draftYear;
    }

    /** @see PlayerInterface::getDraftRound() */
    public function getDraftRound(): ?int
    {
        return $this->getPlayerData()->draftRound;
    }

    /** @see PlayerInterface::getDraftPickNumber() */
    public function getDraftPickNumber(): ?int
    {
        return $this->getPlayerData()->draftPickNumber;
    }

    /** @see PlayerInterface::getDraftTeamOriginalName() */
    public function getDraftTeamOriginalName(): ?string
    {
        return $this->getPlayerData()->draftTeamOriginalName;
    }

    /** @see PlayerInterface::getDraftTeamCurrentName() */
    public function getDraftTeamCurrentName(): ?string
    {
        return $this->getPlayerData()->draftTeamCurrentName;
    }

    /** @see PlayerInterface::getCollegeName() */
    public function getCollegeName(): ?string
    {
        return $this->getPlayerData()->collegeName;
    }

    /** @see PlayerInterface::getDaysRemainingForInjury() */
    public function getDaysRemainingForInjury(): ?int
    {
        return $this->getPlayerData()->daysRemainingForInjury;
    }

    /** @see PlayerInterface::getHeightFeet() */
    public function getHeightFeet(): ?int
    {
        return $this->getPlayerData()->heightFeet;
    }

    /** @see PlayerInterface::getHeightInches() */
    public function getHeightInches(): ?int
    {
        return $this->getPlayerData()->heightInches;
    }

    /** @see PlayerInterface::getWeightPounds() */
    public function getWeightPounds(): ?int
    {
        return $this->getPlayerData()->weightPounds;
    }

    /** @see PlayerInterface::getIsRetired() */
    public function getIsRetired(): ?int
    {
        return $this->getPlayerData()->isRetired;
    }

    /** @see PlayerInterface::getTimeDroppedOnWaivers() */
    public function getTimeDroppedOnWaivers(): ?int
    {
        return $this->getPlayerData()->timeDroppedOnWaivers;
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
        return $this->repository->getFreeAgencyDemands($this->playerID ?? 0);
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
