<?php

declare(strict_types=1);

namespace Tests\DraftHistory;

use DraftHistory\DraftHistoryRepository;
use PHPUnit\Framework\TestCase;

final class DraftHistoryRepositoryTest extends TestCase
{
    public function testGetFirstDraftYearReturns1988(): void
    {
        $mockDb = $this->createStub(\mysqli::class);
        $repository = new DraftHistoryRepository($mockDb);

        $this->assertSame(1988, $repository->getFirstDraftYear());
    }
}
