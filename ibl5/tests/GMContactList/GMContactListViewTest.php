<?php

declare(strict_types=1);

namespace Tests\GMContactList;

use GMContactList\Contracts\GMContactListViewInterface;
use GMContactList\GMContactListView;
use PHPUnit\Framework\TestCase;

class GMContactListViewTest extends TestCase
{
    private GMContactListView $view;

    protected function setUp(): void
    {
        $this->view = new GMContactListView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(GMContactListViewInterface::class, $this->view);
    }

    public function testRenderOutputsTable(): void
    {
        $contacts = [self::createContact()];

        $html = $this->view->render($contacts);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testRenderShowsTeamName(): void
    {
        $contacts = [self::createContact(['team_name' => 'Hawks'])];

        $html = $this->view->render($contacts);

        $this->assertStringContainsString('Hawks', $html);
    }

    public function testRenderShowsOwnerName(): void
    {
        $contacts = [self::createContact(['owner_name' => 'John Doe'])];

        $html = $this->view->render($contacts);

        $this->assertStringContainsString('John Doe', $html);
    }

    public function testRenderLinksToDiscordDm(): void
    {
        $contacts = [self::createContact(['discordID' => 123456789])];

        $html = $this->view->render($contacts);

        $this->assertStringContainsString('https://discord.com/users/123456789', $html);
    }

    public function testRenderWithNullDiscordIdShowsPlainName(): void
    {
        $contacts = [self::createContact(['discordID' => null])];

        $html = $this->view->render($contacts);

        $this->assertStringNotContainsString('discord.com/users', $html);
        $this->assertStringContainsString('Test Owner', $html);
    }

    public function testRenderDoesNotContainAimColumn(): void
    {
        $contacts = [self::createContact()];

        $html = $this->view->render($contacts);

        $this->assertStringNotContainsString('<th>AIM</th>', $html);
    }

    public function testRenderDoesNotContainSkypeColumn(): void
    {
        $contacts = [self::createContact()];

        $html = $this->view->render($contacts);

        $this->assertStringNotContainsString('<th>Skype</th>', $html);
    }

    public function testRenderDoesNotContainMailtoLink(): void
    {
        $contacts = [self::createContact()];

        $html = $this->view->render($contacts);

        $this->assertStringNotContainsString('mailto:', $html);
    }

    public function testRenderEscapesOwnerName(): void
    {
        $contacts = [self::createContact(['owner_name' => '<script>alert(1)</script>'])];

        $html = $this->view->render($contacts);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, owner_name: string, discordID: int|null}
     */
    private static function createContact(array $overrides = []): array
    {
        /** @var array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, owner_name: string, discordID: int|null} */
        return array_merge([
            'teamid' => 1,
            'team_city' => 'Atlanta',
            'team_name' => 'Hawks',
            'color1' => 'FF0000',
            'color2' => 'FFFFFF',
            'owner_name' => 'Test Owner',
            'discordID' => 666988022751035397,
        ], $overrides);
    }
}
