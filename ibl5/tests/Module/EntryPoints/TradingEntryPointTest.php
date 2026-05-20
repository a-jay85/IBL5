<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * Trading/index.php defines global functions (tradeoffer, tradereview,
 * reviewtrade, offertrade) that cannot be redeclared, and is_user() has
 * a static cache. Each test runs in a separate process.
 *
 * POST handlers (offertrade submit, reviewtrade accept/reject) call
 * HtmxHelper::redirect() → exit() and are skipped. Covered by
 * Plans 03b/03c real-DB tests + E2E flows.
 *
 * Unauthenticated GET paths call loginbox() → die() and are skipped
 * except for roster-preview-api which returns JSON without die().
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TradingEntryPointTest extends ModuleEntryPointTestCase
{
    private function seedSeasonMocks(string $phase = 'Free Agency'): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => $phase],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
    }

    public function testDefaultOpRendersTradeReview(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => ''], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpReviewtradeRendersReviewView(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => 'reviewtrade'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpReviewtradeWithResultParam(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => 'reviewtrade', 'result' => 'success'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpReviewtradeWithErrorParam(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => 'reviewtrade', 'error' => 'invalid'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOpRosterPreviewApiReturnsJson(): void
    {
        $this->authenticateAs('testgm');
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['teamid' => '1', 'op' => 'roster-preview-api'], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
    }

    public function testOpRosterPreviewApiWithoutAuthReturnsEmptyJson(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => 'roster-preview-api'], [], [
            'user' => '',
        ]);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('', $decoded['html']);
    }

    public function testUnknownOpFallsToDefault(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Trading', ['op' => 'bogus'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testOffertradeWithSessionFormData(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);
        $_SESSION['tradeFormData'] = ['players' => [1, 2]];

        $output = $this->runModule('Trading', ['op' => 'offertrade', 'partner' => ''], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }
}
