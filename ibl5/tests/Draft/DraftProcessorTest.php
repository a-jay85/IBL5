<?php

declare(strict_types=1);

namespace Tests\Draft;

use PHPUnit\Framework\TestCase;
use Draft\DraftProcessor;

class DraftProcessorTest extends TestCase
{
    private DraftProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new DraftProcessor();
    }

    public function testCreateDraftAnnouncementFormatsCorrectly(): void
    {
        $message = $this->processor->createDraftAnnouncement(
            5,          // pick number
            1,          // round
            2024,       // season year
            'Chicago Bulls',
            'John Doe'
        );

        $this->assertStringContainsString('pick #5', $message);
        $this->assertStringContainsString('round 1', $message);
        $this->assertStringContainsString('2024', $message);
        $this->assertStringContainsString('**Chicago Bulls**', $message);
        $this->assertStringContainsString('**John Doe!**', $message);
    }

    public function testCreateDraftAnnouncementWithSecondRound(): void
    {
        $message = $this->processor->createDraftAnnouncement(
            35,         // pick number
            2,          // round
            2024,       // season year
            'Boston Celtics',
            'Jane Smith'
        );

        $this->assertStringContainsString('pick #35', $message);
        $this->assertStringContainsString('round 2', $message);
        $this->assertStringContainsString('**Boston Celtics**', $message);
        $this->assertStringContainsString('**Jane Smith!**', $message);
    }

    public function testCreateNextTeamMessageWithTeamOnClock(): void
    {
        $baseMessage = 'Draft announcement';
        $message = $this->processor->createNextTeamMessage(
            $baseMessage,
            123456789,  // Discord ID
            2024
        );

        $this->assertStringContainsString($baseMessage, $message);
        $this->assertStringContainsString('<@!123456789>', $message);
        $this->assertStringContainsString('on the clock', $message);
        $this->assertStringContainsString('Draft', $message);
    }

    public function testCreateNextTeamMessageWhenDraftComplete(): void
    {
        $baseMessage = 'Draft announcement';
        $message = $this->processor->createNextTeamMessage(
            $baseMessage,
            null,       // No Discord ID (draft complete)
            2024
        );

        $this->assertStringContainsString($baseMessage, $message);
        $this->assertStringContainsString('Draft has officially concluded', $message);
        $this->assertStringContainsString('2024', $message);
        $this->assertStringContainsString('🏁', $message);
    }

    public function testGetSuccessMessageContainsAnnouncementAndLink(): void
    {
        $announcement = 'Test announcement';
        $message = $this->processor->getSuccessMessage($announcement);

        $this->assertStringContainsString($announcement, $message);
        $this->assertStringContainsString('Go back to the Draft module', $message);
        $this->assertStringContainsString('name=Draft', $message);
    }

    public function testGetDatabaseErrorMessageContainsErrorAndLink(): void
    {
        $message = $this->processor->getDatabaseErrorMessage();

        $this->assertStringContainsString('went wrong', $message);
        $this->assertStringContainsString('database tables', $message);
        $this->assertStringContainsString('Go back to the Draft module', $message);
        $this->assertStringContainsString('name=Draft', $message);
    }

    public function testCreateDraftAnnouncementHandlesApostrophes(): void
    {
        $message = $this->processor->createDraftAnnouncement(
            10,
            1,
            2024,
            "Chicago Bulls",
            "D'Angelo Russell"
        );

        $this->assertStringContainsString("D'Angelo Russell", $message);
    }
}
