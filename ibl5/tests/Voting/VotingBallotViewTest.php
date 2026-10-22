<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\VotingBallotView;
use Voting\Contracts\VotingBallotViewInterface;

class VotingBallotViewTest extends TestCase
{
    private VotingBallotView $view;

    private const string PINNED_ASG_HTML = '<form name="ASGVote" method="post" action="action.php"><CSRF><div class="voting-form-container"><img src="images/logo/1.jpg" alt="Team Logo" class="team-logo-banner"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button><script>
function ShowAndHideGM() {
    var x = document.getElementById(\'GM\');
    var t = document.querySelector(\'[aria-controls="GM"]\');
    if (x.classList.contains(\'voting-collapsed\')) {
        x.classList.remove(\'voting-collapsed\');
        t.setAttribute(\'aria-expanded\', \'true\');
        if (typeof window.IBL_refreshResponsiveTables === \'function\') {
            window.IBL_refreshResponsiveTables();
        }
    } else {
        x.classList.add(\'voting-collapsed\');
        t.setAttribute(\'aria-expanded\', \'false\');
    }
}
</script><div class="voting-category" onclick="ShowAndHideGM()"><h2 class="ibl-title voting-category-title"><button type="button" class="voting-category-toggle" aria-expanded="false" aria-controls="GM">GM of the Year</button></h2><p class="voting-category-instruction">Select THREE.</p></div><table id="GM" class="sortable ibl-data-table voting-form-table voting-collapsed"><thead><tr><th>Vote</th><th>Name</th><th>Team</th></tr></thead><tbody><tr><td><input type="checkbox" name="GM[]" value="Pat O&apos;Brien, Boston Celtics"></td><td>Pat O&apos;Brien</td><td>Boston Celtics</td></tr><tr><td><input type="checkbox" name="GM[]" value="Jane Roe, Chicago Bulls"></td><td>Jane Roe</td><td>Chicago Bulls</td></tr></tbody></table><input type="hidden" name="teamname" value="Test Team"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button></div></form>';

    private const string PINNED_EOY_HTML = '<form name="EOYVote" method="post" action="action.php"><CSRF><div class="voting-form-container"><img src="images/logo/1.jpg" alt="Team Logo" class="team-logo-banner"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button><script>
function ShowAndHideGM() {
    var x = document.getElementById(\'GM\');
    var t = document.querySelector(\'[aria-controls="GM"]\');
    if (x.classList.contains(\'voting-collapsed\')) {
        x.classList.remove(\'voting-collapsed\');
        t.setAttribute(\'aria-expanded\', \'true\');
        if (typeof window.IBL_refreshResponsiveTables === \'function\') {
            window.IBL_refreshResponsiveTables();
        }
    } else {
        x.classList.add(\'voting-collapsed\');
        t.setAttribute(\'aria-expanded\', \'false\');
    }
}
</script><div class="voting-category" onclick="ShowAndHideGM()"><h2 class="ibl-title voting-category-title"><button type="button" class="voting-category-toggle" aria-expanded="false" aria-controls="GM">GM of the Year</button></h2><p class="voting-category-instruction">Select THREE.</p></div><table id="GM" class="sortable ibl-data-table voting-form-table voting-collapsed"><thead><tr><th>1st</th><th>2nd</th><th>3rd</th><th>Name</th><th>Team</th></tr></thead><tbody><tr><td><input type="radio" name="GM[1]" value="Pat O&apos;Brien, Boston Celtics"></td><td><input type="radio" name="GM[2]" value="Pat O&apos;Brien, Boston Celtics"></td><td><input type="radio" name="GM[3]" value="Pat O&apos;Brien, Boston Celtics"></td><td>Pat O&apos;Brien</td><td>Boston Celtics</td></tr><tr><td><input type="radio" name="GM[1]" value="Jane Roe, Chicago Bulls"></td><td><input type="radio" name="GM[2]" value="Jane Roe, Chicago Bulls"></td><td><input type="radio" name="GM[3]" value="Jane Roe, Chicago Bulls"></td><td>Jane Roe</td><td>Chicago Bulls</td></tr></tbody></table><input type="hidden" name="teamname" value="Test Team"><button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--lg">Submit Votes!</button></div></form>';

    protected function setUp(): void
    {
        $this->view = new VotingBallotView();
    }

    public function testImplementsInterface(): void
    {
        self::assertContains(
            VotingBallotViewInterface::class,
            (array) class_implements(VotingBallotView::class)
        );
    }

    public function testRenderBallotFormContainsFormElement(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Regular Season', []);

        $this->assertStringContainsString('<form name="ASGVote"', $html);
        $this->assertStringContainsString('action="action.php"', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    public function testRenderBallotFormShowsEOYFormName(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Playoffs', []);

        $this->assertStringContainsString('<form name="EOYVote"', $html);
    }

    public function testRenderBallotFormContainsTeamLogo(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 5, 'Regular Season', []);

        $this->assertStringContainsString('images/logo/5.jpg', $html);
    }

    public function testRenderBallotFormContainsSubmitButtons(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Regular Season', []);

        $this->assertStringContainsString('Submit Votes!', $html);
    }

    public function testRenderBallotFormContainsHiddenTeamInput(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Regular Season', []);

        $this->assertStringContainsString('name="teamname"', $html);
        $this->assertStringContainsString('value="Test Team"', $html);
    }

    public function testRenderBallotFormXSSProtectsTeamName(): void
    {
        $html = $this->view->renderBallotForm('action.php', '<script>alert(1)</script>', 1, 'Regular Season', []);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderBallotFormShowsCategoryHeaders(): void
    {
        $categories = [
            [
                'code' => 'ECF',
                'title' => 'Eastern Conference Frontcourt',
                'instruction' => 'Select FOUR players.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Regular Season', $categories);

        $this->assertStringContainsString('Eastern Conference Frontcourt', $html);
        $this->assertStringContainsString('Select FOUR players.', $html);
    }

    public function testRenderBallotFormShowsGMCandidateData(): void
    {
        $categories = [
            [
                'code' => 'GM',
                'title' => 'General Manager of the Year',
                'instruction' => 'Select your top THREE choices.',
                'candidates' => [
                    [
                        'type' => 'gm',
                        'name' => 'John Doe',
                        'teamName' => 'New York Knicks',
                    ],
                ],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Playoffs', $categories);

        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('New York Knicks', $html);
        $this->assertStringContainsString('type="radio"', $html);
    }

    public function testASGVotingUsesCheckboxes(): void
    {
        $categories = [
            [
                'code' => 'GM',
                'title' => 'GM Award',
                'instruction' => 'Select FOUR.',
                'candidates' => [
                    [
                        'type' => 'gm',
                        'name' => 'John Doe',
                        'teamName' => 'Other Team',
                    ],
                ],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Regular Season', $categories);

        $this->assertStringContainsString('type="checkbox"', $html);
    }

    public function testSameTeamCandidatesGetEmptyVoteCell(): void
    {
        $categories = [
            [
                'code' => 'GM',
                'title' => 'GM of the Year',
                'instruction' => 'Select THREE.',
                'candidates' => [
                    [
                        'type' => 'gm',
                        'name' => 'Self GM',
                        'teamName' => 'My Team Name',
                    ],
                ],
            ],
        ];

        // Voter team name matches candidate's team
        $html = $this->view->renderBallotForm('action.php', 'My Team Name', 1, 'Playoffs', $categories);

        // Should NOT have a radio input for same-team candidate
        $this->assertStringNotContainsString('type="radio"', $html);
    }

    public function testAsgSelectionChecksOnlyMatchingCandidate(): void
    {
        $categories = [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => 'Ann Lee', 'teamName' => 'Team A'],
                ['type' => 'gm', 'name' => 'Bob Ray', 'teamName' => 'Team B'],
            ],
        ]];

        $html = $this->view->renderBallotForm('action.php', 'Voter Team', 1, 'Regular Season', $categories, ['GM' => ['Ann Lee, Team A']]);

        $this->assertStringContainsString('value="Ann Lee, Team A" checked', $html);
        $this->assertStringContainsString('value="Bob Ray, Team B">', $html);
        $this->assertSame(1, substr_count($html, ' checked'));
    }

    public function testEoyRankSelectionChecksOnlyThatRankRadio(): void
    {
        $categories = [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => 'Ann Lee', 'teamName' => 'Team A'],
                ['type' => 'gm', 'name' => 'Bob Ray', 'teamName' => 'Team B'],
            ],
        ]];

        $html = $this->view->renderBallotForm('action.php', 'Voter Team', 1, 'Playoffs', $categories, ['GM' => [2 => 'Ann Lee, Team A']]);

        $this->assertStringContainsString('name="GM[2]" value="Ann Lee, Team A" checked', $html);
        $this->assertStringContainsString('name="GM[1]" value="Ann Lee, Team A">', $html);
        $this->assertStringContainsString('name="GM[3]" value="Ann Lee, Team A">', $html);
        $this->assertSame(1, substr_count($html, ' checked'));
    }

    public function testSelectionMatchesRawDbValueNotEscapedValue(): void
    {
        $categories = [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => "O'Brien & Co", 'teamName' => 'Team A'],
            ],
        ]];

        $html = $this->view->renderBallotForm('action.php', 'Voter Team', 1, 'Regular Season', $categories, ['GM' => ["O'Brien & Co, Team A"]]);

        $this->assertStringContainsString('value="O&apos;Brien &amp; Co, Team A" checked', $html);
        $this->assertStringNotContainsString("O'Brien & Co, Team A", $html);
    }

    public function testUnknownSelectionValueIsNeverWrittenToHtml(): void
    {
        $categories = [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => 'Ann Lee', 'teamName' => 'Team A'],
                ['type' => 'gm', 'name' => 'Bob Ray', 'teamName' => 'Team B'],
            ],
        ]];

        $html = $this->view->renderBallotForm('action.php', 'Voter Team', 1, 'Regular Season', $categories, ['GM' => ['<script>alert(1)</script>, Team A', 'Nobody, Nowhere']]);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('Nobody', $html);
        $this->assertSame(0, substr_count($html, ' checked'));
    }

    public function testSameTeamCandidateIgnoresSelection(): void
    {
        $categories = [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => 'Ann Lee', 'teamName' => 'Team A'],
            ],
        ]];

        $html = $this->view->renderBallotForm('action.php', 'Team A', 1, 'Regular Season', $categories, ['GM' => ['Ann Lee, Team A']]);

        $this->assertStringNotContainsString(' checked', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
    }

    /**
     * Two GM candidates on non-voter teams so both ASG (checkbox) and EOY
     * (radio) input rows render. GM rows need no PlayerStats, so the fixture
     * stays free of Player\Stats construction.
     *
     * @return list<array{code: string, title: string, instruction: string, candidates: list<array<string, mixed>>}>
     */
    private function twoGmCandidates(): array
    {
        return [[
            'code' => 'GM',
            'title' => 'GM of the Year',
            'instruction' => 'Select THREE.',
            'candidates' => [
                ['type' => 'gm', 'name' => "Pat O'Brien", 'teamName' => 'Boston Celtics'],
                ['type' => 'gm', 'name' => 'Jane Roe', 'teamName' => 'Chicago Bulls'],
            ],
        ]];
    }

    /**
     * Characterization: the token line varies per call, so strip it and pin
     * the rest. The constants were captured from the pre-redisplay tree.
     * The toggle and collapsed-table fragments were updated by hand when the
     * hidden state moved to the voting-collapsed class.
     */
    public function testGetBallotHtmlIsPinnedForAsg(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Regular Season', $this->twoGmCandidates());
        $html = (string) preg_replace('/<input type="hidden" name="_csrf_token" value="[^"]*">/', '<CSRF>', $html);

        $this->assertSame(self::PINNED_ASG_HTML, $html);
    }

    public function testGetBallotHtmlIsPinnedForEoy(): void
    {
        $html = $this->view->renderBallotForm('action.php', 'Test Team', 1, 'Playoffs', $this->twoGmCandidates());
        $html = (string) preg_replace('/<input type="hidden" name="_csrf_token" value="[^"]*">/', '<CSRF>', $html);

        $this->assertSame(self::PINNED_EOY_HTML, $html);
    }

    public function testRenderShowsShowHideScript(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString('ShowAndHideMVP', $html);
    }

    public function testCategoryHeaderRendersKeyboardToggleButton(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString(
            '<h2 class="ibl-title voting-category-title"><button type="button" class="voting-category-toggle" aria-expanded="false" aria-controls="MVP">Most Valuable Player</button></h2>',
            $html
        );
    }

    public function testShowHideScriptFlipsAriaExpandedInBothBranches(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString('document.querySelector(\'[aria-controls="MVP"]\')', $html);
        $this->assertStringContainsString("t.setAttribute('aria-expanded', 'true');", $html);
        $this->assertStringContainsString("t.setAttribute('aria-expanded', 'false');", $html);
        $this->assertSame(2, substr_count($html, "setAttribute('aria-expanded'"));
    }

    public function testCategoryToggleButtonEscapesTitle(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => '<img src=x onerror=alert(1)>',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('aria-controls="MVP">&lt;img', $html);
    }

    public function testShowHideScriptCallsRefreshResponsiveTables(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString('IBL_refreshResponsiveTables', $html);
        $this->assertStringContainsString("typeof window.IBL_refreshResponsiveTables === 'function'", $html);
        $this->assertStringContainsString('window.IBL_refreshResponsiveTables();', $html);
    }

    public function testShowHideScriptTogglesCollapsedClass(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString("x.classList.contains('voting-collapsed')", $html);
        $this->assertStringContainsString("x.classList.remove('voting-collapsed');", $html);
        $this->assertStringContainsString("x.classList.add('voting-collapsed');", $html);
        $this->assertStringNotContainsString('style.display', $html);

        $removeAt = strpos($html, "x.classList.remove('voting-collapsed');");
        $refreshAt = strpos($html, 'window.IBL_refreshResponsiveTables();');
        $addAt = strpos($html, "x.classList.add('voting-collapsed');");
        $this->assertIsInt($removeAt);
        $this->assertIsInt($refreshAt);
        $this->assertIsInt($addAt);
        $this->assertLessThan($refreshAt, $removeAt, 'Reveal branch removes the class before refreshing tables.');
        $this->assertLessThan($addAt, $refreshAt, 'Else branch re-adds the class after the reveal branch.');
    }

    /**
     * Guards the table markup built in VotingBallotView::renderCandidateTable() (:135).
     */
    public function testCandidateTableStartsCollapsedWithClass(): void
    {
        $categories = [
            [
                'code' => 'MVP',
                'title' => 'Most Valuable Player',
                'instruction' => 'Select THREE.',
                'candidates' => [],
            ],
        ];

        $html = $this->view->renderBallotForm('action.php', 'Test', 1, 'Playoffs', $categories);

        $this->assertStringContainsString(
            '<table id="MVP" class="sortable ibl-data-table voting-form-table voting-collapsed">',
            $html
        );
        $this->assertStringNotContainsString('style="display:none"', $html);
    }

    public function testRenderResultsExpanderEmitsToggleAndHint(): void
    {
        $html = $this->view->renderResultsExpander('<p>RESULTS</p>');

        $this->assertStringContainsString('ShowAndHideResults', $html);
        $this->assertStringContainsString('Voting Results', $html);
        $this->assertStringContainsString(
            '<p class="voting-category-instruction"><em>Tap/click to reveal/hide results.</em></p>',
            $html
        );
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('<p>RESULTS</p>', $html);
    }

    public function testRenderResultsExpanderEmitsKeyboardToggleButton(): void
    {
        $html = $this->view->renderResultsExpander('<p>RESULTS</p>');

        $this->assertStringContainsString(
            '<div class="voting-category" onclick="ShowAndHideResults()"><h2 class="ibl-title voting-category-title"><button type="button" class="voting-category-toggle" aria-expanded="false" aria-controls="Results">Voting Results</button></h2>',
            $html
        );
    }

    public function testRenderResultsExpanderStartsCollapsed(): void
    {
        $html = $this->view->renderResultsExpander('<p>RESULTS</p>');

        // Same hidden-state convention the category candidate tables use.
        $this->assertStringContainsString('<div id="Results" class="voting-collapsed">', $html);
        $this->assertStringNotContainsString('style=', $html);
        // The expander's own toggle must target the class the container starts with.
        $this->assertStringContainsString("getElementById('Results')", $html);
        $this->assertStringContainsString("x.classList.contains('voting-collapsed')", $html);
        $this->assertLessThan(
            strpos($html, '<p>RESULTS</p>'),
            strpos($html, 'id="Results"'),
            'The results html belongs inside the collapsed container.'
        );
    }
}
