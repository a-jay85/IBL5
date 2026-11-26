<?php

declare(strict_types=1);

namespace TradeSuggestions\Tuning;

use Database\SqlPlaceholders;
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
        // A fixed-width list of bound placeholders, padded with -1 (no team has
        // that id), so no runtime value is ever written into the SQL text.
        $rows = $this->fetchAll(
            'SELECT tid, partner_tid, deal_key, core_key, deal_json, reason_codes, free_text, created_at
                FROM `ibl_trade_suggestion_feedback`
                WHERE tid IN (' . SqlPlaceholders::PID_LIST . ')
                ORDER BY tid ASC, created_at DESC, id DESC',
            SqlPlaceholders::PID_LIST_TYPES,
            ...SqlPlaceholders::padPidList($tids)
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
