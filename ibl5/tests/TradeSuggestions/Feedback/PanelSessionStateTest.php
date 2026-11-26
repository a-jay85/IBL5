<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Feedback\PanelSessionState;
use TradeSuggestions\TradeSuggestionsView;

final class PanelSessionStateTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * @return array<string, array{core: string, partnerTid: int, dealJson: string}>
     */
    private static function eligible(int $n): array
    {
        $map = [];
        for ($i = 0; $i < $n; $i++) {
            $map[sprintf('%040x', $i)] = ['core' => str_repeat('c', 40), 'partnerTid' => 2, 'dealJson' => '{}'];
        }

        return $map;
    }

    public function testAdvanceWrapsToZero(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(12));

        $state->advance();
        self::assertSame(3, $state->offset());
        $state->advance();
        $state->advance();
        self::assertSame(9, $state->offset());
        $state->advance();
        self::assertSame(0, $state->offset());
    }

    public function testAdvanceClampsOnShorterList(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(12));
        $state->advance();
        $state->advance();
        self::assertSame(6, $state->offset());

        // The list shrinks to 5 while the offset is 6.
        $state->setEligible(self::eligible(5));

        self::assertSame(0, $state->offset());
        $state->advance();
        self::assertSame(3, $state->offset());
    }

    public function testForSimKeyResetsState(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k1');
        $state->setEligible(self::eligible(12));
        $state->advance();

        $state->forSimKey('k2');

        self::assertSame(0, $state->offset());
        self::assertSame([], $state->eligible());
    }

    public function testSameSimKeyKeepsState(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k1');
        $state->setEligible(self::eligible(12));
        $state->advance();

        $state->forSimKey('k1');

        self::assertSame(3, $state->offset());
        self::assertCount(12, $state->eligible());
    }

    public function testEntryReturnsNullForUnknownKey(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(2));

        self::assertNull($state->entry(str_repeat('f', 40)));
        self::assertSame(
            ['core' => str_repeat('c', 40), 'partnerTid' => 2, 'dealJson' => '{}'],
            $state->entry(sprintf('%040x', 1)),
        );
    }

    public function testForgetClearsStore(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(3));

        $state->forget(sprintf('%040x', 1));

        self::assertNull($state->entry(sprintf('%040x', 1)));
        self::assertCount(2, $state->eligible());
    }

    public function testCurrentBindsTheSessionByReference(): void
    {
        $_SESSION = [];

        PanelSessionState::current()->forSimKey('k');

        self::assertIsArray($_SESSION['trade_suggestions_panel']);
        self::assertSame('k', $_SESSION['trade_suggestions_panel']['simKey']);
    }

    public function testCurrentCreatesSessionWhenUnset(): void
    {
        unset($_SESSION);

        PanelSessionState::current()->forSimKey('fresh');

        self::assertIsArray($_SESSION);
        self::assertIsArray($_SESSION['trade_suggestions_panel']);
        self::assertSame('fresh', $_SESSION['trade_suggestions_panel']['simKey']);
    }

    public function testCurrentKeepsExistingSessionData(): void
    {
        $_SESSION = ['other_module' => ['x' => 1]];

        PanelSessionState::current()->forSimKey('k');

        self::assertSame(['x' => 1], $_SESSION['other_module']);
        self::assertSame('k', $_SESSION['trade_suggestions_panel']['simKey']);
    }

    public function testAdvanceStoresZeroOffsetWhenWrapping(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(3));

        $state->advance();

        self::assertIsArray($store['trade_suggestions_panel']);
        self::assertSame(0, $store['trade_suggestions_panel']['offset']);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function singleFieldMalformedEntryProvider(): array
    {
        $valid = ['core' => 'c', 'partnerTid' => 2, 'dealJson' => '{}'];

        return [
            'integer deal key' => [[5 => $valid]],
            'non-array entry' => [['k' => 'not an array']],
            'missing core' => [['k' => ['partnerTid' => 2, 'dealJson' => '{}']]],
            'non-string core' => [['k' => ['core' => 7] + $valid]],
            'non-int partnerTid' => [['k' => ['partnerTid' => '2'] + $valid]],
            'missing partnerTid' => [['k' => ['core' => 'c', 'dealJson' => '{}']]],
            'non-string dealJson' => [['k' => ['dealJson' => 9] + $valid]],
            'missing dealJson' => [['k' => ['core' => 'c', 'partnerTid' => 2]]],
        ];
    }

    /**
     * @param array<mixed> $malformed
     */
    #[DataProvider('singleFieldMalformedEntryProvider')]
    public function testEntryWithExactlyOneBadFieldIsDroppedWhileValidOnesStay(array $malformed): void
    {
        $valid = ['core' => 'good', 'partnerTid' => 4, 'dealJson' => '{"a":1}'];
        $store = ['trade_suggestions_panel' => [
            'simKey' => 'k',
            'offset' => 0,
            'eligible' => $malformed + ['keep' => $valid],
        ]];

        self::assertSame(['keep' => $valid], (new PanelSessionState($store))->eligible());
    }

    public function testStepEqualsViewMaxItems(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(12));

        // One refresh steps past exactly the ideas the panel shows.
        $state->advance();

        self::assertSame(TradeSuggestionsView::MAX_ITEMS, $state->offset());
    }

    public function testMalformedStoredEntriesAreIgnored(): void
    {
        $store = ['trade_suggestions_panel' => [
            'simKey' => 'k',
            'offset' => 0,
            'eligible' => ['x' => 'not an array', 'y' => ['core' => 'c']],
        ]];

        self::assertSame([], (new PanelSessionState($store))->eligible());
    }

    public function testFreshStoreHasZeroOffset(): void
    {
        $store = [];

        self::assertSame(0, (new PanelSessionState($store))->offset());
    }

    public function testForSimKeyStoresZeroOffset(): void
    {
        $store = [];
        (new PanelSessionState($store))->forSimKey('k');

        self::assertIsArray($store['trade_suggestions_panel']);
        self::assertSame(0, $store['trade_suggestions_panel']['offset']);
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function storedOffsetProvider(): array
    {
        return [
            'zero' => [0, 0],
            'positive' => [4, 4],
            'negative' => [-5, 0],
            'numeric string' => ['5', 0],
            'null' => [null, 0],
        ];
    }

    #[DataProvider('storedOffsetProvider')]
    public function testStoredOffsetIsSanitized(mixed $stored, int $expected): void
    {
        $store = ['trade_suggestions_panel' => ['simKey' => 'k', 'offset' => $stored, 'eligible' => []]];

        self::assertSame($expected, (new PanelSessionState($store))->offset());
    }

    public function testSetEligibleResetsOffsetEqualToListSize(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(12));
        $state->advance();
        self::assertSame(3, $state->offset());

        $state->setEligible(self::eligible(3));

        self::assertIsArray($store['trade_suggestions_panel']);
        self::assertSame(0, $store['trade_suggestions_panel']['offset']);
    }

    public function testSetEligibleKeepsOffsetBelowListSize(): void
    {
        $store = [];
        $state = new PanelSessionState($store);
        $state->forSimKey('k');
        $state->setEligible(self::eligible(12));
        $state->advance();

        $state->setEligible(self::eligible(4));

        self::assertSame(3, $state->offset());
    }
}
