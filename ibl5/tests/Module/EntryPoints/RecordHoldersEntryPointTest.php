<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Module\ModuleRedirect;
use Records\RecordsController;

/**
 * RecordHolders is now a redirect stub; All-Time records render on the
 * Records page and the all-star list on AllStarAppearances. The 302 header
 * itself is asserted by E2E since CLI PHPUnit cannot read response headers.
 */
class RecordHoldersEntryPointTest extends ModuleEntryPointTestCase
{
    /**
     * @param array<string, mixed> $query
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('queryProvider')]
    public function testRedirectStubEmitsNoBody(array $query): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pid' => 1, 'appearances' => 5],
        ]);

        $output = $this->runModule('RecordHolders', $query, [], $this->dbGlobals());

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function queryProvider(): array
    {
        return [
            'no query' => [[]],
            'allstar op' => [['op' => 'allstar']],
            'unknown op' => [['op' => 'bogus']],
            'array op' => [['op' => ['allstar']]],
        ];
    }

    public function testDefaultRedirectTargetIsRecordsAllTime(): void
    {
        $url = ModuleRedirect::passthroughUrl(
            'modules.php?name=Records&tab=' . RecordsController::TAB_ALLTIME,
            [],
            []
        );
        $this->assertSame('modules.php?name=Records&tab=alltime', $url);
    }

    public function testAllstarRedirectTargetIsAllStarAppearances(): void
    {
        $url = ModuleRedirect::passthroughUrl('modules.php?name=AllStarAppearances', [], []);
        $this->assertSame('modules.php?name=AllStarAppearances', $url);
    }
}
