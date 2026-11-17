<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

/**
 * The trade-ideas panel's per-session state: the refresh offset and the ideas
 * the panel offered on its last render, keyed by deal key.
 *
 * The service writes the map on every render; the feedback controller reads it,
 * so a hide trusts only the deal key from the form. A new sim key clears both.
 *
 * Only `current()` touches `$_SESSION`; tests pass a local array.
 */
final class PanelSessionState
{
    /** Ideas one refresh steps past; equals `TradeSuggestionsView::MAX_ITEMS`. */
    public const int STEP = 3;

    /** @var array<mixed> */
    private array $store;

    /**
     * @param array<mixed> $store
     */
    public function __construct(array &$store, private readonly string $key = 'trade_suggestions_panel')
    {
        $this->store = &$store;
    }

    /**
     * Bound by reference to the live session. The web bootstrap
     * (`Bootstrap\SessionBootstrap`) starts the session before any module runs.
     */
    public static function current(): self
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        return new self($_SESSION);
    }

    public function forSimKey(string $simKey): void
    {
        if ($this->simKey() !== $simKey) {
            $this->write($simKey, 0, []);
        }
    }

    public function offset(): int
    {
        $offset = $this->slot()['offset'] ?? null;

        return is_int($offset) ? max(0, $offset) : 0;
    }

    public function advance(int $step = self::STEP): void
    {
        $next = $this->offset() + $step;
        $this->write($this->simKey(), $next >= count($this->eligible()) ? 0 : $next, $this->eligible());
    }

    /**
     * @param array<string, array{core: string, partnerTid: int, dealJson: string}> $eligible
     */
    public function setEligible(array $eligible): void
    {
        $offset = $this->offset();
        $this->write($this->simKey(), $offset >= count($eligible) ? 0 : $offset, $eligible);
    }

    /**
     * @return array<string, array{core: string, partnerTid: int, dealJson: string}>
     */
    public function eligible(): array
    {
        $raw = $this->slot()['eligible'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $dealKey => $entry) {
            if (
                is_string($dealKey)
                && is_array($entry)
                && is_string($entry['core'] ?? null)
                && is_int($entry['partnerTid'] ?? null)
                && is_string($entry['dealJson'] ?? null)
            ) {
                $out[$dealKey] = ['core' => $entry['core'], 'partnerTid' => $entry['partnerTid'], 'dealJson' => $entry['dealJson']];
            }
        }

        return $out;
    }

    /**
     * @return array{core: string, partnerTid: int, dealJson: string}|null
     */
    public function entry(string $dealKey): ?array
    {
        return $this->eligible()[$dealKey] ?? null;
    }

    public function forget(string $dealKey): void
    {
        $eligible = $this->eligible();
        unset($eligible[$dealKey]);
        $this->write($this->simKey(), $this->offset(), $eligible);
    }

    private function simKey(): string
    {
        $simKey = $this->slot()['simKey'] ?? '';

        return is_string($simKey) ? $simKey : '';
    }

    /**
     * @return array<mixed>
     */
    private function slot(): array
    {
        $slot = $this->store[$this->key] ?? [];

        return is_array($slot) ? $slot : [];
    }

    /**
     * @param array<string, array{core: string, partnerTid: int, dealJson: string}> $eligible
     */
    private function write(string $simKey, int $offset, array $eligible): void
    {
        $this->store[$this->key] = ['simKey' => $simKey, 'offset' => $offset, 'eligible' => $eligible];
    }
}
