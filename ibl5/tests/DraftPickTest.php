<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use DraftPick;

/**
 * DraftPickTest - Tests for DraftPick entity class
 */
class DraftPickTest extends TestCase
{
    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testDraftPickCanBeInstantiated(): void
    {
        $row = $this->createValidDraftPickRow();
        $draftPick = new DraftPick($row);
        
        $this->assertInstanceOf(DraftPick::class, $draftPick);
    }

    public function testDraftPickPropertiesAreSetCorrectly(): void
    {
        $row = $this->createValidDraftPickRow();
        $draftPick = new DraftPick($row);
        
        $this->assertEquals(1, $draftPick->pickID);
        $this->assertEquals('Current Owner', $draftPick->owner);
        $this->assertEquals('Original Team', $draftPick->originalTeam);
        $this->assertEquals(2025, $draftPick->year);
        $this->assertEquals(1, $draftPick->round);
        $this->assertEquals('Lottery protected', $draftPick->notes);
    }

    // ============================================
    // PROPERTY ACCESS TESTS
    // ============================================

    public function testPickIdIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['pickid' => 42]);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals(42, $draftPick->pickID);
    }

    public function testOwnerIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['ownerofpick' => 'Boston']);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals('Boston', $draftPick->owner);
    }

    public function testOriginalTeamIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['teampick' => 'Los Angeles']);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals('Los Angeles', $draftPick->originalTeam);
    }

    public function testYearIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['year' => 2028]);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals(2028, $draftPick->year);
    }

    public function testRoundIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['round' => 2]);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals(2, $draftPick->round);
    }

    public function testNotesIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['notes' => 'Top 5 protected']);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals('Top 5 protected', $draftPick->notes);
    }

    public function testEmptyNotesIsAccessible(): void
    {
        $row = $this->createValidDraftPickRow(['notes' => '']);
        $draftPick = new DraftPick($row);
        
        $this->assertEquals('', $draftPick->notes);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function createValidDraftPickRow(array $overrides = []): array
    {
        return array_merge([
            'pickid' => 1,
            'ownerofpick' => 'Current Owner',
            'teampick' => 'Original Team',
            'year' => 2025,
            'round' => 1,
            'notes' => 'Lottery protected',
        ], $overrides);
    }
}
