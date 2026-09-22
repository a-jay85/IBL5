<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class RecordHoldersEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);
        // Cold cache → single-flight rebuild: the request must win GET_LOCK to build inline.
        $this->mockDb->onQuery('GET_LOCK', [['got' => 1]]);
    }

    public function testRendersAllThreeRecordCategories(): void
    {
        $output = $this->runModule('RecordHolders');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Regular Season', $output);
        $this->assertStringContainsString('Playoffs', $output);
        $this->assertStringContainsString('H.E.A.T.', $output);
    }

    public function testHandlesEmptyRecords(): void
    {
        $output = $this->runModule('RecordHolders');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testAllstarOpRendersFullAppearancesList(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pid' => 1, 'appearances' => 3],
        ]);
        $output = $this->runModule('RecordHolders', ['op' => 'allstar'], [], $this->dbGlobals());

        $this->assertStringContainsString('<h1 class="ibl-title">All-Star Appearances</h1>', $output);
        $this->assertStringContainsString('Test Player', $output);
        $this->assertStringNotContainsString('Most All-Star Appearances', $output);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownOpProvider')]
    public function testUnknownOpFallsBackToRecordHoldersView(string $op): void
    {
        $output = $this->runModule('RecordHolders', ['op' => $op], [], $this->dbGlobals());

        $this->assertStringContainsString('Most All-Star Appearances', $output);
        $this->assertStringNotContainsString('<h1 class="ibl-title">All-Star Appearances</h1>', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownOpProvider(): array
    {
        return [
            'uppercase ALLSTAR'    => ['ALLSTAR'],
            'trailing space'       => ['allstar '],
            'null byte'            => ["allstar\x00"],
            'empty string'         => [''],
            'path traversal'       => ['../allstar'],
        ];
    }

    public function testArrayOpFallsBackToRecordHoldersView(): void
    {
        $output = $this->runModule('RecordHolders', ['op' => ['allstar']], [], $this->dbGlobals());

        $this->assertStringContainsString('Most All-Star Appearances', $output);
        $this->assertStringNotContainsString('<h1 class="ibl-title">All-Star Appearances</h1>', $output);
    }
}
