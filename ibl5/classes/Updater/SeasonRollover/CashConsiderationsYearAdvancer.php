<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use Settings\SettingName;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;

/**
 * Advances `ibl_cash_considerations.cy` once per season rollover.
 *
 * Cash-consideration rows mirror player contracts: slot `salary_yrN` is read
 * relative to the row's own `cy`. Nothing advanced `cy` automatically since the
 * table was created (migration 095), so cash rows read one season stale after
 * each rollover (ibl5-bugs#24, #25). This class performs the advance exactly
 * once per season ending year, guarded by the
 * `Cash Considerations Last Advanced Year` setting seeded by migration 188.
 */
final class CashConsiderationsYearAdvancer
{
    public function __construct(
        private readonly BuyoutLedgerRepositoryInterface $ledger,
        private readonly LeagueControlPanelRepositoryInterface $settings,
    ) {
    }

    /**
     * Advance every cash-consideration row's contract year by one, at most once
     * per season ending year.
     *
     * @param int $targetYear The season ending year being rolled into
     * @return int Rows advanced; 0 when this year's advance already happened
     *
     * @throws \RuntimeException When the marker setting is missing or unusable
     */
    public function advance(int $targetYear): int
    {
        if ($targetYear <= 0) {
            throw new \RuntimeException(
                'CashConsiderationsYearAdvancer: refusing to advance for target year ' . $targetYear
            );
        }

        $key = SettingName::CashConsiderationsLastAdvancedYear->value;
        $raw = $this->settings->getSetting($key);

        if ($raw === null) {
            throw new \RuntimeException(
                'CashConsiderationsYearAdvancer: setting "' . $key . '" is missing. '
                . 'Apply migration 188 before running the season rollover.'
            );
        }

        if (!is_numeric($raw)) {
            throw new \RuntimeException(
                'CashConsiderationsYearAdvancer: setting "' . $key . '" is not a year: ' . $raw
            );
        }

        if ((int) $raw >= $targetYear) {
            return 0;
        }

        $rowsAdvanced = $this->ledger->advanceAllCy();
        $this->settings->updateSetting($key, (string) $targetYear);

        return $rowsAdvanced;
    }
}
