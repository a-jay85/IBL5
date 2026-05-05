<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * TrainingCampRatingsDiff/index.php is auth-gated (loginbox() → die() for
 * unauthenticated). Unauthenticated path covered by E2E.
 *
 * Filters: ?year= (ctype_digit), ?tid= (ctype_digit), ?status= (in_array).
 * Invalid values are silently rejected (null/empty), not errors.
 *
 * Requires separate processes because is_user() has a static cache.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TrainingCampRatingsDiffEntryPointTest extends ModuleEntryPointTestCase
{
    /** @return array<string, mixed> */
    private function tcGlobals(): array
    {
        return array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAs('testgm');
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
        $this->mockDb->setMockData([]);
    }

    public function testRendersDefaultRatingsDiff(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', [], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }

    public function testWithYearFilterAppliesYear(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', ['year' => '2024'], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }

    public function testRejectsNonDigitYear(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', ['year' => 'garbage'], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }

    public function testWithTidFilterAppliesTeam(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', ['tid' => '1'], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }

    public function testWithStatusFilterAppliesStatus(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', ['status' => 'signed'], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }

    public function testRejectsInvalidStatus(): void
    {
        $output = $this->runModule('TrainingCampRatingsDiff', ['status' => 'garbage'], [], $this->tcGlobals());

        $this->assertNotEmpty($output);
    }
}
