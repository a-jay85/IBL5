<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

use TradeSuggestions\TradeCandidate;

/**
 * Stable keys for one trade idea. `deal` covers every asset; `core` covers the
 * players only, so a variant with a different pick sweetener shares the core.
 *
 * Asset keys mirror the `SuggestionLink` format (`1:<pid>` player, `0:<pickId>`
 * pick) with a side tag in front: `U:` for the GM's side, `P:` for the partner's.
 */
final readonly class DealKey
{
    public function __construct(
        public string $deal,
        public string $core,
    ) {
    }

    public static function forCandidate(TradeCandidate $c): self
    {
        $prefix = $c->userTid . '|' . $c->partnerTid . '|';

        return new self(
            sha1($prefix . implode(',', self::assetKeys($c))),
            sha1($prefix . implode(',', self::playerKeys($c))),
        );
    }

    /**
     * Every side-tagged asset key, sorted as strings.
     *
     * @return list<string>
     */
    public static function assetKeys(TradeCandidate $c): array
    {
        $keys = array_merge(
            self::tagged('U:1:', $c->userPids),
            self::tagged('U:0:', $c->userPickIds),
            self::tagged('P:1:', $c->partnerPids),
            self::tagged('P:0:', $c->partnerPickIds),
        );
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * The side-tagged player keys only, sorted as strings.
     *
     * @return list<string>
     */
    public static function playerKeys(TradeCandidate $c): array
    {
        $keys = array_merge(self::tagged('U:1:', $c->userPids), self::tagged('P:1:', $c->partnerPids));
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param list<int> $ids
     * @return list<string>
     */
    private static function tagged(string $prefix, array $ids): array
    {
        return array_map(static fn (int $id): string => $prefix . $id, $ids);
    }
}
