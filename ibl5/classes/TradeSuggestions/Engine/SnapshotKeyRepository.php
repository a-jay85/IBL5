<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use League\LeagueContext;
use TradeSuggestions\Engine\Contracts\SnapshotKeyRepositoryInterface;

/**
 * @see SnapshotKeyRepositoryInterface
 */
final class SnapshotKeyRepository extends \Database\BaseMysqliRepository implements SnapshotKeyRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see SnapshotKeyRepositoryInterface::rosterDigest()
     */
    public function rosterDigest(): string
    {
        $row = $this->fetchOne(
            "SELECT SHA1(GROUP_CONCAT(CONCAT_WS(':', pid, teamid, ordinal, cy, injured, retired) ORDER BY pid ) ) AS d FROM `ibl_plr`",
            ''
        );
        $digest = $row['d'] ?? null;

        return is_string($digest) ? $digest : 'empty';
    }

    /**
     * @see SnapshotKeyRepositoryInterface::tradeMarker()
     */
    public function tradeMarker(): string
    {
        $row = $this->fetchOne(
            "SELECT COALESCE(MAX(updated_at), '') AS m FROM `ibl_trade_info`",
            ''
        );
        $marker = $row['m'] ?? null;

        return is_string($marker) ? $marker : '';
    }
}
