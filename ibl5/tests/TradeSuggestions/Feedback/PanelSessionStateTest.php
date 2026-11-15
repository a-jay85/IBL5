<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

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
}
