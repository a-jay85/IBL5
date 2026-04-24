<?php

declare(strict_types=1);

namespace Tests\Player;

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

    public function testIsSalaryPlaceholderAlwaysReturnsFalse(): void
    {
        // Cash and buyout entries are no longer stored in ibl_plr, so no
        // player loaded from the DB can be a salary placeholder.
        $names = [
            'John Smith',
            '',
            '|Cash from Metros',
            '|Cash',
            'Test Buyout Player',
            'Some|Name',
        ];

        foreach ($names as $name) {
            $playerRow = TestDataFactory::createPlayer([
                'name' => $name,
                'loyalty' => 0,
                'playing_time' => 0,
                'winner' => 0,
                'tradition' => 0,
                'security' => 0,
            ]);
            $player = Player::withPlrRow($this->mockDb, $playerRow);

            $this->assertFalse($player->isSalaryPlaceholder(), "Expected false for name: '{$name}'");
        }
    }
}
