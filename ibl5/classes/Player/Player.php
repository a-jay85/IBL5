<?php

namespace Player;

/**
 * Player - Facade for player-related operations
 * 
 * This class now acts as a facade, delegating responsibilities to specialized classes
 * while maintaining backward compatibility with existing code.
 */
class Player
{
    protected $db;
    protected $playerData;
    protected $repository;
    protected $contractCalculator;
    protected $contractValidator;
    protected $nameDecorator;
    protected $injuryCalculator;

    // Keep all public properties for backward compatibility
    public $playerID;
    public $plr;

    public $ordinal;
    public $name;
    public $nickname;
    public $age;
    public $historicalYear;

    public $teamID;
    public $teamName;
    public $position;

    public $ratingFieldGoalAttempts;
    public $ratingFieldGoalPercentage;
    public $ratingFreeThrowAttempts;
    public $ratingFreeThrowPercentage;
    public $ratingThreePointAttempts;
    public $ratingThreePointPercentage;
    public $ratingOffensiveRebounds;
    public $ratingDefensiveRebounds;
    public $ratingAssists;
    public $ratingSteals;
    public $ratingTurnovers;
    public $ratingBlocks;
    public $ratingFouls;
    public $ratingOutsideOffense;
    public $ratingOutsideDefense;
    public $ratingDriveOffense;
    public $ratingDriveDefense;
    public $ratingPostOffense;
    public $ratingPostDefense;
    public $ratingTransitionOffense;
    public $ratingTransitionDefense;
    public $ratingClutch;
    public $ratingConsistency;
    public $ratingTalent;
    public $ratingSkill;
    public $ratingIntangibles;

    public $freeAgencyLoyalty;
    public $freeAgencyPlayingTime;
    public $freeAgencyPlayForWinner;
    public $freeAgencyTradition;
    public $freeAgencySecurity;

    public $yearsOfExperience;
    public $birdYears;
    public $contractCurrentYear;
    public $contractTotalYears;
    public $contractYear1Salary;
    public $contractYear2Salary;
    public $contractYear3Salary;
    public $contractYear4Salary;
    public $contractYear5Salary;
    public $contractYear6Salary;
    public $currentSeasonSalary;
    public $salaryJSB;

    public $draftYear;
    public $draftRound;
    public $draftPickNumber;
    public $draftTeamOriginalName;
    public $draftTeamCurrentName;
    public $collegeName;

    public $daysRemainingForInjury;

    public $heightFeet;
    public $heightInches;
    public $weightPounds;

    public $isRetired;

    public $timeDroppedOnWaivers;
    
    public $decoratedName;

    public function __construct()
    {
        $this->contractCalculator = new PlayerContractCalculator();
        $this->contractValidator = new PlayerContractValidator();
        $this->nameDecorator = new PlayerNameDecorator();
        $this->injuryCalculator = new PlayerInjuryCalculator();
    }

    public static function withPlayerID($db, int $playerID)
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->loadByID($playerID);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    public static function withPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromCurrentRow($plrRow);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    public static function withHistoricalPlrRow($db, array $plrRow)
    {
        $instance = new self();
        $instance->initialize($db);
        $instance->playerData = $instance->repository->fillFromHistoricalRow($plrRow);
        $instance->syncPropertiesFromPlayerData();
        return $instance;
    }

    /**
     * Initialize the Player instance with database and repository
     */
    protected function initialize($db): void
    {
        $this->db = $db;
        $this->repository = new PlayerRepository($db);
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use static factory methods instead
     */
    protected function loadByID($db, int $playerID)
    {
        $this->initialize($db);
        $this->playerData = $this->repository->loadByID($playerID);
        $this->syncPropertiesFromPlayerData();
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use static factory methods instead
     */
    protected function fill($db, array $plrRow)
    {
        $this->initialize($db);
        $this->playerData = $this->repository->fillFromCurrentRow($plrRow);
        $this->syncPropertiesFromPlayerData();
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use static factory methods instead
     */
    protected function fillHistorical($db, array $plrRow)
    {
        $this->initialize($db);
        $this->playerData = $this->repository->fillFromHistoricalRow($plrRow);
        $this->syncPropertiesFromPlayerData();
    }

    /**
     * Sync all public properties from PlayerData for backward compatibility
     * Uses reflection to copy all properties from PlayerData to Player
     */
    protected function syncPropertiesFromPlayerData()
    {
        $reflection = new \ReflectionClass($this->playerData);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            // Skip 'plr' property as it's not used in Player facade
            if ($propertyName === 'plr') {
                continue;
            }
            $this->$propertyName = $this->playerData->$propertyName;
        }
        
        // Calculate derived properties
        $this->currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($this->playerData);
        $this->decoratedName = $this->decoratePlayerName();
    }

    public function decoratePlayerName()
    {
        return $this->nameDecorator->decoratePlayerName($this->playerData);
    }

    public function getCurrentSeasonSalary()
    {
        return $this->contractCalculator->getCurrentSeasonSalary($this->playerData);
    }

    public function getFreeAgencyDemands()
    {
        return $this->repository->getFreeAgencyDemands($this->name);
    }

    public function getInjuryReturnDate($rawLastSimEndDate)
    {
        return $this->injuryCalculator->getInjuryReturnDate($this->playerData, $rawLastSimEndDate);
    }

    public function getNextSeasonSalary()
    {
        return $this->contractCalculator->getNextSeasonSalary($this->playerData);
    }

    public function getLongBuyoutArray()
    {
        return $this->contractCalculator->getLongBuyoutArray($this->playerData);
    }

    public function getShortBuyoutArray()
    {
        return $this->contractCalculator->getShortBuyoutArray($this->playerData);
    }

    public function getRemainingContractArray()
    {
        return $this->contractCalculator->getRemainingContractArray($this->playerData);
    }

    public function getTotalRemainingSalary()
    {
        return $this->contractCalculator->getTotalRemainingSalary($this->playerData);
    }

    public function canRenegotiateContract()
    {
        return $this->contractValidator->canRenegotiateContract($this->playerData);
    }

    public function canRookieOption($seasonPhase)
    {
        return $this->contractValidator->canRookieOption($this->playerData, $seasonPhase);
    }

    public function wasRookieOptioned()
    {
        return $this->contractValidator->wasRookieOptioned($this->playerData);
    }
}