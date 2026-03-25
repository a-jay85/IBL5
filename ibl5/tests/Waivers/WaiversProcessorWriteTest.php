<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversRepositoryInterface;
use Waivers\Contracts\WaiversValidatorInterface;
use Waivers\WaiversProcessor;

class WaiversProcessorWriteTest extends TestCase
{
    private WaiversRepositoryInterface $repoStub;
    private \Services\CommonMysqliRepository $commonRepoStub;
    private WaiversValidatorInterface $validatorStub;
    private \Services\NewsService $newsServiceStub;
    private WaiversProcessor $processor;

    protected function setUp(): void
    {
        $this->repoStub = $this->createStub(WaiversRepositoryInterface::class);
        $this->commonRepoStub = $this->createStub(\Services\CommonMysqliRepository::class);
        $this->validatorStub = $this->createStub(WaiversValidatorInterface::class);
        $this->newsServiceStub = $this->createStub(\Services\NewsService::class);
        $dbStub = $this->createStub(\mysqli::class);

        $this->processor = new WaiversProcessor(
            $this->repoStub,
            $this->commonRepoStub,
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
        $this->validatorStub->method('validateDrop')->willReturn(true);
        $this->commonRepoStub->method('getPlayerByID')->willReturn(['name' => 'Test Player', 'pid' => 1]);
        $this->repoStub->method('dropPlayerToWaivers')->willReturn(true);

        $result = $this->processor->processDrop(1, 'Test Team', 3, 5000);

        self::assertTrue($result['success']);
        self::assertSame('player_dropped', $result['result'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenValidationFails(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(false);
        $this->validatorStub->method('getErrors')->willReturn(['Over hard cap']);

        $result = $this->processor->processDrop(1, 'Test Team', 3, 8000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Over hard cap', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorForNullPlayerID(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(true);

        $result = $this->processor->processDrop(null, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorForZeroPlayerID(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(true);

        $result = $this->processor->processDrop(0, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenPlayerNotFound(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(true);
        $this->commonRepoStub->method('getPlayerByID')->willReturn(null);

        $result = $this->processor->processDrop(999, 'Test Team', 3, 5000);

        self::assertFalse($result['success']);
        self::assertSame('Player not found.', $result['error'] ?? '');
    }

    public function testProcessDropReturnsErrorWhenRepoFails(): void
    {
        $this->validatorStub->method('validateDrop')->willReturn(true);
        $this->commonRepoStub->method('getPlayerByID')->willReturn(['name' => 'Test Player', 'pid' => 1]);
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
        $result = $this->processor->processAdd(null, 'Test Team', 5, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('valid player', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenPlayerNotFound(): void
    {
        $this->commonRepoStub->method('getPlayerByID')->willReturn(null);

        $result = $this->processor->processAdd(999, 'Test Team', 5, 3000);

        self::assertFalse($result['success']);
        self::assertSame('Player not found.', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenValidationFails(): void
    {
        $this->commonRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'cy1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(false);
        $this->validatorStub->method('getErrors')->willReturn(['Full roster']);

        $result = $this->processor->processAdd(1, 'Test Team', 0, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Full roster', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenTeamNotFound(): void
    {
        $this->commonRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'cy1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(true);
        $this->commonRepoStub->method('getTeamByName')->willReturn(null);

        $result = $this->processor->processAdd(1, 'Fake Team', 5, 3000);

        self::assertFalse($result['success']);
        self::assertSame('Team not found.', $result['error'] ?? '');
    }

    public function testProcessAddReturnsErrorWhenRepoSignFails(): void
    {
        $this->commonRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'cy1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(true);
        $this->commonRepoStub->method('getTeamByName')->willReturn(['team_name' => 'Test Team', 'teamid' => 1]);
        $this->repoStub->method('signPlayerFromWaivers')->willReturn(false);

        $result = $this->processor->processAdd(1, 'Test Team', 5, 3000);

        self::assertFalse($result['success']);
        self::assertStringContainsString('something went wrong', $result['error'] ?? '');
    }

    public function testProcessAddReturnsSuccessWhenAllValid(): void
    {
        $this->commonRepoStub->method('getPlayerByID')->willReturn([
            'name' => 'Test Player', 'pid' => 1, 'cy1' => 0, 'exp' => 5,
        ]);
        $this->validatorStub->method('validateAdd')->willReturn(true);
        $this->commonRepoStub->method('getTeamByName')->willReturn(['team_name' => 'Test Team', 'teamid' => 1]);
        $this->repoStub->method('signPlayerFromWaivers')->willReturn(true);

        $result = $this->processor->processAdd(1, 'Test Team', 5, 3000);

        self::assertTrue($result['success']);
        self::assertSame('player_added', $result['result'] ?? '');
    }
}
