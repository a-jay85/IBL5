<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use Settings\SettingName;

/**
 * Writes an Advance decision to ibl_settings.
 *
 * Year goes through updateSetting(); phase goes through setSeasonPhase() so the
 * Show Draft Link side effect for Preseason/HEAT is preserved. Any outcome other
 * than Advance writes nothing at all.
 */
final class SeasonRolloverApplier
{
    public function __construct(
        private readonly LeagueControlPanelRepositoryInterface $repository,
    ) {
    }

    /**
     * @return bool true when settings were written, false when the decision was NoOp or Halt
     */
    public function apply(SeasonRolloverDecisionResult $result): bool
    {
        if (!$result->shouldWrite()) {
            return false;
        }

        // Non-null by SeasonRolloverDecisionResult's Advance invariant.
        $year = (int) $result->targetYear;
        $phase = (string) $result->targetPhase;

        $this->repository->updateSetting(SettingName::CurrentSeasonEndingYear->value, (string) $year);
        $this->repository->setSeasonPhase($phase);

        return true;
    }
}
