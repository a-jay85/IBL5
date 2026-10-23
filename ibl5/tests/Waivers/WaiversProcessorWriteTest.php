<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Validation\ValidationResult;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;
use Waivers\WaiversProcessor;

class WaiversProcessorWriteTest extends TestCase
{
    /** @var WaiversRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private WaiversRepositoryInterface $repoStub;
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private \Repositories\Contracts\TeamIdentityRepositoryInterface $teamIdentityRepoStub;
    /** @var \Repositories\Contracts\PlayerLookupRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private \Repositories\Contracts\PlayerLookupRepositoryInterface $playerLookupRepoStub;
    /** @var WaiversValidatorInterface&\PHPUnit\Framework\MockObject\Stub */
    private WaiversValidatorInterface $validatorStub;
    private \Topics\News\NewsRepository $newsServiceStub;
    private WaiversProcessor $processor;

    protected function setUp(): void
    {
        $this->repoStub = self::createStub(WaiversRepositoryInterface::class);
        $this->teamIdentityRepoStub = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $this->playerLookupRepoStub = self::createStub(\Repositories\Contracts\PlayerLookupRepositoryInterface::class);
        $this->validatorStub = self::createStub(WaiversValidatorInterface::class);
        $this->newsServiceStub = self::createStub(\Topics\News\NewsRepository::class);
        $dbStub = self::createStub(\mysqli::class);

        $this->processor = new WaiversProcessor(
            $this->repoStub,
            $this->teamIdentityRepoStub,
            $this->playerLookupRepoStub,
            $this->validatorStub,
            $this->newsServiceStub,
            $dbStub
        );
    }

    // ============================================
    // processDrop tests
    // ============================================

    public function testProcessDropReturnsSuccessWhenRepoSucceeds(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::success());
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn(['name' => 'Test Player', 'pid' => 1]);
        $this->repoStub->method('dropPlayerToWaivers')->willReturn(true);

        $result = $this->processor->processDrop(1, 'Test Team', 3, 5000);

