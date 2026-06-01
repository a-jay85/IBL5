<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\Contracts\PlayerInterface;
use Player\Player;

class PlayerInterfaceContractTest extends TestCase
{
    private const array EXPECTED_GETTERS = [
        'getPlayerID',
        'getPlrRow',
        'getOrdinal',
        'getName',
        'getNickname',
        'getAge',
        'getHistoricalYear',
        'getTeamid',
        'getTeamName',
        'getTeamColor1',
        'getTeamColor2',
        'getPosition',
        'getRatingFieldGoalAttempts',
        'getRatingFieldGoalPercentage',
        'getRatingFreeThrowAttempts',
        'getRatingFreeThrowPercentage',
        'getRatingThreePointAttempts',
        'getRatingThreePointPercentage',
        'getRatingOffensiveRebounds',
        'getRatingDefensiveRebounds',
        'getRatingAssists',
        'getRatingSteals',
        'getRatingTurnovers',
        'getRatingBlocks',
        'getRatingFouls',
        'getRatingOutsideOffense',
        'getRatingOutsideDefense',
        'getRatingDriveOffense',
        'getRatingDriveDefense',
        'getRatingPostOffense',
        'getRatingPostDefense',
        'getRatingTransitionOffense',
        'getRatingTransitionDefense',
        'getRatingClutch',
        'getRatingConsistency',
        'getRatingTalent',
        'getRatingSkill',
        'getRatingIntangibles',
        'getFreeAgencyLoyalty',
        'getFreeAgencyPlayingTime',
        'getFreeAgencyPlayForWinner',
        'getFreeAgencyTradition',
        'getFreeAgencySecurity',
        'getYearsOfExperience',
        'getBirdYears',
        'getContractCurrentYear',
        'getContractTotalYears',
        'getContractYear1Salary',
        'getContractYear2Salary',
        'getContractYear3Salary',
        'getContractYear4Salary',
        'getContractYear5Salary',
        'getContractYear6Salary',
        'getSalaryJSB',
        'getDraftYear',
        'getDraftRound',
        'getDraftPickNumber',
        'getDraftTeamOriginalName',
        'getDraftTeamCurrentName',
        'getCollegeName',
        'getDaysRemainingForInjury',
        'getHeightFeet',
        'getHeightInches',
        'getWeightPounds',
        'getIsRetired',
        'getTimeDroppedOnWaivers',
        'getDecoratedName',
        'getNameStatusClass',
    ];

    public function testPlayerInterfaceDeclaresAllGetters(): void
    {
        $interface = new \ReflectionClass(PlayerInterface::class);

        foreach (self::EXPECTED_GETTERS as $method) {
            self::assertTrue(
                $interface->hasMethod($method),
                "PlayerInterface is missing declaration for $method()"
            );
        }
    }

    public function testPlayerImplementsAllInterfaceGetters(): void
    {
        $player = new \ReflectionClass(Player::class);

        foreach (self::EXPECTED_GETTERS as $method) {
            self::assertTrue(
                $player->hasMethod($method),
                "Player is missing implementation of $method()"
            );
        }
    }
}
