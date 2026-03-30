<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Tests\Integration\Mocks\TestDataFactory;

class PlayerSalaryPlaceholderTest extends TestCase
{
    private \MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
    }

    #[DataProvider('salaryPlaceholderProvider')]
    public function testIsSalaryPlaceholder(string $name, bool $expected): void
    {
        $playerRow = TestDataFactory::createPlayer([
            'name' => $name,
            'loyalty' => 0,
            'playingTime' => 0,
            'winner' => 0,
            'tradition' => 0,
            'security' => 0,
        ]);
        $player = Player::withPlrRow($this->mockDb, $playerRow);

        $this->assertSame($expected, $player->isSalaryPlaceholder());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function salaryPlaceholderProvider(): array
    {
        return [
            'cash transaction pipe prefix' => ['|Cash from Metros', true],
            'cash transaction short' => ['|Cash', true],
            'pipe only' => ['|', true],
            'buyout in name' => ['Test Buyout Player', true],
            'buyout at start' => ['Buyout Record', true],
            'buyout at end' => ['Player Buyout', true],
            'normal player' => ['John Smith', false],
            'empty string' => ['', false],
            'pipe in middle' => ['Some|Name', false],
        ];
    }
}
