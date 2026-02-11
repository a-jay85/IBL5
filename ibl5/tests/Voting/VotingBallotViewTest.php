<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\VotingBallotView;
use Voting\Contracts\VotingBallotViewInterface;

class VotingBallotViewTest extends TestCase
{
    private VotingBallotView $view;

    protected function setUp(): void
    {
        $this->view = new VotingBallotView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(VotingBallotViewInterface::class, $this->view);
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
}
