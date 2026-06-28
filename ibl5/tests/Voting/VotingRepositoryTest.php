<?php

declare(strict_types=1);

namespace Tests\Voting;

use Tests\WideUnit\WideUnitTestCase;
use Voting\VotingRepository;

class VotingRepositoryTest extends WideUnitTestCase
{
    private VotingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new VotingRepository($this->mockDb);
    }

    public function testFetchAllStarTotalsAggregatesAndResolvesPid(): void
    {
        $this->mockDb->setVotingResultsQueue([[['name' => 'LeBron James, Sting', 'votes' => 5]]]);
        $this->mockDb->setMockData([['pid' => 99, 'name' => 'LeBron James']]);

        $result = $this->repository->fetchAllStarTotals(['east_f1']);

        $this->assertCount(1, $result);
        $this->assertSame('LeBron James, Sting', $result[0]['name']);
        $this->assertSame(5, $result[0]['votes']);
        $this->assertSame(99, $result[0]['pid']);
    }

    public function testFetchAllStarTotalsBlankNameUsesPlaceholder(): void
    {
        $this->mockDb->setVotingResultsQueue([[['name' => '  ', 'votes' => 3]]]);

        $result = $this->repository->fetchAllStarTotals(['east_f1']);

        $this->assertCount(1, $result);
        $this->assertSame(VotingRepository::BLANK_BALLOT_LABEL, $result[0]['name']);
        $this->assertSame(0, $result[0]['pid']);
    }

    public function testFetchEndOfYearTotalsAppliesWeights(): void
    {
        $this->mockDb->setVotingResultsQueue([[['name' => 'MVP Guy, Team', 'votes' => 9]]]);
        $this->mockDb->setMockData([['pid' => 7, 'name' => 'MVP Guy']]);

        $result = $this->repository->fetchEndOfYearTotals(['mvp_1' => 3]);

        $this->assertCount(1, $result);
        $this->assertSame(9, $result[0]['votes']);
        $this->assertSame(7, $result[0]['pid']);
    }

    public function testFetchAllStarTotalsRejectsNonAllowlistedColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vote column');

        $this->repository->fetchAllStarTotals(['east_f1; DROP TABLE']);
    }

    public function testSaveEoyVoteAndMarkCooldownTargetCorrectTables(): void
    {
        $ballot = [
            'mvp_1' => 'x', 'mvp_2' => 'x', 'mvp_3' => 'x',
            'six_1' => 'x', 'six_2' => 'x', 'six_3' => 'x',
            'roy_1' => 'x', 'roy_2' => 'x', 'roy_3' => 'x',
            'gm_1' => 'x', 'gm_2' => 'x', 'gm_3' => 'x',
        ];

        $this->repository->saveEoyVote('Team', $ballot);
        $this->assertQueryExecuted('UPDATE ibl_votes_EOY');

        $this->repository->markEoyVoteCast('Team');
        $this->assertQueryExecuted('eoy_vote = NOW()');
    }

    public function testSaveAsgVoteAndMarkCooldownTargetCorrectTables(): void
    {
        $ballot = [
            'east_f1' => 'x', 'east_f2' => 'x', 'east_f3' => 'x', 'east_f4' => 'x',
            'east_b1' => 'x', 'east_b2' => 'x', 'east_b3' => 'x', 'east_b4' => 'x',
            'west_f1' => 'x', 'west_f2' => 'x', 'west_f3' => 'x', 'west_f4' => 'x',
            'west_b1' => 'x', 'west_b2' => 'x', 'west_b3' => 'x', 'west_b4' => 'x',
        ];

        $this->repository->saveAsgVote('Team', $ballot);
        $this->assertQueryExecuted('UPDATE ibl_votes_ASG');

        $this->repository->markAsgVoteCast('Team');
        $this->assertQueryExecuted('asg_vote = NOW()');
    }

    public function testFetchPlayerIdsByNamesMapsAndShortCircuits(): void
    {
        $this->mockDb->setMockData([['pid' => 1, 'name' => 'A'], ['pid' => 2, 'name' => 'B']]);

        $result = $this->repository->fetchPlayerIdsByNames(['A', 'B']);

        $this->assertSame(['A' => 1, 'B' => 2], $result);

        $this->mockDb->clearQueries();

        $empty = $this->repository->fetchPlayerIdsByNames([]);
        $this->assertSame([], $empty);
        $this->assertQueryNotExecuted('ibl_plr');
    }
}
