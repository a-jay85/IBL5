<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->expectExceptionMessageIsOrContains('Invalid vote column');

        $this->repository->fetchAllStarTotals(['east_f1; DROP TABLE']);
    }

    /**
     * Literal copy of the vote-column allowlist (independent oracle).
     *
     * @return array<string, array{string}>
     */
    public static function allowlistedVoteColumnProvider(): array
    {
        return [
            'east_f1' => ['east_f1'],
            'east_f2' => ['east_f2'],
            'east_f3' => ['east_f3'],
            'east_f4' => ['east_f4'],
            'east_b1' => ['east_b1'],
            'east_b2' => ['east_b2'],
            'east_b3' => ['east_b3'],
            'east_b4' => ['east_b4'],
            'west_f1' => ['west_f1'],
            'west_f2' => ['west_f2'],
            'west_f3' => ['west_f3'],
            'west_f4' => ['west_f4'],
            'west_b1' => ['west_b1'],
            'west_b2' => ['west_b2'],
            'west_b3' => ['west_b3'],
            'west_b4' => ['west_b4'],
            'mvp_1' => ['mvp_1'],
            'mvp_2' => ['mvp_2'],
            'mvp_3' => ['mvp_3'],
            'six_1' => ['six_1'],
            'six_2' => ['six_2'],
            'six_3' => ['six_3'],
            'roy_1' => ['roy_1'],
            'roy_2' => ['roy_2'],
            'roy_3' => ['roy_3'],
            'gm_1' => ['gm_1'],
            'gm_2' => ['gm_2'],
            'gm_3' => ['gm_3'],
        ];
    }

    #[DataProvider('allowlistedVoteColumnProvider')]
    public function testFetchAllStarTotalsSplicesEachAllowlistedColumn(string $column): void
    {
        $this->mockDb->setVotingResultsQueue([[]]);

        $this->repository->fetchAllStarTotals([$column]);

        $this->assertQueryExecuted('SELECT ' . $column . ' AS name FROM ibl_votes_ASG');
    }

    #[DataProvider('allowlistedVoteColumnProvider')]
    public function testFetchEndOfYearTotalsSplicesEachAllowlistedColumn(string $column): void
    {
        $this->mockDb->setVotingResultsQueue([[]]);

        $this->repository->fetchEndOfYearTotals([$column => 3]);

        $this->assertQueryExecuted('SELECT ' . $column . ' AS name, 3 AS score FROM ibl_votes_EOY');
    }

    public function testFetchEndOfYearTotalsRejectsNonAllowlistedColumn(): void
    {
        try {
            $this->repository->fetchEndOfYearTotals(['mvp_1; DROP TABLE x' => 3]);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid vote column', $e->getMessage());
            self::assertSame([], $this->mockDb->getExecutedQueries());
        }
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
