<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingSubmissionViewInterface;
use Voting\VotingSubmissionView;

/**
 * @covers \Voting\VotingSubmissionView
 */
final class VotingSubmissionViewTest extends TestCase
{
    private VotingSubmissionView $view;

    protected function setUp(): void
    {
        $this->view = new VotingSubmissionView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(VotingSubmissionViewInterface::class, $this->view);
    }

    // ==================== Error Rendering ====================

    public function testRenderErrorsUsesErrorCssClass(): void
    {
        $html = $this->view->renderErrors(['Some error message']);

        $this->assertStringContainsString('voting-submission-error', $html);
        $this->assertStringContainsString('Some error message', $html);
    }

    public function testRenderErrorsDoesNotUseFontTag(): void
    {
        $html = $this->view->renderErrors(['Test error']);

        $this->assertStringNotContainsString('<font', $html);
    }

    public function testRenderErrorsRendersMultipleErrors(): void
    {
        $html = $this->view->renderErrors(['Error one', 'Error two', 'Error three']);

        $this->assertStringContainsString('Error one', $html);
        $this->assertStringContainsString('Error two', $html);
        $this->assertStringContainsString('Error three', $html);
        $this->assertSame(3, substr_count($html, 'voting-submission-error'));
    }

    public function testRenderErrorsEscapesHtml(): void
    {
        $html = $this->view->renderErrors(['<script>alert("xss")</script>']);

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ==================== EOY Confirmation ====================

    public function testEoyConfirmationShowsTeamName(): void
    {
        $ballot = self::validEoyBallot();
        $html = $this->view->renderEoyConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('Test Team', $html);
    }

    public function testEoyConfirmationShowsThankYouMessage(): void
    {
        $ballot = self::validEoyBallot();
        $html = $this->view->renderEoyConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('Thank you for voting', $html);
    }

    public function testEoyConfirmationShowsAllTwelveChoices(): void
    {
        $ballot = self::validEoyBallot();
        $html = $this->view->renderEoyConfirmation('Test Team', $ballot);

        foreach ($ballot as $value) {
            $this->assertStringContainsString(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        }
    }

    public function testEoyConfirmationEscapesPlayerNames(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['mvp_1'] = '<script>xss</script>, Knicks';
        $html = $this->view->renderEoyConfirmation('Test Team', $ballot);

        $this->assertStringNotContainsString('<script>xss</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEoyConfirmationUsesSuccessCssClass(): void
    {
        $ballot = self::validEoyBallot();
        $html = $this->view->renderEoyConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('voting-submission-success', $html);
    }

    // ==================== ASG Confirmation ====================

    public function testAsgConfirmationShowsTeamName(): void
    {
        $ballot = self::validAsgBallot();
        $html = $this->view->renderAsgConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('Test Team', $html);
    }

    public function testAsgConfirmationShowsThankYouMessage(): void
    {
        $ballot = self::validAsgBallot();
        $html = $this->view->renderAsgConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('Thank you for voting', $html);
    }

    public function testAsgConfirmationShowsAllSixteenChoices(): void
    {
        $ballot = self::validAsgBallot();
        $html = $this->view->renderAsgConfirmation('Test Team', $ballot);

        foreach ($ballot as $value) {
            $this->assertStringContainsString(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        }
    }

    public function testAsgConfirmationShowsCategoryLabels(): void
    {
        $ballot = self::validAsgBallot();
        $html = $this->view->renderAsgConfirmation('Test Team', $ballot);

        $this->assertStringContainsString('Eastern Frontcourt', $html);
        $this->assertStringContainsString('Eastern Backcourt', $html);
        $this->assertStringContainsString('Western Frontcourt', $html);
        $this->assertStringContainsString('Western Backcourt', $html);
    }

    // ==================== Fixtures ====================

    /**
     * @return array{mvp_1: string, mvp_2: string, mvp_3: string, six_1: string, six_2: string, six_3: string, roy_1: string, roy_2: string, roy_3: string, gm_1: string, gm_2: string, gm_3: string}
     */
    private static function validEoyBallot(): array
    {
        return [
            'mvp_1' => 'Player A, Knicks',
            'mvp_2' => 'Player B, Lakers',
            'mvp_3' => 'Player C, Celtics',
            'six_1' => 'Player D, Hawks',
            'six_2' => 'Player E, Nets',
            'six_3' => 'Player F, Heat',
            'roy_1' => 'Player G, Spurs',
            'roy_2' => 'Player H, Bulls',
            'roy_3' => 'Player I, Jazz',
            'gm_1' => 'GM Alpha, Suns',
            'gm_2' => 'GM Beta, Sixers',
            'gm_3' => 'GM Gamma, Bucks',
        ];
    }

    /**
     * @return array{east_f1: string, east_f2: string, east_f3: string, east_f4: string, east_b1: string, east_b2: string, east_b3: string, east_b4: string, west_f1: string, west_f2: string, west_f3: string, west_f4: string, west_b1: string, west_b2: string, west_b3: string, west_b4: string}
     */
    private static function validAsgBallot(): array
    {
        return [
            'east_f1' => 'EF1, Knicks', 'east_f2' => 'EF2, Hawks',
            'east_f3' => 'EF3, Celtics', 'east_f4' => 'EF4, Nets',
            'east_b1' => 'EB1, Heat', 'east_b2' => 'EB2, Bulls',
            'east_b3' => 'EB3, Pacers', 'east_b4' => 'EB4, Cavs',
            'west_f1' => 'WF1, Lakers', 'west_f2' => 'WF2, Suns',
            'west_f3' => 'WF3, Nuggets', 'west_f4' => 'WF4, Clippers',
            'west_b1' => 'WB1, Warriors', 'west_b2' => 'WB2, Grizzlies',
            'west_b3' => 'WB3, Mavs', 'west_b4' => 'WB4, Thunder',
        ];
    }
}
