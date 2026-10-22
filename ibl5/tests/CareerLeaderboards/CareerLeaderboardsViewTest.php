<?php

declare(strict_types=1);

namespace Tests\CareerLeaderboards;

use PHPUnit\Framework\TestCase;
use CareerLeaderboards\CareerLeaderboardsService;
use CareerLeaderboards\CareerLeaderboardsView;

final class CareerLeaderboardsViewTest extends TestCase
{
    private CareerLeaderboardsView $view;
    private CareerLeaderboardsService $service;

    protected function setUp(): void
    {
        $this->service = new CareerLeaderboardsService();
        $this->view = new CareerLeaderboardsView($this->service);
    }

    public function testRenderFilterFormCreatesValidHtml(): void
    {
        $filters = [
            'phase' => 'regular',
            'mode' => 'totals',
            'sortby' => 'PPG',
            'retirees' => false,
            'display' => '50'
        ];

        $html = $this->view->renderFilterForm($filters);

        // Check that form is rendered
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="CareerLeaderboards"', $html);
        $this->assertStringContainsString('method="get" action="modules.php"', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="Leaderboards">', $html);
        $this->assertStringContainsString('<input type="hidden" name="tab" value="career">', $html);

        // Check that all form fields are present
        $this->assertStringContainsString('name="phase"', $html);
        $this->assertStringContainsString('name="mode"', $html);
        $this->assertStringContainsString('name="sortby"', $html);
        $this->assertStringContainsString('name="retirees"', $html);
        $this->assertStringContainsString('name="display"', $html);
        $this->assertStringContainsString('name="submitted"', $html);
        
        // Check that selected values are marked
        $this->assertStringContainsString('value="50"', $html);
        $this->assertStringContainsString('selected', $html);
        
        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderFilterFormHandlesEmptyFilters(): void
    {
        $filters = [];

        $html = $this->view->renderFilterForm($filters);

        // Should still render the form
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="CareerLeaderboards"', $html);
    }

    public function testRenderTableHeaderCreatesValidHtml(): void
    {
        $html = $this->view->renderTableHeader();

        // Page heading is rendered in index.php, not in renderTableHeader()
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('sortable', $html);

        // Check that all stat columns are present
        $this->assertStringContainsString('>#<', $html);
        $this->assertStringContainsString('>Name<', $html);
        $this->assertStringContainsString('>G<', $html);
        $this->assertStringContainsString('>MIN<', $html);
        $this->assertStringContainsString('>FGM<', $html);
        $this->assertStringContainsString('>FGA<', $html);
        $this->assertStringContainsString('>FG%<', $html);
        $this->assertStringContainsString('>PTS<', $html);

        // DRB column appears between ORB and REB
        $this->assertStringContainsString('>DRB<', $html);
        $orbPos = strpos($html, '>ORB<');
        $drbPos = strpos($html, '>DRB<');
        $rebPos = strpos($html, '>REB<');
        $this->assertNotFalse($orbPos);
        $this->assertNotFalse($drbPos);
        $this->assertNotFalse($rebPos);
        $this->assertGreaterThan($orbPos, $drbPos);
        $this->assertGreaterThan($drbPos, $rebPos);
    }

    public function testGamesColumnHiddenWhenShowGamesIsOff(): void
    {
        $stats = [
            'pid' => 123, 'name' => 'Test Player', 'games' => '777', 'minutes' => '30',
            'fgm' => '5', 'fga' => '10', 'fgp' => '0.500', 'ftm' => '2', 'fta' => '2',
            'ftp' => '1.000', 'tgm' => '1', 'tga' => '3', 'tgp' => '0.333', 'orb' => '1',
            'drb' => '4', 'reb' => '5', 'ast' => '3', 'stl' => '1', 'tvr' => '2',
            'blk' => '0', 'pf' => '2', 'pts' => '13',
        ];

        $this->view->setShowGames(false);
        $header = $this->view->renderTableHeader();
        $row = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringNotContainsString('>G<', $header);
        $this->assertStringContainsString('>MIN<', $header);
        $this->assertStringNotContainsString('777', $row);
        $this->assertSame(preg_match_all('/<th[ >]/', $header), preg_match_all('/<td[ >]/', $row));
    }

    public function testGamesSortDisabledForOneGamePhase(): void
    {
        $rookie = $this->view->renderFilterForm(['phase' => 'rookie']);
        $regular = $this->view->renderFilterForm(['phase' => 'regular']);

        $this->assertMatchesRegularExpression('/<option value="GAMES"[^>]* disabled>/', $rookie);
        $this->assertDoesNotMatchRegularExpression('/<option value="GAMES"[^>]* disabled>/', $regular);
        $this->assertStringContainsString('value="sophomore" data-has-averages="0" data-shows-games="0"', $rookie);
    }

    public function testRenderPlayerRowCreatesValidHtml(): void
    {
        $stats = [
            'pid' => 123,
            'name' => 'Test Player',
            'games' => '82',
            'minutes' => '3,000',
            'fgm' => '500',
            'fga' => '1,000',
            'fgp' => '0.500',
            'ftm' => '200',
            'fta' => '250',
            'ftp' => '0.800',
            'tgm' => '150',
            'tga' => '400',
            'tgp' => '0.375',
            'orb' => '100',
            'drb' => '400',
            'reb' => '500',
            'ast' => '400',
            'stl' => '80',
            'tvr' => '150',
            'blk' => '50',
            'pf' => '200',
            'pts' => '1,350'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that row is created
        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('</tr>', $html);
        
        // Check that rank is displayed
        $this->assertStringContainsString('>1<', $html);
        
        // Check that player link is created (& properly encoded as &amp; in HTML)
        $this->assertStringContainsString('href="./modules.php?name=Player&amp;pa=showpage&amp;pid=123"', $html);
        $this->assertStringContainsString('>Test Player<', $html);
        
        // Check that stats are displayed
        $this->assertStringContainsString('>82<', $html);
        $this->assertStringContainsString('>3,000<', $html);
        $this->assertStringContainsString('>0.500<', $html);
        $this->assertStringContainsString('>1,350<', $html);
    }

    public function testRenderPlayerRowEscapesHtml(): void
    {
        $stats = [
            'pid' => 456,
            'name' => 'Player <script>alert("XSS")</script>',
            'games' => '82',
            'minutes' => '3,000',
            'fgm' => '500',
            'fga' => '1,000',
            'fgp' => '0.500',
            'ftm' => '200',
            'fta' => '250',
            'ftp' => '0.800',
            'tgm' => '150',
            'tga' => '400',
            'tgp' => '0.375',
            'orb' => '100',
            'drb' => '400',
            'reb' => '500',
            'ast' => '400',
            'stl' => '80',
            'tvr' => '150',
            'blk' => '50',
            'pf' => '200',
            'pts' => '1,350'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderTableFooterCreatesValidHtml(): void
    {
        $html = $this->view->renderTableFooter();

        // Check that table is closed (footer only closes tbody, table, and container div)
        $this->assertStringContainsString('</tbody>', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('</div>', $html);
    }

    public function testRenderPlayerRowHandlesRetiredPlayer(): void
    {
        $stats = [
            'pid' => 789,
            'name' => 'Retired Legend*',
            'games' => '1,000',
            'minutes' => '40,000',
            'fgm' => '10,000',
            'fga' => '20,000',
            'fgp' => '0.500',
            'ftm' => '5,000',
            'fta' => '6,000',
            'ftp' => '0.833',
            'tgm' => '2,000',
            'tga' => '6,000',
            'tgp' => '0.333',
            'orb' => '2,000',
            'drb' => '8,000',
            'reb' => '10,000',
            'ast' => '8,000',
            'stl' => '1,500',
            'tvr' => '2,000',
            'blk' => '1,000',
            'pf' => '3,000',
            'pts' => '27,000'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that asterisk is displayed for retired player
        $this->assertStringContainsString('Retired Legend*', $html);
    }

    public function testFilterFormSubmitsViaGetToLeaderboardsCareerTab(): void
    {
        $html = $this->view->renderFilterForm([
            'phase' => 'regular',
            'mode' => 'totals',
            'sortby' => 'PPG',
            'retirees' => true,
            'display' => '50',
        ]);

        $this->assertStringContainsString('method="get" action="modules.php"', $html);
        $this->assertStringNotContainsString('method="post"', $html);
        $this->assertStringContainsString('<input type="hidden" name="name" value="Leaderboards">', $html);
        $this->assertStringContainsString('<input type="hidden" name="tab" value="career">', $html);
        $this->assertStringContainsString('<input type="hidden" name="submitted" value="1">', $html);
        // The retired module name would hit the 302 stub and lose the query string.
        $this->assertStringNotContainsString('name=CareerLeaderboards"', $html);
    }

    public function testFilterFormRendersPhaseSelectWithAllPhases(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('id="cl-phase" name="phase"', $html);
        foreach (['Regular Season', 'Playoffs', 'H.E.A.T.', 'Olympics', 'Rookie Game', 'Sophomore Game', 'All-Star Game'] as $label) {
            $this->assertStringContainsString('>' . $label . '</option>', $html);
        }
        $this->assertStringNotContainsString('boards_type', $html);
        $this->assertStringNotContainsString('sort_cat', $html);
    }

    public function testFilterFormRendersSegmentedModeRadios(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('<fieldset class="ibl-segmented">', $html);
        $this->assertStringContainsString('<legend class="ibl-segmented__legend">', $html);
        $this->assertStringContainsString('type="radio" name="mode" value="totals" checked', $html);
        $this->assertMatchesRegularExpression('/value="averages"(?![^>]*disabled)/', $html);
    }

    public function testFilterFormDisablesAveragesRadioForRookie(): void
    {
        $html = $this->view->renderFilterForm(['phase' => 'rookie', 'mode' => 'averages']);

        $this->assertMatchesRegularExpression('/value="averages"[^>]*disabled/', $html);
        $this->assertStringContainsString('value="totals" checked', $html);
    }

    public function testFilterFormDisablesAveragesRadioForSophomore(): void
    {
        $html = $this->view->renderFilterForm(['phase' => 'sophomore']);

        $this->assertMatchesRegularExpression('/value="averages"[^>]*disabled/', $html);
    }

    public function testFilterFormSortByOptionsExcludeQaAndLabelPts(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('id="cl-sortby" name="sortby"', $html);
        $this->assertStringContainsString('value="PPG" selected>PTS</option>', $html);
        $this->assertStringNotContainsString('value="QA"', $html);
        $this->assertStringNotContainsString('Category:', $html);
    }

    public function testFilterFormLabelsPpgOnAverages(): void
    {
        $html = $this->view->renderFilterForm(['mode' => 'averages']);

        $this->assertStringContainsString('value="PPG" selected>PPG</option>', $html);
        $this->assertStringContainsString('value="averages" checked', $html);
    }

    public function testFilterFormKeepsPercentageSortOptionsEnabledOnTotals(): void
    {
        $html = $this->view->renderFilterForm(['mode' => 'totals', 'sortby' => 'FGP']);

        $this->assertStringContainsString('value="FGP" selected>FG%</option>', $html);
        $this->assertStringContainsString('value="FTP">FT%</option>', $html);
        $this->assertStringContainsString('value="TGP">TG%</option>', $html);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*value="(FGP|FTP|TGP)"[^>]*disabled/', $html);
    }

    public function testFilterFormEnablesPercentageSortOptionsOnAverages(): void
    {
        $html = $this->view->renderFilterForm(['mode' => 'averages', 'sortby' => 'FGP']);

        $this->assertStringContainsString('value="FGP" selected>FG%</option>', $html);
        $this->assertStringContainsString('value="FTP">FT%</option>', $html);
        $this->assertStringContainsString('value="TGP">TG%</option>', $html);
    }

    public function testFilterFormRendersRetireesSwitchCheckedByDefault(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('type="checkbox" role="switch" name="retirees" value="1" id="cl-retirees"', $html);
        $this->assertMatchesRegularExpression('/id="cl-retirees"[^>]*checked/', $html);
        $this->assertStringContainsString('>Retired?</label>', $html);
    }

    public function testFilterFormRendersRetireesSwitchOffWhenFalse(): void
    {
        $html = $this->view->renderFilterForm(['retirees' => false]);

        $this->assertDoesNotMatchRegularExpression('/id="cl-retirees"[^>]*checked/', $html);
        $this->assertStringContainsString('name="submitted" value="1"', $html);
    }

    public function testFilterFormUsesResultsLimitAndSearchButton(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('>Results Limit:</label>', $html);
        $this->assertStringContainsString('placeholder="50"', $html);
        $this->assertStringNotContainsString('Records', $html);
        $this->assertStringContainsString('>Search</button>', $html);
        $this->assertStringNotContainsString('Display Career Leaderboards', $html);
    }

    public function testFilterFormIsStackedAndLoadsEnhancementScript(): void
    {
        $html = $this->view->renderFilterForm([]);

        $this->assertStringContainsString('ibl-filter-form ibl-filter-form--stacked', $html);
        $this->assertStringContainsString('ibl-filter-form__actions', $html);
        $this->assertStringContainsString('<script src="jslib/career-leaderboards-form.js" defer></script>', $html);
    }
}
