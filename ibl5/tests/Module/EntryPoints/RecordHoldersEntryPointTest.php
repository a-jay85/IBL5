<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

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

    // Redirect targets (default -> Records All-Time, op=allstar -> AllStarAppearances)
    // are pinned by RecordsControllerTest::testLegacyRedirectUrl*. The Location header
    // cannot be captured in the CLI PHPUnit harness.
}
