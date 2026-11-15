<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

use League\LeagueContext;
use TradeSuggestions\Feedback\Contracts\FeedbackRepositoryInterface;

/**
 * @see FeedbackRepositoryInterface
 */
class FeedbackRepository extends \Database\BaseMysqliRepository implements FeedbackRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see FeedbackRepositoryInterface::hiddenKeys()
     *
     * @return array{deal: list<string>, core: list<string>}
     */
    public function hiddenKeys(int $tid): array
    {
        $rows = $this->fetchAll(
            "SELECT deal_key, core_key FROM `ibl_trade_suggestion_feedback` WHERE tid = ?",
            "i",
            $tid
        );
        $deal = [];
        $core = [];
        foreach ($rows as $row) {
            if (is_string($row['deal_key'] ?? null)) {
                $deal[] = $row['deal_key'];
            }
            if (is_string($row['core_key'] ?? null)) {
                $core[] = $row['core_key'];
            }
        }

        return ['deal' => $deal, 'core' => $core];
    }

    /**
     * @see FeedbackRepositoryInterface::record()
     */
    public function record(int $tid, int $partnerTid, DealKey $key, string $dealJson, string $reasonCodes, string $freeText): void
    {
        $this->execute(
            "INSERT INTO `ibl_trade_suggestion_feedback`"
            . " (tid, deal_key, core_key, partner_tid, deal_json, reason_codes, free_text)"
            . " VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''))"
            . " ON DUPLICATE KEY UPDATE reason_codes = VALUES(reason_codes), free_text = VALUES(free_text),"
            . " deal_json = VALUES(deal_json), created_at = CURRENT_TIMESTAMP",
            "ississs",
            $tid,
            $key->deal,
            $key->core,
            $partnerTid,
            $dealJson,
            $reasonCodes,
            $freeText
        );
    }

    /**
     * @see FeedbackRepositoryInterface::latestForTeam()
     *
     * @return array{deal_key: string, reason_codes: string, free_text: ?string, created_at: string}|null
     */
    public function latestForTeam(int $tid): ?array
    {
        $row = $this->fetchOne(
            "SELECT deal_key, reason_codes, free_text, created_at FROM `ibl_trade_suggestion_feedback`"
            . " WHERE tid = ? ORDER BY created_at DESC, id DESC LIMIT 1",
            "i",
            $tid
        );
        if ($row === null) {
            return null;
        }
        $freeText = $row['free_text'] ?? null;

        return [
            'deal_key' => is_string($row['deal_key'] ?? null) ? $row['deal_key'] : '',
            'reason_codes' => is_string($row['reason_codes'] ?? null) ? $row['reason_codes'] : '',
            'free_text' => is_string($freeText) ? $freeText : null,
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : '',
        ];
    }
}
