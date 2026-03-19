<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Draft\DraftRepository;

/**
 * Tests DraftRepository against real MariaDB — draft picks, draft class, player creation.
 */
class DraftRepositoryTest extends DatabaseTestCase
{
    private DraftRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DraftRepository($this->db);
    }

    public function testGetCurrentDraftSelectionReturnsPlayerName(): void
    {
        $this->insertDraftRow(2099, 1, 1, 1, 'DraftTest Player');

        $result = $this->repo->getCurrentDraftSelection(1, 1);

        self::assertSame('DraftTest Player', $result);
    }

    public function testGetCurrentDraftSelectionReturnsEmptyStringWhenEmpty(): void
    {
        $this->insertDraftRow(2099, 1, 2, 1, '');

        $result = $this->repo->getCurrentDraftSelection(1, 2);

        // Method returns the player column value; empty string for undrafted slot
        self::assertSame('', $result);
    }

    public function testGetCurrentDraftSelectionReturnsNullWhenNoRow(): void
    {
        $result = $this->repo->getCurrentDraftSelection(99, 99);

        self::assertNull($result);
    }

    public function testUpdateDraftTableSetsPlayerAndDate(): void
    {
        $this->insertDraftRow(2099, 2, 1, 1, '');

        $result = $this->repo->updateDraftTable('New Draftee', '2099-06-15 12:00:00', 2, 1);

        self::assertTrue($result);

        $check = $this->repo->getCurrentDraftSelection(2, 1);
        self::assertSame('New Draftee', $check);
    }

    public function testUpdateDraftTableReturnsFalseWhenNoRow(): void
    {
        $result = $this->repo->updateDraftTable('Nobody', '2099-06-15 12:00:00', 99, 99);

        self::assertFalse($result);
    }

    public function testUpdateRookieTableMarksDrafted(): void
    {
        $this->insertDraftClassRow('DC UpdateTest', 'SF');

        $result = $this->repo->updateRookieTable('DC UpdateTest', 'Metros');

        self::assertTrue($result);

        // Verify drafted flag set
        self::assertTrue($this->repo->isPlayerAlreadyDrafted('DC UpdateTest'));
    }

    public function testUpdateRookieTableReturnsFalseWhenMissing(): void
    {
        $result = $this->repo->updateRookieTable('Nonexistent Prospect', 'Metros');

        self::assertFalse($result);
    }

    public function testIsPlayerAlreadyDraftedReturnsFalse(): void
    {
        $this->insertDraftClassRow('DC UndraftedTest', 'PG', ['drafted' => 0]);

        self::assertFalse($this->repo->isPlayerAlreadyDrafted('DC UndraftedTest'));
    }

    public function testIsPlayerAlreadyDraftedReturnsTrue(): void
    {
        $this->insertDraftClassRow('DC DraftedTest', 'C', ['drafted' => 1]);

        self::assertTrue($this->repo->isPlayerAlreadyDrafted('DC DraftedTest'));
    }

    public function testGetAllDraftClassPlayersIncludesTeamJoin(): void
    {
        $this->insertDraftClassRow('DC AllListTest', 'PF', ['team' => 'Metros']);

        $players = $this->repo->getAllDraftClassPlayers();

        self::assertNotEmpty($players);

        $found = false;
        foreach ($players as $player) {
            if ($player['name'] === 'DC AllListTest') {
                $found = true;
                self::assertArrayHasKey('color1', $player);
                self::assertArrayHasKey('color2', $player);
                break;
            }
        }
        self::assertTrue($found, 'Inserted draft class player not found in getAllDraftClassPlayers');
    }

    public function testGetCurrentDraftPickReturnsFirstEmptySlot(): void
    {
        // Insert a taken slot and an empty slot
        $this->insertDraftRow(2099, 1, 1, 1, 'Already Picked');
        $this->insertDraftRow(2099, 1, 2, 2, '', ['team' => 'Enforcers', 'tid' => 2]);

        $pick = $this->repo->getCurrentDraftPick();

        self::assertNotNull($pick);
        self::assertSame(2, $pick['tid']);
        self::assertSame(1, $pick['round']);
        self::assertSame(2, $pick['pick']);
    }
}
