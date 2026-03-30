<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use League\League;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;

/**
 * @see LeagueControlPanelRepositoryInterface
 */
class LeagueControlPanelRepository extends \BaseMysqliRepository implements LeagueControlPanelRepositoryInterface
{
    /**
     * @see LeagueControlPanelRepositoryInterface::getSetting()
     */
    public function getSetting(string $name): ?string
    {
        $row = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ?",
            "s",
            $name
        );

        if ($row === null) {
            return null;
        }

        /** @var string */
        return $row['value'];
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::getBulkSettings()
     */
    public function getBulkSettings(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $types = str_repeat('s', count($names));

        $rows = $this->fetchAll(
            "SELECT name, value FROM ibl_settings WHERE name IN ($placeholders)",
            $types,
            ...$names
        );

        $settings = [];
        foreach ($rows as $row) {
            /** @var string $name */
            $name = $row['name'];
            /** @var string $value */
            $value = $row['value'];
            $settings[$name] = $value;
        }

        return $settings;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::getSimLengthInDays()
     */
    public function getSimLengthInDays(): int
    {
        $value = $this->getSetting('Sim Length in Days');

        return $value !== null ? (int) $value : 3;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::updateSetting()
     */
    public function updateSetting(string $name, string $value): bool
    {
        $this->execute(
            "UPDATE ibl_settings SET value = ? WHERE name = ?",
            "ss",
            $value,
            $name
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setSeasonPhase()
     */
    public function setSeasonPhase(string $phase): bool
    {
        $this->transactional(function () use ($phase): void {
            $this->execute(
                "UPDATE ibl_settings SET value = ? WHERE name = 'Current Season Phase'",
                "s",
                $phase
            );

            if ($phase === 'Preseason' || $phase === 'HEAT') {
                $this->execute(
                    "UPDATE ibl_settings SET value = 'Off' WHERE name = 'Show Draft Link'"
                );
            }
        });

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setSimLengthInDays()
     */
    public function setSimLengthInDays(int $days): bool
    {
        $this->execute(
            "UPDATE ibl_settings SET value = ? WHERE name = 'Sim Length in Days'",
            "i",
            $days
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setShowDraftLink()
     */
    public function setShowDraftLink(string $value): bool
    {
        $this->execute(
            "UPDATE ibl_settings SET value = ? WHERE name = 'Show Draft Link'",
            "s",
            $value
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::resetAllStarVoting()
     */
    public function resetAllStarVoting(): bool
    {
        $this->transactional(function (): void {
            $this->execute(
                "UPDATE ibl_votes_ASG SET East_F1 = NULL, East_F2 = NULL, East_F3 = NULL, East_F4 = NULL,
                    West_F1 = NULL, West_F2 = NULL, West_F3 = NULL, West_F4 = NULL,
                    East_B1 = NULL, East_B2 = NULL, East_B3 = NULL, East_B4 = NULL,
                    West_B1 = NULL, West_B2 = NULL, West_B3 = NULL, West_B4 = NULL"
            );

            $this->execute(
                "UPDATE ibl_settings SET value = 'Yes' WHERE name = 'ASG Voting'"
            );

            $this->execute(
                "UPDATE ibl_team_info SET asg_vote = 'No Vote'"
            );
        });

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::resetEndOfYearVoting()
     */
    public function resetEndOfYearVoting(): bool
    {
        $this->transactional(function (): void {
            $this->execute(
                "UPDATE ibl_votes_EOY SET MVP_1 = NULL, MVP_2 = NULL, MVP_3 = NULL,
                    Six_1 = NULL, Six_2 = NULL, Six_3 = NULL,
                    ROY_1 = NULL, ROY_2 = NULL, ROY_3 = NULL,
                    GM_1 = NULL, GM_2 = NULL, GM_3 = NULL"
            );

            $this->execute(
                "UPDATE ibl_settings SET value = 'Yes' WHERE name = 'EOY Voting'"
            );

            $this->execute(
                "UPDATE ibl_team_info SET eoy_vote = 'No Vote'"
            );
        });

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setWaiversToFreeAgents()
     */
    public function setWaiversToFreeAgents(): bool
    {
        $this->execute(
            "UPDATE ibl_plr SET tid = " . League::FREE_AGENTS_TEAMID . ", bird = 0"
            . " WHERE retired <> 1 AND ordinal > " . \JSB::WAIVERS_ORDINAL
            . " AND name NOT LIKE '|%' AND name NOT LIKE '%Buyout%'"
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setFreeAgencyFactorsForPfw()
     */
    public function setFreeAgencyFactorsForPfw(): bool
    {
        $this->execute(
            "UPDATE ibl_team_info info JOIN ibl_standings s ON s.tid = info.teamid SET Contract_Wins = s.wins, Contract_Losses = s.losses"
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setAllowTrades()
     */
    public function setAllowTrades(string $value): bool
    {
        return $this->updateSetting('Allow Trades', $value);
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setAllowWaivers()
     */
    public function setAllowWaivers(string $value): bool
    {
        return $this->updateSetting('Allow Waiver Moves', $value);
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setFreeAgencyNotifications()
     */
    public function setFreeAgencyNotifications(string $value): bool
    {
        return $this->updateSetting('Free Agency Notifications', $value);
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::activateTriviaMode()
     */
    public function activateTriviaMode(): bool
    {
        return $this->updateSetting('Trivia Mode', 'On');
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::deactivateTriviaMode()
     */
    public function deactivateTriviaMode(): bool
    {
        return $this->updateSetting('Trivia Mode', 'Off');
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::resetAllContractExtensions()
     */
    public function resetAllContractExtensions(): bool
    {
        $this->execute("UPDATE ibl_team_info SET Used_Extension_This_Season = 0");

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::resetAllMlesAndLles()
     */
    public function resetAllMlesAndLles(): bool
    {
        $this->execute("UPDATE ibl_team_info SET HasMLE = 1, HasLLE = 1");

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::deleteDraftPlaceholders()
     */
    public function deleteDraftPlaceholders(): int
    {
        $draftPidStart = 90000;

        return $this->execute("DELETE FROM ibl_plr WHERE pid >= ?", "i", $draftPidStart);
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::upsertAward()
     */
    public function upsertAward(int $year, string $award, string $name): int
    {
        return $this->execute(
            "INSERT INTO ibl_awards (year, Award, name)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)",
            'iss',
            $year,
            $award,
            $name
        );
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::upsertGmAward()
     */
    public function upsertGmAward(int $year, string $name): int
    {
        return $this->execute(
            "INSERT INTO ibl_gm_awards (year, Award, name)
            VALUES (?, 'GM of the Year', ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name)",
            'is',
            $year,
            $name
        );
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::deleteOutdatedBuyoutsAndCash()
     */
    public function deleteOutdatedBuyoutsAndCash(): int
    {
        return $this->execute(
            "DELETE FROM ibl_plr
            WHERE (name LIKE '%|%Buyout%' OR name LIKE '%|%Cash%')
              AND (cy >= 1 OR cy1 = 0)
              AND (cy >= 2 OR cy2 = 0)
              AND (cy >= 3 OR cy3 = 0)
              AND (cy >= 4 OR cy4 = 0)
              AND (cy >= 5 OR cy5 = 0)
              AND (cy >= 6 OR cy6 = 0)"
        );
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::hasFinalsMvp()
     */
    public function hasFinalsMvp(int $year): bool
    {
        $row = $this->fetchOne(
            "SELECT table_ID FROM ibl_awards WHERE year = ? AND Award = 'IBL Finals MVP' LIMIT 1",
            'i',
            $year
        );

        return $row !== null;
    }
}
