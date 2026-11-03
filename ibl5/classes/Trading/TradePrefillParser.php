<?php

declare(strict_types=1);

namespace Trading;

/**
 * Turns the untrusted `prefill` query param into the trade form's previousFormData shape.
 *
 * Accepted format: comma-separated `<type>:<id>` keys, where type 1 is a player (pid)
 * and type 0 is a draft pick (pickid), e.g. `1:123,0:456`. Every key must name an asset
 * already loaded for the user's or partner's side of the form. Any malformed or foreign
 * key rejects the whole pre-fill (fail closed). The result only ticks checkboxes; the
 * GM still reviews and submits the offer, and every submit-time validation still runs.
 */
final class TradePrefillParser
{
    public const MAX_LENGTH = 200;
    public const MAX_ITEMS = 8;

    /**
     * @param array<string, mixed> $pageData Trade offer page data from TradingService::getTradeOfferPageData()
     * @return array{checkedItems: array<string, true>, userSendsCash: array<int, int>, partnerSendsCash: array<int, int>}|null
     */
    public static function parse(mixed $raw, array $pageData): ?array
    {
        if (!is_string($raw) || strlen($raw) > self::MAX_LENGTH) {
            return null;
        }

        $item = '[01]:[1-9][0-9]{0,9}';
        $pattern = '/\A' . $item . '(,' . $item . '){0,' . (self::MAX_ITEMS - 1) . '}\z/';
        if (preg_match($pattern, $raw) !== 1) {
            return null;
        }

        if (($pageData['partnerTeamId'] ?? 0) === 0) {
            return null;
        }

        $allowed = self::buildAllowedKeys($pageData);
        $keys = array_values(array_unique(explode(',', $raw)));

        foreach ($keys as $key) {
            if (!isset($allowed[$key])) {
                return null;
            }
        }

        /** @var array<string, true> $checkedItems */
        $checkedItems = array_fill_keys($keys, true);

        return [
            'checkedItems' => $checkedItems,
            'userSendsCash' => [],
            'partnerSendsCash' => [],
        ];
    }

    /**
     * @param array<string, mixed> $pageData
     * @return array<string, true>
     */
    private static function buildAllowedKeys(array $pageData): array
    {
        $allowed = [];
        foreach (['userPlayers', 'partnerPlayers'] as $listKey) {
            foreach (self::rows($pageData, $listKey) as $row) {
                $pid = $row['pid'] ?? null;
                if (is_int($pid) || is_string($pid)) {
                    $allowed['1:' . $pid] = true;
                }
            }
        }
        foreach (['userPicks', 'partnerPicks'] as $listKey) {
            foreach (self::rows($pageData, $listKey) as $row) {
                $pickId = $row['pickid'] ?? null;
                if (is_int($pickId) || is_string($pickId)) {
                    $allowed['0:' . $pickId] = true;
                }
            }
        }

        return $allowed;
    }

    /**
     * @param array<string, mixed> $pageData
     * @return list<array<mixed>>
     */
    private static function rows(array $pageData, string $listKey): array
    {
        $list = $pageData[$listKey] ?? [];
        if (!is_array($list)) {
            return [];
        }

        $rows = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
