<?php

declare(strict_types=1);

namespace Tests\Navigation;

use Navigation\NavigationConfig;
use PHPUnit\Framework\TestCase;

class NavigationConfigTest extends TestCase
{
    public function testFullConstruction(): void
    {
        $teamsData = [
            'Eastern' => [
                'Atlantic' => [
                    ['teamid' => 1, 'team_name' => 'Celtics', 'team_city' => 'Boston'],
                ],
            ],
        ];

        $config = new NavigationConfig(
            isLoggedIn: true,
            username: 'TestUser',
            currentLeague: 'ibl',
            teamId: 5,
            teamsData: $teamsData,
            seasonPhase: 'Draft',
            allowWaivers: 'Yes',
            showDraftLink: 'On',
            serverName: 'localhost',
            requestUri: '/ibl5/index.php',
            isAdmin: true,
        );

        $this->assertTrue($config->isLoggedIn);
        $this->assertSame('TestUser', $config->username);
        $this->assertSame('ibl', $config->currentLeague);
        $this->assertSame(5, $config->teamId);
        $this->assertSame($teamsData, $config->teamsData);
        $this->assertSame('Draft', $config->seasonPhase);
        $this->assertSame('Yes', $config->allowWaivers);
        $this->assertSame('On', $config->showDraftLink);
        $this->assertSame('localhost', $config->serverName);
        $this->assertSame('/ibl5/index.php', $config->requestUri);
        $this->assertTrue($config->isAdmin);
    }

    public function testMinimalDefaults(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: false,
            username: null,
            currentLeague: 'ibl',
        );

        $this->assertFalse($config->isLoggedIn);
        $this->assertNull($config->username);
        $this->assertSame('ibl', $config->currentLeague);
        $this->assertNull($config->teamId);
        $this->assertNull($config->teamsData);
        $this->assertSame('', $config->seasonPhase);
        $this->assertSame('', $config->allowWaivers);
        $this->assertSame('', $config->showDraftLink);
        $this->assertNull($config->serverName);
        $this->assertNull($config->requestUri);
        $this->assertFalse($config->isAdmin);
        $this->assertSame(0, $config->unreadNotificationCount);
    }

    public function testUnreadNotificationCountRoundTrips(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: true,
            username: 'TestUser',
            currentLeague: 'ibl',
            teamId: 1,
            unreadNotificationCount: 7,
        );

        $this->assertSame(7, $config->unreadNotificationCount);
    }

    public function testReadonlyProperties(): void
    {
        $config = new NavigationConfig(
            isLoggedIn: true,
            username: 'Admin',
            currentLeague: 'olympics',
        );

        $reflection = new \ReflectionClass($config);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
