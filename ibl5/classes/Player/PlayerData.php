<?php

declare(strict_types=1);

namespace Player;

/**
 * PlayerData - Value object representing player information
 *
 * This class is a simple data container following the Data Transfer Object pattern.
 * It holds player data without business logic, making it easy to serialize, cache, and test.
 */
class PlayerData
{
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

    /** @var string|null Team primary color (hex without #) */
    public ?string $teamColor1 = null;

    /** @var string|null Team secondary/text color (hex without #) */
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
     * Create a new PlayerData instance
     */
    public function __construct()
    {
    }
}
