<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerPageController;

class PlayerPageControllerTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $mysqliDb = $this->createStub(\mysqli::class);

        $controller = new PlayerPageController($mysqliDb);

        $this->assertInstanceOf(PlayerPageController::class, $controller);
    }

    public function testAcceptsDifferentDbConnections(): void
    {
        $db1 = $this->createStub(\mysqli::class);
        $db2 = $this->createStub(\mysqli::class);

        $controller1 = new PlayerPageController($db1);
        $controller2 = new PlayerPageController($db2);

        $this->assertNotSame($controller1, $controller2);
    }
}
