<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\FeedbackRepository;

final class FeedbackRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private FeedbackRepository $repo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repo = new FeedbackRepository($this->mockDb);
    }

    private function record(string $freeText = 'too old'): void
    {
        $this->repo->record(
            1,
            2,
            new DealKey(str_repeat('a', 40), str_repeat('b', 40)),
            '{"keys":["U:1:10"]}',
            'TOO_OLD,OTHER',
            $freeText,
        );
    }

    public function testRecordBindsIssisss(): void
    {
        $this->record();

        $prepared = $this->mockDb->getPreparedQueries();
        self::assertCount(1, $prepared);
        self::assertSame(7, substr_count($prepared[0], '?'));
        foreach (['aaaa', 'bbbb', 'TOO_OLD', 'too old', '"keys"'] as $value) {
            self::assertStringNotContainsString($value, $prepared[0]);
        }
        self::assertSame('ississs', $this->mockDb->getLastBoundTypes());
    }

    public function testRecordBindsValuesInColumnOrder(): void
    {
        $this->record();

        self::assertSame(
            [1, str_repeat('a', 40), str_repeat('b', 40), 2, '{"keys":["U:1:10"]}', 'TOO_OLD,OTHER', 'too old'],
            $this->mockDb->getLastBoundParams(),
        );
    }

    public function testRecordPassesFreeTextRawToTheBind(): void
    {
        $this->record('<script>x</script>');

        $params = $this->mockDb->getLastBoundParams();
        self::assertSame('<script>x</script>', $params[6]);
        self::assertStringNotContainsString('<script>', $this->mockDb->getPreparedQueries()[0]);
    }

    public function testHiddenKeysBindsTid(): void
    {
        $this->repo->hiddenKeys(1);

        self::assertSame([1], $this->mockDb->getLastBoundParams());
        self::assertSame('i', $this->mockDb->getLastBoundTypes());
        self::assertSame(1, substr_count($this->mockDb->getPreparedQueries()[0], '?'));
    }

    public function testHiddenKeysSplitsRowsIntoDealAndCoreLists(): void
    {
        $this->mockDb->onQuery('ibl_trade_suggestion_feedback', [
            ['deal_key' => 'd1', 'core_key' => 'c1'],
            ['deal_key' => 'd2', 'core_key' => 'c1'],
        ]);

        self::assertSame(['deal' => ['d1', 'd2'], 'core' => ['c1', 'c1']], $this->repo->hiddenKeys(1));
    }

    public function testLatestForTeamReturnsNullWhenEmpty(): void
    {
        $this->mockDb->onQuery('ibl_trade_suggestion_feedback', []);

        self::assertNull($this->repo->latestForTeam(1));
        self::assertSame([1], $this->mockDb->getLastBoundParams());
    }

    public function testLatestForTeamShapesTheRow(): void
    {
        $this->mockDb->onQuery('ibl_trade_suggestion_feedback', [[
            'deal_key' => str_repeat('a', 40),
            'reason_codes' => 'TOO_OLD',
            'free_text' => null,
            'created_at' => '2026-11-01 10:00:00',
        ]]);

        self::assertSame(
            [
                'deal_key' => str_repeat('a', 40),
                'reason_codes' => 'TOO_OLD',
                'free_text' => null,
                'created_at' => '2026-11-01 10:00:00',
            ],
            $this->repo->latestForTeam(1),
        );
    }

    public function testLatestForTeamKeepsAStringNote(): void
    {
        $this->mockDb->onQuery('ibl_trade_suggestion_feedback', [[
            'deal_key' => 'd1',
            'reason_codes' => 'OTHER',
            'free_text' => 'too old',
            'created_at' => '2026-11-01 10:00:00',
        ]]);

        $row = $this->repo->latestForTeam(1);

        self::assertNotNull($row);
        self::assertSame('too old', $row['free_text']);
    }

    public function testLatestForTeamDropsANonStringNote(): void
    {
        $this->mockDb->onQuery('ibl_trade_suggestion_feedback', [[
            'deal_key' => 'd1',
            'reason_codes' => 'OTHER',
            'free_text' => 42,
            'created_at' => '2026-11-01 10:00:00',
        ]]);

        $row = $this->repo->latestForTeam(1);

        self::assertNotNull($row);
        self::assertNull($row['free_text']);
    }
}
