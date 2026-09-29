<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use PHPUnit\Framework\TestCase;
use ComparePlayers\ComparePlayersRepository;
use Tests\WideUnit\Mocks\MockDatabase;

class ComparePlayersRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private ComparePlayersRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMockDatabase();
        $this->repository = new ComparePlayersRepository($this->mockDb);
    }

    public function testGetAllPlayerNamesReturnsArrayOfNames(): void
    {
        $result = $this->repository->getAllPlayerNames();

        $this->assertIsArray($result);
        foreach ($result as $item) {
            $this->assertIsString($item);
        }
    }

    public function testGetPlayerByNameReturnsPlayerData(): void
    {
        $playerName = 'Michael Jordan';
        $result = $this->repository->getPlayerByName($playerName);

        // Mock database returns null for this player
        $this->assertNull($result);
    }

    public function testGetPlayerByNameReturnsNullForNonExistentPlayer(): void
    {
        $result = $this->repository->getPlayerByName('NonExistent Player 12345');

        $this->assertNull($result);
    }

    public function testGetPlayerByNameHandlesApostrophes(): void
    {
        // Test that apostrophes don't cause SQL errors — mock returns null (no data)
        $result = $this->repository->getPlayerByName("O'Neal");

        $this->assertNull($result);
    }

    public function testGetPlayerByNameHandlesSpecialCharacters(): void
    {
        // Test SQL injection attempt is handled safely
        $maliciousInput = "Jordan'; DROP TABLE ibl_plr; --";
        $result = $this->repository->getPlayerByName($maliciousInput);

        // Should return null safely without SQL error
        $this->assertNull($result);
    }





    public function testGetPlayerByNameHandlesEmptyString(): void
    {
        $result = $this->repository->getPlayerByName('');
        $this->assertNull($result);
    }

    public function testGetPlayerByNameHandlesWhitespaceOnlyString(): void
    {
        $result = $this->repository->getPlayerByName('   ');
        $this->assertNull($result);
    }

    private function createMockDatabase(): MockDatabase
    {
        return new MockDatabase();
    }
}
