<?php

use PHPUnit\Framework\TestCase;
use Player\PlayerContractValidator;
use Player\PlayerData;

class PlayerContractValidatorTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new PlayerContractValidator();
    }

    public function testCanRenegotiateContractWhenInLastYear()
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 6;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertTrue($result);
    }

    public function testCanRenegotiateContractWhenYear1WithNoYear2()
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractYear2Salary = 0;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertTrue($result);
    }

    public function testCanRenegotiateContractWhenYear2WithNoYear3()
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 2;
        $playerData->contractYear3Salary = 0;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertTrue($result);
    }

    public function testCannotRenegotiateContractWhenYearHasNext()
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 2;
        $playerData->contractYear3Salary = 1000;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertFalse($result);
    }

    public function testCanRookieOptionFirstRoundDuringFreeAgency()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 2;
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Free Agency');
        
        $this->assertTrue($result);
    }

    public function testCanRookieOptionSecondRoundDuringFreeAgency()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 2;
        $playerData->yearsOfExperience = 1;
        $playerData->contractYear3Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Free Agency');
        
        $this->assertTrue($result);
    }

    public function testCanRookieOptionFirstRoundDuringPreseason()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 3;
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Preseason');
        
        $this->assertTrue($result);
    }

    public function testCanRookieOptionSecondRoundDuringHEAT()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 2;
        $playerData->yearsOfExperience = 2;
        $playerData->contractYear3Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'HEAT');
        
        $this->assertTrue($result);
    }

    public function testCannotRookieOptionDuringRegularSeason()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 2;
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Regular Season');
        
        $this->assertFalse($result);
    }

    public function testWasRookieOptionedFirstRound()
    {
        $playerData = new PlayerData();
        $playerData->yearsOfExperience = 4;
        $playerData->draftRound = 1;
        $playerData->contractYear3Salary = 369;
        $playerData->contractYear4Salary = 738;
        
        $result = $this->validator->wasRookieOptioned($playerData);
        
        $this->assertTrue($result);
    }

    public function testWasRookieOptionedSecondRound()
    {
        $playerData = new PlayerData();
        $playerData->yearsOfExperience = 3;
        $playerData->draftRound = 2;
        $playerData->contractYear2Salary = 51;
        $playerData->contractYear3Salary = 102;
        
        $result = $this->validator->wasRookieOptioned($playerData);
        
        $this->assertTrue($result);
    }

    public function testWasNotRookieOptioned()
    {
        $playerData = new PlayerData();
        $playerData->yearsOfExperience = 4;
        $playerData->draftRound = 1;
        $playerData->contractYear3Salary = 369;
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->wasRookieOptioned($playerData);
        
        $this->assertFalse($result);
    }

    public function testCannotRenegotiateWhenRookieOptionedFirstRoundInOptionYear()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 4;
        $playerData->contractCurrentYear = 4;
        $playerData->contractYear3Salary = 369;
        $playerData->contractYear4Salary = 738; // Doubled salary indicates rookie option
        $playerData->contractYear5Salary = 0;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertFalse($result, 'First round rookie optioned player in year 4 should not be able to renegotiate');
    }

    public function testCannotRenegotiateWhenRookieOptionedSecondRoundInOptionYear()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 2;
        $playerData->yearsOfExperience = 3;
        $playerData->contractCurrentYear = 3;
        $playerData->contractYear2Salary = 51;
        $playerData->contractYear3Salary = 102; // Doubled salary indicates rookie option
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertFalse($result, 'Second round rookie optioned player in year 3 should not be able to renegotiate');
    }

    public function testCanRenegotiateWhenRookieOptionedButNotInOptionYear()
    {
        // This test demonstrates a player who WAS rookie optioned in a previous year
        // but is now past the rookie option year
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 5; // Past the rookie option year
        $playerData->contractCurrentYear = 5;
        $playerData->contractYear3Salary = 369;
        $playerData->contractYear4Salary = 738; // This was the doubled rookie option year
        $playerData->contractYear5Salary = 800;
        $playerData->contractYear6Salary = 0; // No year 6, so can renegotiate
        
        $result = $this->validator->canRenegotiateContract($playerData);
        
        $this->assertTrue($result, 'Rookie optioned player can renegotiate after the option year when no next year salary');
    }

    public function testGetFinalYearRookieContractSalaryFirstRound()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->contractYear2Salary = 100;
        $playerData->contractYear3Salary = 150;
        
        $result = $this->validator->getFinalYearRookieContractSalary($playerData);
        
        $this->assertEquals(150, $result, 'First round picks have 3-year contracts (cy3 is final year)');
    }

    public function testGetFinalYearRookieContractSalarySecondRound()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 2;
        $playerData->contractYear2Salary = 100;
        $playerData->contractYear3Salary = 150;
        
        $result = $this->validator->getFinalYearRookieContractSalary($playerData);
        
        $this->assertEquals(100, $result, 'Second round picks have 2-year contracts (cy2 is final year)');
    }

    public function testGetFinalYearRookieContractSalaryNotDraftPick()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 0; // Not a draft pick
        $playerData->contractYear2Salary = 100;
        $playerData->contractYear3Salary = 150;
        
        $result = $this->validator->getFinalYearRookieContractSalary($playerData);
        
        $this->assertEquals(0, $result, 'Non-draft picks should return 0');
    }

    public function testCannotRookieOptionWithMoreThanThreeYearsExperience()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 4; // More than 3 years
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Free Agency');
        
        $this->assertFalse($result, 'Players with more than 3 years of experience should not be eligible');
    }

    public function testCannotRookieOptionSecondRoundWithMoreThanThreeYearsExperience()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 2;
        $playerData->yearsOfExperience = 5; // More than 3 years
        $playerData->contractYear3Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'Preseason');
        
        $this->assertFalse($result, 'Second round players with more than 3 years of experience should not be eligible');
    }

    public function testCannotRookieOptionWithExactlyFourYearsExperience()
    {
        $playerData = new PlayerData();
        $playerData->draftRound = 1;
        $playerData->yearsOfExperience = 4; // Exactly 4 years (more than 3)
        $playerData->contractYear4Salary = 0;
        
        $result = $this->validator->canRookieOption($playerData, 'HEAT');
        
        $this->assertFalse($result, 'Players with exactly 4 years of experience (> 3) should not be eligible');
    }

    public function testIsPlayerFreeAgentWhenFreeThisSeason()
    {
        $playerData = new PlayerData();
        $playerData->draftYear = 2020;
        $playerData->yearsOfExperience = 1;
        $playerData->contractTotalYears = 2;
        $playerData->contractCurrentYear = 1;
        
        // Free agent year = 2020 + 1 + 2 - 1 = 2022
        $season = $this->createMockSeason(2022);
        
        $result = $this->validator->isPlayerFreeAgent($playerData, $season);
        
        $this->assertTrue($result, 'Player should be free agent when calculation equals season ending year');
    }

    public function testIsPlayerFreeAgentWhenNotFreeThisSeason()
    {
        $playerData = new PlayerData();
        $playerData->draftYear = 2020;
        $playerData->yearsOfExperience = 1;
        $playerData->contractTotalYears = 2;
        $playerData->contractCurrentYear = 1;
        
        // Free agent year = 2020 + 1 + 2 - 1 = 2022, but season is 2023
        $season = $this->createMockSeason(2023);
        
        $result = $this->validator->isPlayerFreeAgent($playerData, $season);
        
        $this->assertFalse($result, 'Player should not be free agent when calculation does not equal season ending year');
    }

    public function testIsPlayerFreeAgentWithRookieContract()
    {
        $playerData = new PlayerData();
        $playerData->draftYear = 2020;
        $playerData->yearsOfExperience = 0;
        $playerData->contractTotalYears = 3;
        $playerData->contractCurrentYear = 0;
        
        // Free agent year = 2020 + 0 + 3 - 0 = 2023
        $season = $this->createMockSeason(2023);
        
        $result = $this->validator->isPlayerFreeAgent($playerData, $season);
        
        $this->assertTrue($result, 'Rookie should become free agent after contract expires');
    }

    private function createMockSeason($endingYear)
    {
        $season = $this->createMock(\Season::class);
        $season->endingYear = $endingYear;
        return $season;
    }
}
