<?php

declare(strict_types=1);

namespace LeagueControlPanel;

use League\League;
use LeagueControlPanel\Contracts\LeagueControlPanelRepositoryInterface;
use Trading\CashConsiderationRepository;

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
                "UPDATE ibl_votes_ASG SET east_f1 = NULL, east_f2 = NULL, east_f3 = NULL, east_f4 = NULL,
                    west_f1 = NULL, west_f2 = NULL, west_f3 = NULL, west_f4 = NULL,
                    east_b1 = NULL, east_b2 = NULL, east_b3 = NULL, east_b4 = NULL,
                    west_b1 = NULL, west_b2 = NULL, west_b3 = NULL, west_b4 = NULL"
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
                "UPDATE ibl_votes_EOY SET mvp_1 = NULL, mvp_2 = NULL, mvp_3 = NULL,
                    six_1 = NULL, six_2 = NULL, six_3 = NULL,
                    roy_1 = NULL, roy_2 = NULL, roy_3 = NULL,
                    gm_1 = NULL, gm_2 = NULL, gm_3 = NULL"
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
            "UPDATE ibl_plr SET teamid = " . League::FREE_AGENTS_TEAMID . ", bird = 0"
            . " WHERE retired <> 1 AND ordinal > " . \JSB::WAIVERS_ORDINAL
        );

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::setFreeAgencyFactorsForPfw()
     */
    public function setFreeAgencyFactorsForPfw(): bool
    {
        $this->execute(
            "UPDATE ibl_team_info info JOIN ibl_standings s ON s.teamid = info.teamid SET contract_wins = s.wins, contract_losses = s.losses"
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
        $this->execute("UPDATE ibl_team_info SET used_extension_this_season = 0");

        return true;
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::resetAllMlesAndLles()
     */
    public function resetAllMlesAndLles(): bool
    {
        $this->execute("UPDATE ibl_team_info SET has_mle = 1, has_lle = 1");

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
            "INSERT INTO ibl_awards (year, award, name)
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
            "INSERT INTO ibl_gm_awards (year, award, name)
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
        $cashRepo = new CashConsiderationRepository($this->db);
        return $cashRepo->deleteExpiredCashConsiderations();
    }

    /**
     * @see LeagueControlPanelRepositoryInterface::hasFinalsMvp()
     */
    public function hasFinalsMvp(int $year): bool
    {
        $row = $this->fetchOne(
            "SELECT table_id FROM ibl_awards WHERE year = ? AND award = 'IBL Finals MVP' LIMIT 1",
            'i',
            $year
        );

        return $row !== null;
    }
}
