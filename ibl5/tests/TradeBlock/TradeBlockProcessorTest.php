<?php

declare(strict_types=1);

namespace Tests\TradeBlock;

use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamQueryRepositoryInterface;
use TradeBlock\Contracts\TradeBlockRepositoryInterface;
use TradeBlock\TradeBlockProcessor;

class TradeBlockProcessorTest extends TestCase
{
    /** @var list<int> */
    private array $setOnBlockCalls = [];
    /** @var list<int> */
    private array $removeFromBlockCalls = [];
    /** @var list<array{teamId: int, note: string}> */
    private array $seekingCalls = [];

    /** @var TradeBlockRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TradeBlockRepositoryInterface $repoStub;
    /** @var TeamQueryRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamQueryRepositoryInterface $teamQueryRepoStub;

    protected function setUp(): void
    {
        $this->setOnBlockCalls = [];
        $this->removeFromBlockCalls = [];
        $this->seekingCalls = [];

        $this->repoStub = self::createStub(TradeBlockRepositoryInterface::class);
        $this->repoStub->method('setOnBlock')->willReturnCallback(function (int $pid, string $note): bool {
            $this->setOnBlockCalls[] = $pid;
            return true;
        });
        $this->repoStub->method('removeFromBlock')->willReturnCallback(function (int $pid): bool {
            $this->removeFromBlockCalls[] = $pid;
            return true;
        });
        $this->repoStub->method('upsertSeekingNote')->willReturnCallback(function (int $teamId, string $note): bool {
            $this->seekingCalls[] = ['teamId' => $teamId, 'note' => $note];
            return true;
        });

        $this->teamQueryRepoStub = self::createStub(TeamQueryRepositoryInterface::class);
    }

    /**
     * @param list<int> $pids
     */
    private function stubRoster(array $pids): void
    {
        $rows = array_map(static fn (int $pid): array => ['pid' => $pid, 'name' => "P{$pid}"], $pids);
        $this->teamQueryRepoStub->method('getRosterUnderContractOrderedByName')->willReturn($rows);
    }

    private function makeProcessor(): TradeBlockProcessor
    {
        return new TradeBlockProcessor($this->repoStub, $this->teamQueryRepoStub);
    }

    public function testCheckedPlayerIsSetAndUncheckedIsRemoved(): void
    {
        $this->stubRoster([10, 11]);

        $result = $this->makeProcessor()->processEdit(1, [10], [10 => 'note'], '');

        self::assertContains(10, $this->setOnBlockCalls);
        self::assertContains(11, $this->removeFromBlockCalls);
        self::assertNotContains(11, $this->setOnBlockCalls);
        self::assertTrue($result['success']);
        self::assertSame('block_updated', $result['result'] ?? '');
    }

    public function testForgedCrossTeamPidNeverReachesAWrite(): void
    {
        // Roster is [10, 11]; pid 999 belongs to another team.
        $this->stubRoster([10, 11]);

        $this->makeProcessor()->processEdit(1, [999], [], '');

        self::assertNotContains(999, $this->setOnBlockCalls, 'forged pid must never be set on block');
        self::assertNotContains(999, $this->removeFromBlockCalls, 'forged pid must never be touched');
        // The roster pids are still reconciled (both unchecked => removed).
        self::assertContains(10, $this->removeFromBlockCalls);
        self::assertContains(11, $this->removeFromBlockCalls);
    }

    public function testEmptySeekingNoteStillUpserts(): void
    {
        $this->stubRoster([10]);

        $this->makeProcessor()->processEdit(7, [], [], '');

        self::assertCount(1, $this->seekingCalls);
        self::assertSame(7, $this->seekingCalls[0]['teamId']);
        self::assertSame('', $this->seekingCalls[0]['note']);
    }
}
