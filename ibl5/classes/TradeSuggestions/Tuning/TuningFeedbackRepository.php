<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use League\LeagueContext;
use TradeSuggestions\Tuning\Contracts\TuningFeedbackRepositoryInterface;

/**
 * @see TuningFeedbackRepositoryInterface
 */
class TuningFeedbackRepository extends \Database\BaseMysqliRepository implements TuningFeedbackRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see TuningFeedbackRepositoryInterface::forTeams()
     *
     * @param list<int> $tids
     * @return list<TuningFeedback>
     */
    public function forTeams(array $tids): array
    {
        if ($tids === []) {
            return [];
        }
        // One bound placeholder per tid; no value is written into the SQL.
        $placeholders = implode(', ', array_fill(0, count($tids), '?'));
        $rows = $this->fetchAll(
            "SELECT tid, partner_tid, deal_key, core_key, deal_json, reason_codes, free_text, created_at
                FROM `ibl_trade_suggestion_feedback`
                WHERE tid IN ({$placeholders})
                ORDER BY tid ASC, created_at DESC, id DESC",
            str_repeat('i', count($tids)),
            ...$tids
        );

        $out = [];
        foreach ($rows as $row) {
            $codes = is_string($row['reason_codes'] ?? null) ? $row['reason_codes'] : '';
            $freeText = $row['free_text'] ?? null;
            $out[] = new TuningFeedback(
                tid: is_int($row['tid'] ?? null) ? $row['tid'] : 0,
                partnerTid: is_int($row['partner_tid'] ?? null) ? $row['partner_tid'] : 0,
                dealKey: is_string($row['deal_key'] ?? null) ? $row['deal_key'] : '',
                coreKey: is_string($row['core_key'] ?? null) ? $row['core_key'] : '',
                dealJson: is_string($row['deal_json'] ?? null) ? $row['deal_json'] : '',
                reasonCodes: $codes === '' ? [] : explode(',', $codes),
                freeText: is_string($freeText) ? $freeText : null,
                createdAt: is_string($row['created_at'] ?? null) ? $row['created_at'] : '',
            );
        }

        return $out;
    }
}
