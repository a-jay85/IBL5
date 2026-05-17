<?php

declare(strict_types=1);

namespace Tests\FranchiseRecordBook;

use FranchiseRecordBook\FranchiseRecordBookView;
use PHPUnit\Framework\TestCase;

final class FranchiseRecordBookViewXssTest extends TestCase
{
    public function testRenderEscapesXssInTeamName(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $view = new FranchiseRecordBookView();
        $output = $view->render([
            'teams' => [
                ['teamid' => 1, 'team_name' => $xss, 'color1' => 'FF0000', 'color2' => '000000'],
            ],
            'team' => ['teamid' => 1, 'team_name' => $xss, 'color1' => 'FF0000', 'color2' => '000000'],
            'singleSeason' => [],
            'career' => [],
        ]);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
