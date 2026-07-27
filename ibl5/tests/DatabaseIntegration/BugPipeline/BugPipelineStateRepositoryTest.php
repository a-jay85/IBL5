<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use BugPipeline\BugPipelineStateRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class BugPipelineStateRepositoryTest extends DatabaseTestCase
{
    private BugPipelineStateRepository $repo;

    // Representative snowflake fixtures — real Discord IDs are 17–19 digits
    private const CHANNEL = '200000000000000002';
    private const MSG_ID  = '300000000000000003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BugPipelineStateRepository($this->db);
    }

    // ── upsertPipelineState / findPipelineState ────────────────────────────────

    public function testFindPipelineStateReturnsNullWhenNoRow(): void
    {
        self::assertNull($this->repo->findPipelineState('999999999999999999'));
    }

    public function testUpsertPipelineStateInsertsAndReturnsString(): void
    {
        $this->repo->upsertPipelineState(self::CHANNEL, self::MSG_ID);
        $cursor = $this->repo->findPipelineState(self::CHANNEL);
        self::assertSame(self::MSG_ID, $cursor);
    }

    public function testUpsertPipelineStateIsMonotonic(): void
    {
        // Lower ID first, then higher — cursor advances
        $lower  = '200000000000000001';
        $higher = '300000000000000002';
        $this->repo->upsertPipelineState(self::CHANNEL, $lower);
        $this->repo->upsertPipelineState(self::CHANNEL, $higher);
        self::assertSame($higher, $this->repo->findPipelineState(self::CHANNEL));

        // Replaying older message must NOT regress the cursor
        $this->repo->upsertPipelineState(self::CHANNEL, $lower);
        self::assertSame($higher, $this->repo->findPipelineState(self::CHANNEL));
    }

    public function testUpsertPipelineStateKeepsHigherWatermarkWhenLowerArrivesFirst(): void
    {
        // Seed with a higher snowflake first, then attempt a lower one.
        // GREATEST() must keep the cursor at the higher value.
        $channel = '200000000000000099';
        $lower   = '200000000000000001';
        $higher  = '300000000000000002';
        $this->repo->upsertPipelineState($channel, $higher);
        $this->repo->upsertPipelineState($channel, $lower);
        self::assertSame($higher, $this->repo->findPipelineState($channel));
    }
}
