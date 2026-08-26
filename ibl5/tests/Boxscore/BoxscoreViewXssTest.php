<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreView;
use Boxscore\Contracts\BoxscoreViewInterface;
use Boxscore\RejectedGame;
use PHPUnit\Framework\TestCase;

final class BoxscoreViewXssTest extends TestCase
{
    public function testImplementsBoxscoreViewInterface(): void
    {
        $view = new BoxscoreView();

        $this->assertTrue(
            (new \ReflectionClass($view))->implementsInterface(BoxscoreViewInterface::class),
        );
    }

    public function testParseLogEscapesXssInMessagesAndError(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 1,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 10,
            'messages' => [$xss],
            'error' => $xss,
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }

    public function testParseLogEscapesXssInRejectedGames(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        // gameDate flows through describe() -> triple() and then into the sco-rejects__item li.
        // reason is an enum-like constant and never rendered in describe(), so XSS must be
        // exercised via the string field that actually reaches the template.
        $reject = new RejectedGame(
            gameDate: $xss,
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );

        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 1,
            'linesProcessed' => 1,
            'messages' => [],
            'rejectedGames' => [$reject],
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }

    public function testParseLogEscapesXssInSourceArchive(): void
    {
        $xss = '" onload="alert(1)';
        $escaped = '&quot; onload=&quot;alert(1)';

        $reject = new RejectedGame(
            gameDate: '2008-04-05',
            visitorTeamid: 1,
            homeTeamid: 2,
            gameOfThatDay: 1,
            reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
        );

        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 1,
            'linesProcessed' => 1,
            'messages' => [],
            'rejectedGames' => [$reject],
            'sourceArchive' => $xss,
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }

    public function testParseLogOmitsRejectBlockWhenNoRejects(): void
    {
        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 5,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 0,
            'linesProcessed' => 5,
            'messages' => [],
            'rejectedGames' => [],
        ]);

        $this->assertStringNotContainsString('sco-rejects', $output);
        $this->assertStringNotContainsString('Rejected', $output);
    }

    public function testParseLogRendersRejectBadgeAndList(): void
    {
        $rejects = [
            new RejectedGame(
                gameDate: '2008-04-05',
                visitorTeamid: 1,
                homeTeamid: 2,
                gameOfThatDay: 1,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
            new RejectedGame(
                gameDate: '2008-04-06',
                visitorTeamid: 3,
                homeTeamid: 4,
                gameOfThatDay: 2,
                reason: RejectedGame::REASON_NOT_IN_SCHEDULE,
            ),
        ];

        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'gamesRejected' => 2,
            'linesProcessed' => 2,
            'messages' => [],
            'rejectedGames' => $rejects,
        ]);

        $this->assertStringContainsString('2 Rejected', $output);
        $this->assertSame(2, substr_count($output, 'sco-rejects__item'));
    }

    public function testParseLogHandlesResultArrayWithoutRejectKeys(): void
    {
        $view = new BoxscoreView();
        $output = $view->renderParseLog([
            'success' => true,
            'gamesInserted' => 1,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 1,
            'messages' => [],
        ]);

        $this->assertStringNotContainsString('sco-rejects', $output);
        $this->assertStringNotContainsString('Rejected', $output);
        $this->assertStringContainsString('1 Inserted', $output);
        $this->assertStringContainsString('0 Skipped', $output);
    }

    public function testAllStarRenameUIEscapesXssInTeamLabelAndPlayers(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $view = new BoxscoreView();
        $output = $view->renderAllStarRenameUI([
            ['id' => 1, 'date' => '2024-01-15', 'name' => 'East', 'seasonYear' => 2024, 'teamLabel' => $xss, 'players' => [$xss]],
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
