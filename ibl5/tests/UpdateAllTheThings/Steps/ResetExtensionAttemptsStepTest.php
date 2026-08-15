<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Steps\ResetExtensionAttemptsStep;
use Tests\WideUnit\Mocks\MockDatabase;

class ResetExtensionAttemptsStepTest extends TestCase
{
    public function testGetLabelReturnsExpectedLabel(): void
    {
        $mockDb = new MockDatabase();
        $step = new ResetExtensionAttemptsStep($mockDb);

        $this->assertSame('Extension attempts reset', $step->getLabel());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $step = new ResetExtensionAttemptsStep($mockDb);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Extension attempts reset', $result->label);
    }

    public function testExecuteRunsUpdateToTeamInfoTable(): void
    {
        $mockDb = new MockDatabase();
        (new ResetExtensionAttemptsStep($mockDb))->execute();

        $queries = $mockDb->getExecutedQueries();
        $found = array_filter($queries, static fn (string $q): bool => str_contains($q, 'ibl_team_info'));

        $this->assertNotEmpty($found, 'Expected at least one query targeting ibl_team_info');
    }
}