        self::assertTrue($result['success']);
        self::assertSame('player_dropped', $result['result'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenValidationFails(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::failure('Over hard cap'));

        $result = $this->processor->processDrop(1, 'Test Team', 3, 8000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Over hard cap', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorForNullPlayerID(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::success());

        $result = $this->processor->processDrop(null, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorForZeroPlayerID(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::success());

        $result = $this->processor->processDrop(0, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenPlayerNotFound(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::success());
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn(null);

        $result = $this->processor->processDrop(999, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertSame('Player not found.', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenRepoFails(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(ValidationResult::success());
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn(['name' => 'Test Player', 'pid' => 1]);
        $this->repoStub->method('dropPlayerToWaivers')->willReturn(false);

        $result = $this->processor->processDrop(1, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Failed to drop', $result['error'] ?? '');
    }

    // ============================================
    // processAdd tests
    // ============================================

    public function testProcessAddReturnsErrorForNullPlayerID(): void
    {
        $result = $this->processor->processAdd(null, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenPlayerNotFound(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn(null);

        $result = $this->processor->processAdd(999, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
        self::assertSame('Player not found.', $result['error'] ?? '');
    }

    /**
     * Pins the salary hand-off: the validator receives the determineContractData() salary,
     * which is the veteran minimum for a no-contract player in-season.
     */
    public function testProcessAddPassesContractSalaryToValidator(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'exp' => 3,
            'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);
        $validatorMock = $this->createMock(WaiversValidatorInterface::class);
        $validatorMock->expects($this->once())->method('validateAdd')
            ->with(1, 4, 5, 3000, \League\ContractRules::getVeteranMinimumSalary(3), \League\ContractRules::getVeteranMinimumSalary(3))
            ->willReturn(ValidationResult::failure('stop'));
        $processor = new WaiversProcessor(
            $this->repoStub,
            $this->teamIdentityRepoStub,
            $this->playerLookupRepoStub,
            $validatorMock,
            $this->newsServiceStub,
            self::createStub(\mysqli::class),
            null,
            new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class))
        );

        $result = $processor->processAdd(1, 'Test Team', 4, 5, 3000);

        self::assertFalse($result['success']);
    }

    /**
     * Offseason (Draft / Free Agency) contracts use one extra year of experience, so the
     * validator's veteran minimum comes from exp + 1.
     */
    public function testProcessAddPassesOffseasonVeteranMinimumToValidator(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'exp' => 3,
            'cy' => 0, 'cyt' => 0,
            'salary_yr1' => 0, 'salary_yr2' => 0, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);
        $validatorMock = $this->createMock(WaiversValidatorInterface::class);
        $validatorMock->expects($this->once())->method('validateAdd')
            ->with(1, 5, 5, 3000, \League\ContractRules::getVeteranMinimumSalary(4), \League\ContractRules::getVeteranMinimumSalary(4))
            ->willReturn(ValidationResult::failure('stop'));
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->phase = 'Free Agency';
        $processor = new WaiversProcessor(
            $this->repoStub,
            $this->teamIdentityRepoStub,
            $this->playerLookupRepoStub,
            $validatorMock,
            $this->newsServiceStub,
            self::createStub(\mysqli::class),
            null,
            $season
        );

        $result = $processor->processAdd(1, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
    }

    /**
     * Offseason with an existing contract: the validator receives the next-year salary
     * and the veteran minimum for exp + 1.
     */
    public function testProcessAddOffseasonContractedPlayerPassesNextYearSalaryAndExpPlusOneVetMin(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'exp' => 3,
            'cy' => 1, 'cyt' => 3,
            'salary_yr1' => 500, 'salary_yr2' => 600, 'salary_yr3' => 700,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);
        $validatorMock = $this->createMock(WaiversValidatorInterface::class);
        $validatorMock->expects($this->once())->method('validateAdd')
            ->with(1, 5, 5, 3000, 600, \League\ContractRules::getVeteranMinimumSalary(4))
            ->willReturn(ValidationResult::failure('stop'));
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->phase = 'Free Agency';
        $processor = new WaiversProcessor(
            $this->repoStub,
            $this->teamIdentityRepoStub,
            $this->playerLookupRepoStub,
            $validatorMock,
            $this->newsServiceStub,
            self::createStub(\mysqli::class),
            null,
            $season
        );

        $result = $processor->processAdd(1, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
    }

    /**
     * A player with an existing contract hands that salary to the validator, and the
     * veteran minimum still comes from the player's own experience.
     */
    public function testProcessAddPassesExistingContractSalaryAndOwnVeteranMinimum(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'exp' => 2,
            'cy' => 1, 'cyt' => 2,
            'salary_yr1' => 500, 'salary_yr2' => 500, 'salary_yr3' => 0,
            'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
        ]);
        $validatorMock = $this->createMock(WaiversValidatorInterface::class);
        $validatorMock->expects($this->once())->method('validateAdd')
            ->with(1, 5, 5, 3000, 500, \League\ContractRules::getVeteranMinimumSalary(2))
            ->willReturn(ValidationResult::failure('stop'));
        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->phase = 'Regular Season';
        $processor = new WaiversProcessor(
            $this->repoStub,
            $this->teamIdentityRepoStub,
            $this->playerLookupRepoStub,
            $validatorMock,
            $this->newsServiceStub,
            self::createStub(\mysqli::class),
            null,
            $season
        );

        $result = $processor->processAdd(1, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
    }

    public function testProcessAddReturnsErrorWhenValidationFails(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'salary_yr1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(ValidationResult::failure('Full roster'));

        $result = $this->processor->processAdd(1, 'Test Team', 0, 0, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Full roster', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenTeamNotFound(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'salary_yr1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(ValidationResult::success());
        $this->teamIdentityRepoStub->method('getTeamByName')->willReturn(null);

        $result = $this->processor->processAdd(1, 'Fake Team', 5, 5, 3000);

        self::assertFalse($result['success']);
        self::assertSame('Team not found.', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenRepoSignFails(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'salary_yr1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(ValidationResult::success());
        $this->teamIdentityRepoStub->method('getTeamByName')->willReturn(['team_name' => 'Test Team', 'teamid' => 1]);
        $this->repoStub->method('signPlayerFromWaivers')->willReturn(false);

        $result = $this->processor->processAdd(1, 'Test Team', 5, 5, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('something went wrong', $result['error'] ?? '');
    }

    public function testProcessAddReturnsSuccessWhenAllValid(): void
    {
        $this->playerLookupRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'salary_yr1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(ValidationResult::success());
        $this->teamIdentityRepoStub->method('getTeamByName')->willReturn(['team_name' => 'Test Team', 'teamid' => 1]);
        $this->repoStub->method('signPlayerFromWaivers')->willReturn(true);

        $result = $this->processor->processAdd(1, 'Test Team', 5, 5, 3000);

        self::assertTrue($result['success']);
        self::assertSame('player_added', $result['result'] ?? '');
    }
}
