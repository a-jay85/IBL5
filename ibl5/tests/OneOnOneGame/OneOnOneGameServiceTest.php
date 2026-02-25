<?php

declare(strict_types=1);


namespace Tests\OneOnOneGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use OneOnOneGame\OneOnOneGameService;
use OneOnOneGame\OneOnOneGameRepository;
use OneOnOneGame\OneOnOneGameEngine;
use OneOnOneGame\OneOnOneGameResult;

/**
 * Tests for OneOnOneGameService
 */
#[AllowMockObjectsWithoutExpectations]
final class OneOnOneGameServiceTest extends TestCase
{
    private OneOnOneGameService $service;
    /** @var OneOnOneGameRepository&\PHPUnit\Framework\MockObject\MockObject */
    private $mockRepository;
    /** @var OneOnOneGameEngine&\PHPUnit\Framework\MockObject\MockObject */
    private $mockGameEngine;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(OneOnOneGameRepository::class);
        $this->mockGameEngine = $this->createMock(OneOnOneGameEngine::class);
        $this->service = new OneOnOneGameService($this->mockRepository, $this->mockGameEngine);
    }

    // ========== validatePlayerSelection Tests ==========

    public function testValidatePlayerSelectionReturnsEmptyArrayForValidSelection(): void
    {
        $errors = $this->service->validatePlayerSelection(1, 2);

        $this->assertEmpty($errors);
    }

    public function testValidatePlayerSelectionReturnsErrorWhenPlayer1IsNull(): void
    {
        $errors = $this->service->validatePlayerSelection(null, 2);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Player 1', $errors[0]);
    }

    public function testValidatePlayerSelectionReturnsErrorWhenPlayer2IsNull(): void
    {
        $errors = $this->service->validatePlayerSelection(1, null);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Player 2', $errors[0]);
    }

    public function testValidatePlayerSelectionReturnsErrorWhenPlayer1IsZero(): void
    {
        $errors = $this->service->validatePlayerSelection(0, 2);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Player 1', $errors[0]);
    }

    public function testValidatePlayerSelectionReturnsErrorWhenPlayer2IsZero(): void
    {
        $errors = $this->service->validatePlayerSelection(1, 0);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Player 2', $errors[0]);
    }

    public function testValidatePlayerSelectionReturnsErrorWhenBothPlayersAreSame(): void
    {
        $errors = $this->service->validatePlayerSelection(5, 5);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('same player', $errors[0]);
    }

    public function testValidatePlayerSelectionReturnsMultipleErrorsWhenBothNull(): void
    {
        $errors = $this->service->validatePlayerSelection(null, null);

        $this->assertCount(2, $errors);
    }

    // ========== playGame Tests ==========

    public function testPlayGameThrowsExceptionForInvalidPlayerSelection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->playGame(5, 5, 'Owner');
    }

    public function testPlayGameThrowsExceptionWhenPlayer1NotFound(): void
    {
        $this->mockRepository->expects($this->once())
            ->method('getPlayerForGame')
            ->with(1)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player 1 with ID 1 not found');

        $this->service->playGame(1, 2, 'Owner');
    }

    public function testPlayGameThrowsExceptionWhenPlayer2NotFound(): void
    {
        $player1Data = ['pid' => 1, 'name' => 'Player One'];

        $this->mockRepository->expects($this->exactly(2))
            ->method('getPlayerForGame')
            ->willReturnCallback(function ($id) use ($player1Data) {
                return $id === 1 ? $player1Data : null;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Player 2 with ID 2 not found');

        $this->service->playGame(1, 2, 'Owner');
    }

    public function testPlayGameReturnsGameResult(): void
    {
        $player1Data = $this->createMockPlayerData(1, 'Player One');
        $player2Data = $this->createMockPlayerData(2, 'Player Two');
        $expectedResult = new OneOnOneGameResult();
        $expectedResult->player1Name = 'Player One';
        $expectedResult->player2Name = 'Player Two';
        $expectedResult->player1Score = 21;
        $expectedResult->player2Score = 18;

        $this->mockRepository->expects($this->exactly(2))
            ->method('getPlayerForGame')
            ->willReturnCallback(function ($id) use ($player1Data, $player2Data) {
                return $id === 1 ? $player1Data : $player2Data;
            });

        $this->mockGameEngine->expects($this->once())
            ->method('simulateGame')
            ->with($player1Data, $player2Data, 'Owner')
            ->willReturn($expectedResult);

        $this->mockRepository->expects($this->once())
            ->method('saveGame')
            ->with($expectedResult)
            ->willReturn(100);

        $result = $this->service->playGame(1, 2, 'Owner');

        $this->assertInstanceOf(OneOnOneGameResult::class, $result);
        $this->assertEquals('Player One', $result->player1Name);
        $this->assertEquals('Player Two', $result->player2Name);
    }

    public function testPlayGameSavesGameToRepository(): void
    {
        $player1Data = $this->createMockPlayerData(1, 'Player One');
        $player2Data = $this->createMockPlayerData(2, 'Player Two');
        $gameResult = new OneOnOneGameResult();

        $this->mockRepository->method('getPlayerForGame')
            ->willReturnCallback(function ($id) use ($player1Data, $player2Data) {
                return $id === 1 ? $player1Data : $player2Data;
            });

        $this->mockGameEngine->method('simulateGame')
            ->willReturn($gameResult);

        $this->mockRepository->expects($this->once())
            ->method('saveGame')
            ->with($gameResult)
            ->willReturn(42);

        $result = $this->service->playGame(1, 2, 'Owner');

        $this->assertEquals(42, $result->gameId);
    }

    // ========== Helper Methods ==========

    private function createMockPlayerData(int $pid, string $name): array
    {
        return [
            'pid' => $pid,
            'name' => $name,
            'oo' => 50,
            'do' => 50,
            'po' => 50,
            'od' => 50,
            'dd' => 50,
            'pd' => 50,
            'r_fga' => 50,
            'r_fgp' => 45,
            'r_fta' => 50,
            'r_tga' => 30,
            'r_tgp' => 35,
            'r_orb' => 30,
            'r_drb' => 50,
            'r_stl' => 40,
            'r_to' => 50,
            'r_blk' => 30,
            'r_foul' => 50,
        ];
    }
}
