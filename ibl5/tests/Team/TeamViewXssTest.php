<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamView;

final class TeamViewXssTest extends TestCase
{
    private TeamView $view;

    protected function setUp(): void
    {
        $this->view = new TeamView();
    }

    /**
     * Build a minimal pageData array with a stdClass team object.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makePageData(array $overrides = []): array
    {
        $team = new \stdClass();
        $team->name = $overrides['teamName'] ?? 'Safe Team';
        $team->color1 = 'FF0000';
        $team->color2 = '000000';
        $team->discord_id = null;

        return array_merge([
            'teamid' => 1,
            'team' => $team,
            'imagesPath' => 'images/',
            'yr' => null,
            'isActualTeam' => false,
            'tableOutput' => '',
            'draftPicksTable' => '',
            'currentSeasonCard' => '',
            'awardsCard' => '',
            'franchiseHistoryCard' => '',
            'rafters' => '',
            'userTeamName' => '',
            'isOwnTeam' => false,
            'extensionResult' => null,
            'extensionMsg' => null,
        ], $overrides);
    }

    public function testExtensionMsgWithScriptPayloadIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $pageData = $this->makePageData([
            'extensionResult' => 'extension_error',
            'extensionMsg' => $xss,
        ]);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString($escaped, $html);
        $this->assertStringNotContainsString($xss, $html);
    }
}
