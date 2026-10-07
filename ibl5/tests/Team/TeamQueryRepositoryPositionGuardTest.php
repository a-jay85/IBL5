<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\TeamQueryRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Rejected-position behavior of the starter lookups: the depth column comes from a
 * closed const map, so anything outside PG/SG/SF/PF/C throws before any SQL is built.
 */
class TeamQueryRepositoryPositionGuardTest extends TestCase
{
    private MockDatabase $db;
    private TeamQueryRepository $repository;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new TeamQueryRepository($this->db);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownPositionProvider(): array
    {
        return [
            'unknown code' => ['XX'],
            'empty string' => [''],
            'single guard letter' => ['G'],
            'trailing space' => ['PG '],
            'injection-shaped' => ['pg_depth = 1 OR 1=1 -- '],
        ];
    }

    #[DataProvider('unknownPositionProvider')]
    public function testLastSimStarterLookupRejectsUnknownPosition(string $position): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid position');

        try {
            $this->repository->getLastSimStarterPlayerIDForPosition(1, $position);
        } finally {
            self::assertSame([], $this->db->getPreparedQueries());
            self::assertSame([], $this->db->getExecutedQueries());
        }
    }

    #[DataProvider('unknownPositionProvider')]
    public function testDepthChartStarterLookupRejectsUnknownPosition(string $position): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid position');

        try {
            $this->repository->getCurrentlySetStarterPlayerIDForPosition(1, $position);
        } finally {
            self::assertSame([], $this->db->getPreparedQueries());
            self::assertSame([], $this->db->getExecutedQueries());
        }
    }
}
