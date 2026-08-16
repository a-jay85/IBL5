<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamView;

/**
 * @phpstan-import-type TeamPageData from \Team\Contracts\TeamServiceInterface
 * @phpstan-type TeamPageDataOverrides array{teamid?: int, team?: \Team\Team, imagesPath?: string, yr?: ?string, display?: string, insertyear?: string, isActualTeam?: bool, tableOutput?: string, draftPicksTable?: string, currentSeasonCard?: string, awardsCard?: string, franchiseHistoryCard?: string, rafters?: string, userTeamName?: string, isOwnTeam?: bool, extensionResult?: ?string, extensionMsg?: ?string}
 */
final class TeamViewXssTest extends TestCase
{
    private TeamView $view;

    protected function setUp(): void
    {
        $this->view = new TeamView();
    }

    /**
     * Build a minimal pageData array with a Team stub.
     *
     * @param TeamPageDataOverrides $overrides
     * @return TeamPageData
     */
    private function makePageData(array $overrides = [], string $teamName = 'Safe Team'): array
    {
        $team = self::createStub(\Team\Team::class);
        $team->name = $teamName;
        $team->color1 = 'FF0000';
        $team->color2 = '000000';
        $team->discord_id = null;

        return array_merge([
            'teamid' => 1,
            'team' => $team,
            'imagesPath' => 'images/',
            'yr' => null,
            'display' => 'ratings',
            'insertyear' => '',
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
