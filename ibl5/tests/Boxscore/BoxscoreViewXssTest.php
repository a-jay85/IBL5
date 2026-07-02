<?php

declare(strict_types=1);

namespace Tests\Boxscore;

use Boxscore\BoxscoreView;
use Boxscore\Contracts\BoxscoreViewInterface;
use PHPUnit\Framework\TestCase;

final class BoxscoreViewXssTest extends TestCase
{
    public function testImplementsBoxscoreViewInterface(): void
    {
        $view = new BoxscoreView();

        $this->assertInstanceOf(BoxscoreViewInterface::class, $view);
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
