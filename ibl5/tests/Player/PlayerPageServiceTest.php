<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Player\PlayerPageService;
use Player\Player;

class PlayerPageServiceTest extends TestCase
{
    private $db;
    private $service;

    protected function setUp(): void
    {
        // Mock database connection
        $this->db = new MockDatabase();
        $this->service = new PlayerPageService($this->db);
    }

    public function testCanShowRenegotiationButtonReturnsTrueWhenAllConditionsMet()
    {
        $player = $this->createMockPlayer(false, true);
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertTrue($result, 'Should show renegotiation button when all conditions are met');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenRookieOptioned()
    {
        $player = $this->createMockPlayer(true, true);
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button when player was rookie optioned');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenTeamIsFreeAgents()
    {
        $player = $this->createMockPlayer(false, true);
        $userTeam = $this->createMockTeam('Free Agents', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button when user team is Free Agents');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenExtensionUsed()
    {
        $player = $this->createMockPlayer(false, true);
        $userTeam = $this->createMockTeam('Test Team', 1);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button when extension already used this season');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenCannotRenegotiate()
    {
        $player = $this->createMockPlayer(false, false);
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button when player cannot renegotiate');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenNotOwnerOfPlayer()
    {
        $player = $this->createMockPlayer(false, true, 'Other Team');
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button when user does not own the player');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenDraftPhase()
    {
        $player = $this->createMockPlayer(false, true);
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Draft');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button during Draft phase');
    }

    public function testCanShowRenegotiationButtonReturnsFalseWhenFreeAgencyPhase()
    {
        $player = $this->createMockPlayer(false, true);
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Free Agency');

        $result = $this->service->canShowRenegotiationButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show renegotiation button during Free Agency phase');
    }

    public function testShouldShowRookieOptionUsedMessageReturnsTrueWhenRookieOptioned()
    {
        $player = $this->createMockPlayer(true, false);

        $result = $this->service->shouldShowRookieOptionUsedMessage($player);

        $this->assertTrue($result, 'Should show message when player was rookie optioned');
    }

    public function testShouldShowRookieOptionUsedMessageReturnsFalseWhenNotRookieOptioned()
    {
        $player = $this->createMockPlayer(false, false);

        $result = $this->service->shouldShowRookieOptionUsedMessage($player);

        $this->assertFalse($result, 'Should not show message when player was not rookie optioned');
    }

    public function testCanShowRookieOptionButtonReturnsTrueWhenAllConditionsMet()
    {
        $player = $this->createMockPlayer(false, true, 'Test Team', true);
        
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRookieOptionButton($player, $userTeam, $season);

        $this->assertTrue($result, 'Should show rookie option button when all conditions are met');
    }

    public function testCanShowRookieOptionButtonReturnsFalseWhenTeamIsFreeAgents()
    {
        $player = $this->createMockPlayer(false, true, 'Test Team', true);
        
        $userTeam = $this->createMockTeam('Free Agents', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRookieOptionButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show rookie option button when user team is Free Agents');
    }

    public function testCanShowRookieOptionButtonReturnsFalseWhenCannotRookieOption()
    {
        $player = $this->createMockPlayer(false, true, 'Test Team', false);
        
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRookieOptionButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show rookie option button when player cannot use rookie option');
    }

    public function testCanShowRookieOptionButtonReturnsFalseWhenNotOwnerOfPlayer()
    {
        $player = $this->createMockPlayer(false, true, 'Other Team', true);
        
        $userTeam = $this->createMockTeam('Test Team', 0);
        $season = $this->createMockSeason('Regular Season');

        $result = $this->service->canShowRookieOptionButton($player, $userTeam, $season);

        $this->assertFalse($result, 'Should not show rookie option button when user does not own the player');
    }

    // Helper methods to create mock objects

    private function createMockPlayer(
        bool $wasRookieOptioned,
        bool $canRenegotiate,
        string $teamName = 'Test Team',
        bool $canRookieOpt = false
    ): Player {
        $player = new Player();
        $player->teamName = $teamName;
        
        // Set up mock behavior by creating a test double
        $testDouble = $this->getMockBuilder(Player::class)
            ->onlyMethods(['wasRookieOptioned', 'canRenegotiateContract', 'canRookieOption'])
            ->getMock();
        
        $testDouble->method('wasRookieOptioned')->willReturn($wasRookieOptioned);
        $testDouble->method('canRenegotiateContract')->willReturn($canRenegotiate);
        $testDouble->method('canRookieOption')->willReturn($canRookieOpt);
        $testDouble->teamName = $teamName;
        
        return $testDouble;
    }

    private function createMockTeam(string $name, int $hasUsedExtension): object
    {
        $team = new stdClass();
        $team->name = $name;
        $team->hasUsedExtensionThisSeason = $hasUsedExtension;
        return $team;
    }

    private function createMockSeason(string $phase): object
    {
        $season = new stdClass();
        $season->phase = $phase;
        return $season;
    }
}
