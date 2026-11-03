<?php

declare(strict_types=1);

namespace TradeSuggestions;

use League\LeagueContext;
use TradeSuggestions\Contracts\TeamModeRepositoryInterface;

/**
 * @see TeamModeRepositoryInterface
 */
class TeamModeRepository extends \Database\BaseMysqliRepository implements TeamModeRepositoryInterface
{
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see TeamModeRepositoryInterface::getStoredSetting()
     */
    public function getStoredSetting(int $tid): TeamModeSetting
    {
        $row = $this->fetchOne(
            "SELECT trade_mode FROM `ibl_team_info` WHERE teamid = ?",
            "i",
            $tid
        );

        if ($row === null) {
            return TeamModeSetting::Auto;
        }

        $raw = $row['trade_mode'] ?? null;

        return TeamModeSetting::tryFrom(is_string($raw) ? $raw : '') ?? TeamModeSetting::Auto;
    }

    /**
     * @see TeamModeRepositoryInterface::getAllStoredSettings()
     *
     * @return array<int, TeamModeSetting>
     */
    public function getAllStoredSettings(): array
    {
        $rows = $this->fetchAll("SELECT teamid, trade_mode FROM `ibl_team_info`");

        $settings = [];
        foreach ($rows as $row) {
            $tidRaw = $row['teamid'] ?? null;
            if (!is_numeric($tidRaw)) {
                continue;
            }
            $raw = $row['trade_mode'] ?? null;
            $settings[(int) $tidRaw] = TeamModeSetting::tryFrom(is_string($raw) ? $raw : '') ?? TeamModeSetting::Auto;
        }

        return $settings;
    }

    /**
     * @see TeamModeRepositoryInterface::saveSetting()
     */
    public function saveSetting(int $tid, TeamModeSetting $setting): void
    {
        $this->execute(
            "UPDATE `ibl_team_info` SET trade_mode = ? WHERE teamid = ?",
            "si",
            $setting->value,
            $tid
        );
    }
}
