<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use League\LeagueContext;

/**
 * Repository for admin free agency database operations
 *
 * Handles all database queries for the admin free agency processor,
 * including offer retrieval, player contract updates, MLE/LLE usage,
 * news story insertion, and offer clearing.
 *
 * @see FreeAgencyAdminRepositoryInterface
 *
 * @phpstan-import-type OfferRow from FreeAgencyAdminRepositoryInterface
 * @phpstan-import-type DemandRow from FreeAgencyAdminRepositoryInterface
 */
class FreeAgencyAdminRepository extends BaseMysqliRepository implements FreeAgencyAdminRepositoryInterface
{
    /**
     * @see FreeAgencyAdminRepositoryInterface::getAllOffersWithBirdYears()
     *
     * @return list<OfferRow>
     */
    public function getAllOffersWithBirdYears(): array
    {
        /** @var list<OfferRow> */
        return $this->fetchAll(
            "SELECT `ibl_fa_offers`.*, `ibl_plr`.`bird`
             FROM `ibl_fa_offers`
             JOIN `ibl_plr` ON `ibl_fa_offers`.`pid` = `ibl_plr`.`pid`
             ORDER BY `ibl_fa_offers`.`name` ASC, `ibl_fa_offers`.`perceivedvalue` DESC",
            ""
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::getPlayerDemandsBatch()
     *
     * @return array<int, DemandRow>
     */
    public function getPlayerDemandsBatch(array $playerIds): array
    {
        /** @var list<array{pid: int, dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}> $rows */
        $rows = $this->fetchAllInList(
            "SELECT pid, dem1, dem2, dem3, dem4, dem5, dem6 FROM `ibl_demands` WHERE pid IN ({IN})",
            'i',
            $playerIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pid']] = [
                'dem1' => $row['dem1'],
                'dem2' => $row['dem2'],
                'dem3' => $row['dem3'],
                'dem4' => $row['dem4'],
                'dem5' => $row['dem5'],
                'dem6' => $row['dem6'],
            ];
        }

        return $result;
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::updatePlayerContract()
     */
    public function updatePlayerContract(
        int $pid,
        int $teamid,
        int $offerYears,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6
    ): int {
        return $this->execute(
            "UPDATE `ibl_plr`
             SET cy = 0,
                 salary_yr1 = ?,
                 salary_yr2 = ?,
                 salary_yr3 = ?,
                 salary_yr4 = ?,
                 salary_yr5 = ?,
                 salary_yr6 = ?,
                 cyt = ?,
                 teamid = ?,
                 fa_signing_flag = 1
             WHERE pid = ?
             LIMIT 1",
            "iiiiiiiii",
            $offer1,
            $offer2,
            $offer3,
            $offer4,
            $offer5,
            $offer6,
            $offerYears,
            $teamid,
            $pid
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::markMleUsed()
     */
    public function markMleUsed(string $teamName): void
    {
        $this->execute(
            "UPDATE `ibl_team_info` SET has_mle = 0 WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::markLleUsed()
     */
    public function markLleUsed(string $teamName): void
    {
        $this->execute(
            "UPDATE `ibl_team_info` SET has_lle = 0 WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::insertNewsStory()
     */
    public function insertNewsStory(string $title, string $homeText, string $bodyText): int
    {
        $currentTime = date('Y-m-d H:i:s');

        $affected = $this->execute(
            "INSERT INTO nuke_stories
             (catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, ihome, alanguage, acomm, haspoll, poll_id, associated)
             VALUES (8, 'chibul', ?, ?, ?, ?, 0, 0, 29, 'chibul', '', 0, 'english', 0, 0, 0, '29-')",
            "ssss",
            $title,
            $currentTime,
            $homeText,
            $bodyText
        );

        return $affected > 0 ? $this->getLastInsertId() : 0;
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::clearAllOffers()
     */
    public function clearAllOffers(): void
    {
        $this->execute("DELETE FROM `ibl_fa_offers`", "");
    }

    /**
     * Resolve the active league for marker keying.
     */
    private function resolveLeague(): string
    {
        return $this->leagueContext?->getCurrentLeague() ?? LeagueContext::LEAGUE_IBL;
    }

    /**
     * Season ending year for the active league, from ibl_settings.
     *
     * @throws \RuntimeException when the setting row is absent — see D8, fail closed.
     */
    private function getSeasonEndingYear(): int
    {
        $league = $this->resolveLeague();
        $row = $this->fetchOne(
            "SELECT value FROM `ibl_settings` WHERE setting_key = ? AND league = ?",
            "ss",
            'Current Season Ending Year',
            $league
        );

        if ($row === null) {
            throw new \RuntimeException(
                "Missing 'Current Season Ending Year' setting for league '{$league}'"
            );
        }

        $value = $row['value'];
        if (!is_numeric($value)) {
            // D8: fail closed. A non-numeric setting would key the marker under a
            // value no later run reproduces, silently disabling the guard.
            throw new \RuntimeException(
                "Non-numeric 'Current Season Ending Year' setting for league '{$league}'"
            );
        }

        return (int) $value;
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::getDayProcessedMarker()
     */
    public function getDayProcessedMarker(int $day): ?string
    {
        $row = $this->fetchOne(
            "SELECT processed_at FROM `ibl_fa_days_processed`
             WHERE league = ? AND season_ending_year = ? AND day = ?",
            "sii",
            $this->resolveLeague(),
            $this->getSeasonEndingYear(),
            $day
        );

        if ($row === null) {
            return null;
        }

        $processedAt = $row['processed_at'];
        if (!is_string($processedAt)) {
            // Never report "not processed" for a marker row that exists — that would
            // fail open on the advisory read and re-offer the Assign button.
            throw new \RuntimeException(
                "Unexpected processed_at value on the day {$day} marker row"
            );
        }

        return $processedAt;
    }

    /**
     * @see FreeAgencyAdminRepositoryInterface::executeSigningsTransactionally()
     */
    public function executeSigningsTransactionally(
        int $day,
        array $signings,
        string $newsTitle,
        string $newsHomeText,
        string $newsBodyText
    ): array {
        return $this->transactional(function () use ($day, $signings, $newsTitle, $newsHomeText, $newsBodyText): array {
            // MUST be the first write in this closure. On a replay the PRIMARY KEY
            // rejects it before any player row, MLE/LLE flag, or news story moves.
            $this->insertDayProcessedMarker($day, count($signings));

            $successCount = 0;
            $errorCount = 0;

            foreach ($signings as $signing) {
                $affected = $this->updatePlayerContract(
                    $signing['playerId'],
                    $signing['teamId'],
                    $signing['offerYears'],
                    $signing['offers']['offer1'],
                    $signing['offers']['offer2'],
                    $signing['offers']['offer3'],
                    $signing['offers']['offer4'],
                    $signing['offers']['offer5'],
                    $signing['offers']['offer6']
                );

                if ($affected > 0) {
                    $successCount++;
                } else {
                    $errorCount++;
                }

                if ($signing['usedMle']) {
                    $this->markMleUsed($signing['teamName']);
                }

                if ($signing['usedLle']) {
                    $this->markLleUsed($signing['teamName']);
                }
            }

            $newsSid = 0;
            if ($successCount > 0 && $newsHomeText !== '' && $newsBodyText !== '') {
                $newsSid = $this->insertNewsStory($newsTitle, $newsHomeText, $newsBodyText);
                if ($newsSid > 0) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }

            return ['successCount' => $successCount, 'errorCount' => $errorCount, 'newsSid' => $newsSid];
        });
    }

    /**
     * @throws DayAlreadyProcessedException when this day was already executed.
     */
    private function insertDayProcessedMarker(int $day, int $signingsSubmitted): void
    {
        $league = $this->resolveLeague();
        $seasonEndingYear = $this->getSeasonEndingYear();

        try {
            $this->execute(
                "INSERT INTO `ibl_fa_days_processed`
                     (league, season_ending_year, day, signings_submitted)
                 VALUES (?, ?, ?, ?)",
                "siii",
                $league,
                $seasonEndingYear,
                $day,
                $signingsSubmitted
            );
        } catch (\mysqli_sql_exception $e) {
            // mysqli runs in exception mode here, so a constraint violation surfaces as
            // a driver exception before BaseMysqliRepository's own code-1003 path can
            // fire. 1062 is MySQL's ER_DUP_ENTRY — anything else is a real failure.
            if ($e->getCode() === 1062) {
                throw new DayAlreadyProcessedException(
                    "Free agency day {$day} has already been processed for {$league} {$seasonEndingYear}.",
                    0,
                    $e
                );
            }
            throw $e;
        } catch (\RuntimeException $e) {
            // Kept for the non-exception mysqli reporting mode, where executeQuery()
            // converts the failed execute() into a RuntimeException itself.
            if ($e->getCode() === 1003 && str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new DayAlreadyProcessedException(
                    "Free agency day {$day} has already been processed for {$league} {$seasonEndingYear}.",
                    0,
                    $e
                );
            }
            throw $e;
        }
    }
}
