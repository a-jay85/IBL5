<?php

declare(strict_types=1);

namespace Tests\Injuries;

use PHPUnit\Framework\TestCase;
use Injuries\InjuriesView;
use Injuries\Contracts\InjuriesViewInterface;

/**
 * InjuriesViewTest - Tests for InjuriesView HTML rendering
 *
 * @covers \Injuries\InjuriesView
 */
class InjuriesViewTest extends TestCase
{
    private InjuriesViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new InjuriesView();
    }

    public function testImplementsInjuriesViewInterface(): void
    {
        $this->assertInstanceOf(InjuriesViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([]);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTitle(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('Injured Players', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('Pos', $result);
        $this->assertStringContainsString('Player', $result);
        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('>Days<', $result);
    }

    public function testRenderWithInjuredPlayersData(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'John Smith',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('John Smith', $result);
        $this->assertStringContainsString('PG', $result);
        $this->assertStringContainsString('5', $result);
        $this->assertStringContainsString('Boston', $result);
        $this->assertStringContainsString('Celtics', $result);
    }

    public function testRenderWithMultipleInjuredPlayers(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Player One',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
            [
                'playerID' => 2,
                'name' => 'Player Two',
                'position' => 'C',
                'daysRemaining' => 10,
                'teamID' => 2,
                'teamCity' => 'Los Angeles',
                'teamName' => 'Lakers',
                'teamColor1' => '552583',
                'teamColor2' => 'FDB927',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('Player One', $result);
        $this->assertStringContainsString('Player Two', $result);
        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('Lakers', $result);
    }

    public function testRenderAlternatesRowColors(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Player One',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
            [
                'playerID' => 2,
                'name' => 'Player Two',
                'position' => 'C',
                'daysRemaining' => 10,
                'teamID' => 2,
                'teamCity' => 'Los Angeles',
                'teamName' => 'Lakers',
                'teamColor1' => '552583',
                'teamColor2' => 'FDB927',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // CSS nth-child selectors in style block handle row alternation
        $this->assertStringContainsString('nth-child(odd)', $result);
        $this->assertStringContainsString('nth-child(even)', $result);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Player<script>alert("xss")</script>',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Test<script>',
                'teamName' => 'Team&Name',
                'teamColor1' => '000000',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // The raw <script> tag should not appear - should be escaped
        $this->assertStringNotContainsString('<script>alert', $result);
        // Escaped version should appear
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderIncludesPlayerLinks(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 123,
                'name' => 'John Smith',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('pid=123', $result);
        $this->assertStringContainsString('modules.php?name=Player', $result);
    }

    public function testRenderIncludesTeamLinks(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'John Smith',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 42,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('teamID=42', $result);
        $this->assertStringContainsString('modules.php?name=Team', $result);
    }
}
