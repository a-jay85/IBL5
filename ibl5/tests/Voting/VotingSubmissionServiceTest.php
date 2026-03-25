<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Voting\Contracts\VotingRepositoryInterface;
use Voting\SubmissionResult;
use Voting\VotingSubmissionService;

/**
 * @covers \Voting\VotingSubmissionService
 * @covers \Voting\SubmissionResult
 */
final class VotingSubmissionServiceTest extends TestCase
{
    // ==================== EOY: Self-Vote ====================

    #[DataProvider('eoyPlayerSelfVoteFieldProvider')]
    public function testEoyRejectsSelfVoteForOwnPlayer(string $field): void
    {
        $ballot = self::validEoyBallot();
        $ballot[$field] = 'Star Player, My Team';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('My Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('cannot vote for your own player', $result->errors[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function eoyPlayerSelfVoteFieldProvider(): array
    {
        return [
            'MVP_1' => ['MVP_1'], 'MVP_2' => ['MVP_2'], 'MVP_3' => ['MVP_3'],
            'Six_1' => ['Six_1'], 'Six_2' => ['Six_2'], 'Six_3' => ['Six_3'],
            'ROY_1' => ['ROY_1'], 'ROY_2' => ['ROY_2'], 'ROY_3' => ['ROY_3'],
        ];
    }

    #[DataProvider('eoyGmSelfVoteFieldProvider')]
    public function testEoyRejectsSelfVoteForGm(string $field): void
    {
        $ballot = self::validEoyBallot();
        $ballot[$field] = 'John Doe, My Team';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('My Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('cannot vote for yourself', $result->errors[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function eoyGmSelfVoteFieldProvider(): array
    {
        return [
            'GM_1' => ['GM_1'], 'GM_2' => ['GM_2'], 'GM_3' => ['GM_3'],
        ];
    }

    // ==================== EOY: Empty Fields ====================

    public function testEoyRejectsEmptyMvp(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['MVP_1'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select an MVP', $result->errors[0]);
    }

    public function testEoyRejectsEmptySixthMan(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['Six_2'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a 6th Man', $result->errors[0]);
    }

    public function testEoyRejectsEmptyRoy(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['ROY_3'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a Rookie of the Year', $result->errors[0]);
    }

    public function testEoyRejectsEmptyGm(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['GM_1'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a GM of the Year', $result->errors[0]);
    }

    // ==================== EOY: Duplicates ====================

    public function testEoyRejectsDuplicateMvp(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['MVP_1'] = 'Same Player, Knicks';
        $ballot['MVP_2'] = 'Same Player, Knicks';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple MVP slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateSixthMan(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['Six_1'] = 'Same Bench, Hawks';
        $ballot['Six_3'] = 'Same Bench, Hawks';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple Sixth Man of the Year slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateRoy(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['ROY_2'] = 'Same Rookie, Nets';
        $ballot['ROY_3'] = 'Same Rookie, Nets';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple Rookie of the Year slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateGm(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['GM_1'] = 'Same GM, Celtics';
        $ballot['GM_2'] = 'Same GM, Celtics';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple GM of the Year slots', $result->errors[0]);
    }

    // ==================== EOY: Multi-Error Collection ====================

    public function testEoyCollectsMultipleErrors(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['MVP_1'] = '';  // empty
        $ballot['Six_1'] = ''; // empty
        $ballot['ROY_1'] = 'Same Rookie, Nets';
        $ballot['ROY_2'] = 'Same Rookie, Nets'; // duplicate

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertGreaterThanOrEqual(3, count($result->errors));
    }

    // ==================== EOY: Success ====================

    public function testEoySuccessSavesVoteAndMarksTimestamp(): void
    {
        $ballot = self::validEoyBallot();
        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->once())->method('saveEoyVote')->with('Test Team', $ballot);
        $repo->expects($this->once())->method('markEoyVoteCast')->with('Test Team');

        $service = new VotingSubmissionService($repo);
        $result = $service->submitEoyVote('Test Team', $ballot);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testEoyEmptyTeamNameDoesNotTriggerSelfVoteErrors(): void
    {
        $ballot = self::validEoyBallot();

        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->once())->method('saveEoyVote');
        $repo->expects($this->once())->method('markEoyVoteCast');

        $service = new VotingSubmissionService($repo);
        $result = $service->submitEoyVote('', $ballot);

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testAsgEmptyTeamNameDoesNotTriggerSelfVoteErrors(): void
    {
        $ballot = self::validAsgBallot();

        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->once())->method('saveAsgVote');
        $repo->expects($this->once())->method('markAsgVoteCast');

        $service = new VotingSubmissionService($repo);
        $result = $service->submitAsgVote('', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testEoyErrorsPreventsRepositorySave(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['MVP_1'] = ''; // invalid

        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->never())->method('saveEoyVote');
        $repo->expects($this->never())->method('markEoyVoteCast');

        $service = new VotingSubmissionService($repo);
        $service->submitEoyVote('Other Team', $ballot);
    }

    // ==================== ASG: Self-Vote ====================

    public function testAsgRejectsSelfVote(): void
    {
        $ballot = self::validAsgBallot();
        $ballot['East_F1'] = 'Star Player, My Team';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitAsgVote('My Team', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('cannot vote for your own player', $result->errors[0]);
    }

    // ==================== ASG: Missing Votes ====================

    public function testAsgRejectsMissingVoteInCategory(): void
    {
        $ballot = self::validAsgBallot();
        $ballot['West_F3'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitAsgVote('Other Team', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('less than FOUR', $result->errors[0]);
        $this->assertStringContainsString('Western Frontcourt', $result->errors[0]);
    }

    // ==================== ASG: Too Many Votes ====================

    public function testAsgRejectsTooManyVotesInCategory(): void
    {
        $ballot = self::validAsgBallot();
        $rawPost = self::validAsgRawPost();
        $rawPost['ECF'] = ['p1', 'p2', 'p3', 'p4', 'p5']; // 5 is too many

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitAsgVote('Other Team', $ballot, $rawPost);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('more than four', $result->errors[0]);
        $this->assertStringContainsString('Eastern Frontcourt', $result->errors[0]);
    }

    // ==================== ASG: Multi-Error Collection ====================

    public function testAsgCollectsMultipleErrors(): void
    {
        $ballot = self::validAsgBallot();
        $ballot['East_F1'] = 'Star, My Team'; // self-vote
        $ballot['West_B2'] = '';              // missing

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitAsgVote('My Team', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->hasErrors());
        $this->assertGreaterThanOrEqual(2, count($result->errors));
    }

    // ==================== ASG: Success ====================

    public function testAsgSuccessSavesVoteAndMarksTimestamp(): void
    {
        $ballot = self::validAsgBallot();
        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->once())->method('saveAsgVote')->with('Test Team', $ballot);
        $repo->expects($this->once())->method('markAsgVoteCast')->with('Test Team');

        $service = new VotingSubmissionService($repo);
        $result = $service->submitAsgVote('Test Team', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->success);
        $this->assertFalse($result->hasErrors());
    }

    public function testAsgErrorsPreventsRepositorySave(): void
    {
        $ballot = self::validAsgBallot();
        $ballot['East_F1'] = ''; // invalid

        $repo = $this->createMock(VotingRepositoryInterface::class);
        $repo->expects($this->never())->method('saveAsgVote');
        $repo->expects($this->never())->method('markAsgVoteCast');

        $service = new VotingSubmissionService($repo);
        $service->submitAsgVote('Other Team', $ballot, self::validAsgRawPost());
    }

    // ==================== SubmissionResult Tests ====================

    public function testSubmissionResultSuccessHasNoErrors(): void
    {
        $result = SubmissionResult::success();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->errors);
        $this->assertFalse($result->hasErrors());
    }

    public function testSubmissionResultWithErrorsIsNotSuccess(): void
    {
        $result = SubmissionResult::withErrors(['Error 1', 'Error 2']);

        $this->assertFalse($result->success);
        $this->assertCount(2, $result->errors);
        $this->assertTrue($result->hasErrors());
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

    /**
     * @return array<string, list<string>>
     */
    private static function validAsgRawPost(): array
    {
        return [
            'ECF' => ['EF1, Knicks', 'EF2, Hawks', 'EF3, Celtics', 'EF4, Nets'],
            'ECB' => ['EB1, Heat', 'EB2, Bulls', 'EB3, Pacers', 'EB4, Cavs'],
            'WCF' => ['WF1, Lakers', 'WF2, Suns', 'WF3, Nuggets', 'WF4, Clippers'],
            'WCB' => ['WB1, Warriors', 'WB2, Grizzlies', 'WB3, Mavs', 'WB4, Thunder'],
        ];
    }
}
