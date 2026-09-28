<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Module\ModuleRedirect;
use Records\RecordsController;

/**
 * Integration tests for modules/FranchiseRecordBook/index.php entry point.
 *
 * op=api serves the HTMX fragment; every other request is a body-less
 * redirect to the Records page's By Franchise tab.
 */
class FranchiseRecordBookEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Route RCB queries to empty results to avoid malformed mock data warnings
        $this->mockDb->onQuery('ibl_rcb_season_records', []);
        $this->mockDb->onQuery('ibl_rcb_alltime_records', []);
    }

    /**
     * @param array<string, mixed> $query
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('redirectQueryProvider')]
    public function testRedirectStubEmitsNoBody(array $query): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', $query);

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_rcb');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function redirectQueryProvider(): array
    {
        return [
            'no query' => [[]],
            'numeric teamid' => [['teamid' => '1']],
            'non-numeric teamid' => [['teamid' => '5abc']],
        ];
    }

    public function testOpApiWithValidTeamidReturnsContent(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['op' => 'api', 'teamid' => '5']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testOpApiWithoutTeamidReturnsLeagueContent(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('FranchiseRecordBook', ['op' => 'api']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_rcb');
    }

    public function testRedirectUrlWithNumericTeamidIncludesTeamid(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_BYFRANCHISE,
            ['teamid'],
            ['teamid' => '5'],
            ['teamid' => 'ctype_digit']
        );
        $this->assertSame('modules.php?name=Records&tab=byfranchise&teamid=5', $url);
    }

    public function testRedirectUrlDropsNonNumericTeamid(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_BYFRANCHISE,
            ['teamid'],
            ['teamid' => '5abc'],
            ['teamid' => 'ctype_digit']
        );
        $this->assertSame('modules.php?name=Records&tab=byfranchise', $url);
    }

    public function testRedirectUrlWithoutTeamidOmitsParam(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_BYFRANCHISE,
            ['teamid'],
            [],
            ['teamid' => 'ctype_digit']
        );
        $this->assertSame('modules.php?name=Records&tab=byfranchise', $url);
    }
}
