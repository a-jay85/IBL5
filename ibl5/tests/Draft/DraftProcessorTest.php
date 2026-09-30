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

    public function testCreateDraftAnnouncementExactFormat(): void
    {
        $this->assertSame(
            'With pick #5 in round 1 of the 2024 IBL Draft, the **Chicago Bulls** select **John Doe!**',
            $this->processor->createDraftAnnouncement(5, 1, 2024, 'Chicago Bulls', 'John Doe')
        );
    }

    public function testCreateDraftAnnouncementZeroPickAndMarkdownNamesPassThroughVerbatim(): void
    {
        $this->assertSame(
            'With pick #0 in round 0 of the 2024 IBL Draft, the **A*B Club** select **_Under_ Score!**',
            $this->processor->createDraftAnnouncement(0, 0, 2024, 'A*B Club', '_Under_ Score')
        );
    }

    public function testCreateNextTeamMessageOnClockExactFormat(): void
    {
        $this->assertSame(
            "Base\n    **<@!123456789>** is on the clock!\nhttps://www.iblhoops.net/ibl5/modules.php?name=Draft",
            $this->processor->createNextTeamMessage('Base', 123456789, 2024)
        );
    }

    public function testCreateNextTeamMessageWithZeroDiscordIdStaysOnClockBranch(): void
    {
        $this->assertSame(
            "Base\n    **<@!0>** is on the clock!\nhttps://www.iblhoops.net/ibl5/modules.php?name=Draft",
            $this->processor->createNextTeamMessage('Base', 0, 2024)
        );
    }

    public function testCreateNextTeamMessageConcludedExactFormat(): void
    {
        $this->assertSame(
            "Base\n    **🏁 __The 2024 IBL Draft has officially concluded!__ 🏁**",
            $this->processor->createNextTeamMessage('Base', null, 2024)
        );
    }

    public function testCreateNextTeamMessageConcludedWithNullSeasonYear(): void
    {
        $this->assertSame(
            "Base\n    **🏁 __The  IBL Draft has officially concluded!__ 🏁**",
            $this->processor->createNextTeamMessage('Base', null, null)
        );
    }

    public function testGetSuccessMessageExactFormat(): void
    {
        $this->assertSame(
            "Announcement<p>\n        <a href=\"/ibl5/modules.php?name=Draft\">Go back to the Draft module</a>",
            $this->processor->getSuccessMessage('Announcement')
        );
    }

    public function testGetDatabaseErrorMessageNamesAdministratorAndEndsWithLink(): void
    {
        $message = $this->processor->getDatabaseErrorMessage();

        $this->assertStringStartsWith("Oops, something went wrong, and at least one of the draft database tables wasn't updated.<p>\n", $message);
        $this->assertStringContainsString("\n            Let the administrator know what happened and they'll look into it.<p>\n", $message);
        $this->assertStringEndsWith('<a href="/ibl5/modules.php?name=Draft">Go back to the Draft module</a>', $message);
    }
}
