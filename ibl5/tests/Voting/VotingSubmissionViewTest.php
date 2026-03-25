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
        $ballot['MVP_1'] = '<script>xss</script>, Knicks';
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
     * @return array{MVP_1: string, MVP_2: string, MVP_3: string, Six_1: string, Six_2: string, Six_3: string, ROY_1: string, ROY_2: string, ROY_3: string, GM_1: string, GM_2: string, GM_3: string}
     */
    private static function validEoyBallot(): array
    {
        return [
            'MVP_1' => 'Player A, Knicks',
            'MVP_2' => 'Player B, Lakers',
            'MVP_3' => 'Player C, Celtics',
            'Six_1' => 'Player D, Hawks',
            'Six_2' => 'Player E, Nets',
            'Six_3' => 'Player F, Heat',
            'ROY_1' => 'Player G, Spurs',
            'ROY_2' => 'Player H, Bulls',
            'ROY_3' => 'Player I, Jazz',
            'GM_1' => 'GM Alpha, Suns',
            'GM_2' => 'GM Beta, Sixers',
            'GM_3' => 'GM Gamma, Bucks',
        ];
    }

    /**
     * @return array{East_F1: string, East_F2: string, East_F3: string, East_F4: string, East_B1: string, East_B2: string, East_B3: string, East_B4: string, West_F1: string, West_F2: string, West_F3: string, West_F4: string, West_B1: string, West_B2: string, West_B3: string, West_B4: string}
     */
    private static function validAsgBallot(): array
    {
        return [
            'East_F1' => 'EF1, Knicks', 'East_F2' => 'EF2, Hawks',
            'East_F3' => 'EF3, Celtics', 'East_F4' => 'EF4, Nets',
            'East_B1' => 'EB1, Heat', 'East_B2' => 'EB2, Bulls',
            'East_B3' => 'EB3, Pacers', 'East_B4' => 'EB4, Cavs',
            'West_F1' => 'WF1, Lakers', 'West_F2' => 'WF2, Suns',
            'West_F3' => 'WF3, Nuggets', 'West_F4' => 'WF4, Clippers',
            'West_B1' => 'WB1, Warriors', 'West_B2' => 'WB2, Grizzlies',
            'West_B3' => 'WB3, Mavs', 'West_B4' => 'WB4, Thunder',
        ];
    }
}
