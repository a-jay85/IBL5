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
            'mvp_1' => ['mvp_1'], 'mvp_2' => ['mvp_2'], 'mvp_3' => ['mvp_3'],
            'six_1' => ['six_1'], 'six_2' => ['six_2'], 'six_3' => ['six_3'],
            'roy_1' => ['roy_1'], 'roy_2' => ['roy_2'], 'roy_3' => ['roy_3'],
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
            'gm_1' => ['gm_1'], 'gm_2' => ['gm_2'], 'gm_3' => ['gm_3'],
        ];
    }

    // ==================== EOY: Empty Fields ====================

    public function testEoyRejectsEmptyMvp(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['mvp_1'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select an MVP', $result->errors[0]);
    }

    public function testEoyRejectsEmptySixthMan(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['six_2'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a 6th Man', $result->errors[0]);
    }

    public function testEoyRejectsEmptyRoy(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['roy_3'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a Rookie of the Year', $result->errors[0]);
    }

    public function testEoyRejectsEmptyGm(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['gm_1'] = '';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('you must select a GM of the Year', $result->errors[0]);
    }

    // ==================== EOY: Duplicates ====================

    public function testEoyRejectsDuplicateMvp(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['mvp_1'] = 'Same Player, Knicks';
        $ballot['mvp_2'] = 'Same Player, Knicks';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple MVP slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateSixthMan(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['six_1'] = 'Same Bench, Hawks';
        $ballot['six_3'] = 'Same Bench, Hawks';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple Sixth Man of the Year slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateRoy(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['roy_2'] = 'Same Rookie, Nets';
        $ballot['roy_3'] = 'Same Rookie, Nets';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple Rookie of the Year slots', $result->errors[0]);
    }

    public function testEoyRejectsDuplicateGm(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['gm_1'] = 'Same GM, Celtics';
        $ballot['gm_2'] = 'Same GM, Celtics';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitEoyVote('Other Team', $ballot);

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('same player for multiple GM of the Year slots', $result->errors[0]);
    }

    // ==================== EOY: Multi-Error Collection ====================

    public function testEoyCollectsMultipleErrors(): void
    {
        $ballot = self::validEoyBallot();
        $ballot['mvp_1'] = '';  // empty
        $ballot['six_1'] = ''; // empty
        $ballot['roy_1'] = 'Same Rookie, Nets';
        $ballot['roy_2'] = 'Same Rookie, Nets'; // duplicate

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
        $ballot['mvp_1'] = ''; // invalid

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
        $ballot['east_f1'] = 'Star Player, My Team';

        $service = new VotingSubmissionService($this->createStub(VotingRepositoryInterface::class));
        $result = $service->submitAsgVote('My Team', $ballot, self::validAsgRawPost());

        $this->assertTrue($result->hasErrors());
        $this->assertStringContainsString('cannot vote for your own player', $result->errors[0]);
    }

    // ==================== ASG: Missing Votes ====================

    public function testAsgRejectsMissingVoteInCategory(): void
    {
        $ballot = self::validAsgBallot();
        $ballot['west_f3'] = '';

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
        $ballot['east_f1'] = 'Star, My Team'; // self-vote
        $ballot['west_b2'] = '';              // missing

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
        $ballot['east_f1'] = ''; // invalid

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
