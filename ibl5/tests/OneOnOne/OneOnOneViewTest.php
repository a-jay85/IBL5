<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use OneOnOne\OneOnOneView;
use OneOnOne\OneOnOneGameResult;
use OneOnOne\OneOnOnePlayerStats;

/**
 * Tests for OneOnOneView
 */
final class OneOnOneViewTest extends TestCase
{
    private OneOnOneView $view;

    protected function setUp(): void
    {
        $this->view = new OneOnOneView();
    }

    // ========== renderHeader Tests ==========

    public function testRenderHeaderReturnsHtmlString(): void
    {
        $html = $this->view->renderHeader();

        $this->assertIsString($html);
        $this->assertStringContainsString('One-on-One Match', $html);
    }

    // ========== renderPlayerSelectionForm Tests ==========

    public function testRenderPlayerSelectionFormIncludesPlayers(): void
    {
        $players = [
            ['pid' => 1, 'name' => 'Michael Jordan'],
            ['pid' => 2, 'name' => 'LeBron James'],
        ];

        $html = $this->view->renderPlayerSelectionForm($players, null, null);

        $this->assertStringContainsString('Michael Jordan', $html);
        $this->assertStringContainsString('LeBron James', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringContainsString('value="2"', $html);
    }

    public function testRenderPlayerSelectionFormMarksSelectedPlayer1(): void
    {
        $players = [
            ['pid' => 1, 'name' => 'Player One'],
            ['pid' => 2, 'name' => 'Player Two'],
        ];

        $html = $this->view->renderPlayerSelectionForm($players, 1, null);

        // Player 1 should be selected in the first dropdown
        $this->assertStringContainsString('value="1" selected', $html);
    }

    public function testRenderPlayerSelectionFormMarksSelectedPlayer2(): void
    {
        $players = [
            ['pid' => 1, 'name' => 'Player One'],
            ['pid' => 2, 'name' => 'Player Two'],
        ];

        $html = $this->view->renderPlayerSelectionForm($players, null, 2);

        // Player 2 should be selected in the second dropdown
        $this->assertStringContainsString('value="2" selected', $html);
    }

    public function testRenderPlayerSelectionFormHasSubmitButton(): void
    {
        $html = $this->view->renderPlayerSelectionForm([], null, null);

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('Begin One-on-One Match', $html);
    }

    public function testRenderPlayerSelectionFormEscapesHtmlInPlayerNames(): void
    {
        $players = [
            ['pid' => 1, 'name' => 'Player <script>alert("xss")</script>'],
        ];

        $html = $this->view->renderPlayerSelectionForm($players, null, null);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ========== renderGameLookupForm Tests ==========

    public function testRenderGameLookupFormReturnsHtml(): void
    {
        $html = $this->view->renderGameLookupForm();

        $this->assertStringContainsString('Review Old Game', $html);
        $this->assertStringContainsString('gameid', $html);
        $this->assertStringContainsString('type="text"', $html);
    }

    // ========== renderErrors Tests ==========

    public function testRenderErrorsReturnsEmptyStringWhenNoErrors(): void
    {
        $html = $this->view->renderErrors([]);

        $this->assertEquals('', $html);
    }

    public function testRenderErrorsDisplaysAllErrors(): void
    {
        $errors = [
            'Error message one',
            'Error message two',
        ];

        $html = $this->view->renderErrors($errors);

        $this->assertStringContainsString('Error message one', $html);
        $this->assertStringContainsString('Error message two', $html);
    }

    public function testRenderErrorsEscapesHtmlInErrors(): void
    {
        $errors = ['<script>alert("xss")</script>'];

        $html = $this->view->renderErrors($errors);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ========== renderGameResult Tests ==========

    public function testRenderGameResultIncludesPlayByPlay(): void
    {
        $result = new OneOnOneGameResult();
        $result->playByPlay = 'Test play by play content';

        $html = $this->view->renderGameResult($result, 123);

        $this->assertStringContainsString('Test play by play content', $html);
    }

    public function testRenderGameResultIncludesGameId(): void
    {
        $result = new OneOnOneGameResult();

        $html = $this->view->renderGameResult($result, 456);

        $this->assertStringContainsString('GAME ID: 456', $html);
    }

    // ========== renderGameReplay Tests ==========

    public function testRenderGameReplayIncludesGameInfo(): void
    {
        $gameData = [
            'gameid' => 789,
            'playbyplay' => 'Old game play by play',
            'winner' => 'Winner Name',
            'loser' => 'Loser Name',
            'winscore' => 21,
            'lossscore' => 18,
            'owner' => 'Game Owner',
        ];

        $html = $this->view->renderGameReplay($gameData);

        $this->assertStringContainsString('Replay of Game Number 789', $html);
        $this->assertStringContainsString('Winner Name', $html);
        $this->assertStringContainsString('Loser Name', $html);
        $this->assertStringContainsString('21', $html);
        $this->assertStringContainsString('18', $html);
        $this->assertStringContainsString('Game Owner', $html);
        $this->assertStringContainsString('Old game play by play', $html);
    }

    public function testRenderGameReplayEscapesHtmlInPlayerNames(): void
    {
        $gameData = [
            'gameid' => 1,
            'playbyplay' => '',
            'winner' => '<script>alert("xss")</script>',
            'loser' => 'Normal Name',
            'winscore' => 21,
            'lossscore' => 18,
            'owner' => 'Owner',
        ];

        $html = $this->view->renderGameReplay($gameData);

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
