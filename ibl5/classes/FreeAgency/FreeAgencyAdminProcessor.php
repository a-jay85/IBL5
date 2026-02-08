<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyAdminProcessorInterface;
use Player\Player;

/**
 * Processes admin free agency operations
 *
 * Handles the administrative workflow for processing free agency day results
 * with secure prepared statements for all database operations.
 *
 * @see FreeAgencyAdminProcessorInterface
 *
 * @phpstan-type OfferRow array{
 *     name: string,
 *     team: string,
 *     pid: int,
 *     offer1: int,
 *     offer2: int,
 *     offer3: int,
 *     offer4: int,
 *     offer5: int,
 *     offer6: int,
 *     bird: int,
 *     MLE: int,
 *     LLE: int,
 *     random: int,
 *     perceivedvalue: float
 * }
 */
class FreeAgencyAdminProcessor extends BaseMysqliRepository implements FreeAgencyAdminProcessorInterface
{
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::processDay()
     */
    public function processDay(int $day): array
    {
        // Get all offers with bird years, ordered by player then perceived value
        $offers = $this->fetchAll(
            "SELECT ibl_fa_offers.*, ibl_plr.bird, ibl_plr.pid
             FROM ibl_fa_offers
             JOIN ibl_plr ON ibl_fa_offers.name = ibl_plr.name
             ORDER BY ibl_fa_offers.name ASC, ibl_fa_offers.perceivedvalue DESC",
            ""
        );

        $signings = [];
        $rejections = [];
        $autoRejections = [];
        $allOffers = [];
        $newsHomeText = '';
        $newsBodyText = '';
        $discordText = '';

        $processedPlayers = [];
        $lastPlayerName = '';

        foreach ($offers as $row) {
            /** @var OfferRow $row */
            $playerName = $row['name'];
            $playerId = $row['pid'];
            $offeringTeamName = $row['team'];
            $perceivedValue = $row['perceivedvalue'];

            $offer1 = $row['offer1'];
            $offer2 = $row['offer2'];
            $offer3 = $row['offer3'];
            $offer4 = $row['offer4'];
            $offer5 = $row['offer5'];
            $offer6 = $row['offer6'];

            $birdYears = $row['bird'];
            $mle = $row['MLE'];
            $lle = $row['LLE'];
            $random = $row['random'];

            // Calculate offer years and total
            $offerYears = $this->calculateOfferYears($offer1, $offer2, $offer3, $offer4, $offer5, $offer6);
            $offerTotal = ($offer1 + $offer2 + $offer3 + $offer4 + $offer5 + $offer6) / 100;

            // Store all offers for display
            $allOffers[] = [
                'playerName' => $playerName,
                'teamName' => $offeringTeamName,
                'offers' => [
                    'offer1' => $offer1,
                    'offer2' => $offer2,
                    'offer3' => $offer3,
                    'offer4' => $offer4,
                    'offer5' => $offer5,
                    'offer6' => $offer6,
                ],
                'birdYears' => $birdYears,
                'mle' => $mle,
                'lle' => $lle,
                'random' => $random,
                'perceivedValue' => $perceivedValue,
            ];

            // Build extended news text for all offers
            $newsBodyText .= "The {$offeringTeamName} offered {$playerName} a {$offerYears}-year deal worth a total of {$offerTotal} million dollars.<br>\n";

            // Get demands for this player
            $demands = $this->getPlayerDemands($playerName, $day);

            // Check if offer is auto-rejected (under half of demands)
            if ($perceivedValue <= $demands / 2) {
                $autoRejections[] = [
                    'playerName' => $playerName,
                    'teamName' => $offeringTeamName,
                    'offers' => [
                        'offer1' => $offer1,
                        'offer2' => $offer2,
                        'offer3' => $offer3,
                        'offer4' => $offer4,
                        'offer5' => $offer5,
                        'offer6' => $offer6,
                    ],
                    'reason' => 'Offer under half of player demands',
                ];
                continue;
            }

            // Only process first (highest value) offer per player
            if (!isset($processedPlayers[$playerName])) {
                $processedPlayers[$playerName] = true;

                // Get team info for IDs
                $offeringTeam = \Team::initialize($this->db, $offeringTeamName);
                $player = Player::withPlayerID($this->db, $playerId);
                $playerTeam = \Team::initialize($this->db, $player->teamName ?? '');

                // Build Discord text
                $discordText .= "**" . strtoupper("{$playerName}, {$playerTeam->city} {$player->teamName}") . "** <@!{$playerTeam->discordID}>\n";

                if ($perceivedValue > $demands) {
                    // Offer accepted
                    $signings[] = [
                        'playerName' => $playerName,
                        'playerId' => $playerId,
                        'teamName' => $offeringTeamName,
                        'teamId' => $offeringTeam->teamID,
                        'offers' => [
                            'offer1' => $offer1,
                            'offer2' => $offer2,
                            'offer3' => $offer3,
                            'offer4' => $offer4,
                            'offer5' => $offer5,
                            'offer6' => $offer6,
                        ],
                        'offerYears' => $offerYears,
                        'offerTotal' => $offerTotal,
                        'usedMle' => $mle === 1,
                        'usedLle' => $lle === 1,
                    ];

                    $outcomeText = "{$playerName} accepts the {$offeringTeamName} offer of a {$offerYears}-year deal worth a total of {$offerTotal} million dollars.";
                    $newsHomeText .= $outcomeText . "<br>\n";
                    $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                    $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
                    $discordText .= $outcomeText . " <@!{$offeringTeamDiscordId}>\n\n";
                } else {
                    // Offer rejected
                    $rejections[] = [
                        'playerName' => $playerName,
                        'reason' => 'Best offer did not meet player demands',
                    ];
                    $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                    $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
                    $discordText .= "**REJECTED**\n\n";
                }
            } else {
                // Additional offer for already-processed player - add to Discord text
                $offeringTeam = \Team::initialize($this->db, $offeringTeamName);
                // Only add if offer wasn't auto-rejected
                $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
            }

            $lastPlayerName = $playerName;
        }

        return [
            'signings' => $signings,
            'rejections' => $rejections,
            'autoRejections' => $autoRejections,
            'allOffers' => $allOffers,
            'newsHomeText' => $newsHomeText,
            'newsBodyText' => $newsBodyText,
            'discordText' => $discordText,
        ];
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::executeSignings()
     */
    public function executeSignings(
        int $day,
        array $signings,
        string $newsTitle,
        string $newsHomeText,
        string $newsBodyText
    ): array {
        $successCount = 0;
        $errorCount = 0;

        foreach ($signings as $signing) {
            // Update player contract
            $affected = $this->execute(
                "UPDATE ibl_plr
                 SET cy = 0,
                     cy1 = ?,
                     cy2 = ?,
                     cy3 = ?,
                     cy4 = ?,
                     cy5 = ?,
                     cy6 = ?,
                     teamname = ?,
                     cyt = ?,
                     tid = ?
                 WHERE pid = ?
                 LIMIT 1",
                "iiiiiisiii",
                $signing['offers']['offer1'],
                $signing['offers']['offer2'],
                $signing['offers']['offer3'],
                $signing['offers']['offer4'],
                $signing['offers']['offer5'],
                $signing['offers']['offer6'],
                $signing['teamName'],
                $signing['offerYears'],
                $signing['teamId'],
                $signing['playerId']
            );

            if ($affected > 0) {
                $successCount++;
            } else {
                $errorCount++;
            }

            // Mark MLE as used if applicable
            if ($signing['usedMle']) {
                $this->execute(
                    "UPDATE ibl_team_info SET HasMLE = 0 WHERE team_name = ? LIMIT 1",
                    "s",
                    $signing['teamName']
                );
            }

            // Mark LLE as used if applicable
            if ($signing['usedLle']) {
                $this->execute(
                    "UPDATE ibl_team_info SET HasLLE = 0 WHERE team_name = ? LIMIT 1",
                    "s",
                    $signing['teamName']
                );
            }
        }

        // Insert news story if there were signings
        if ($successCount > 0 && $newsHomeText !== '' && $newsBodyText !== '') {
            $currentTime = date('Y-m-d H:i:s');
            $affected = $this->execute(
                "INSERT INTO nuke_stories
                 (catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, ihome, alanguage, acomm, haspoll, pollID, score, ratings, rating_ip, associated)
                 VALUES (8, 'chibul', ?, ?, ?, ?, 0, 0, 29, 'chibul', '', 0, 'english', 0, 0, 0, 0, 0, '0', '29-')",
                "ssss",
                $newsTitle,
                $currentTime,
                $newsHomeText,
                $newsBodyText
            );

            if ($affected > 0) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        if ($errorCount === 0 && $successCount > 0) {
            return [
                'success' => true,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => "Successfully executed {$successCount} operations. Free agents have been assigned to teams.",
            ];
        } elseif ($errorCount > 0) {
            return [
                'success' => false,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => "Completed with errors: {$successCount} operations succeeded, {$errorCount} operations failed.",
            ];
        } else {
            return [
                'success' => false,
                'successCount' => 0,
                'errorCount' => 0,
                'message' => 'No operations were executed.',
            ];
        }
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::clearOffers()
     */
    public function clearOffers(): array
    {
        // Use DELETE instead of TRUNCATE for prepared statement compatibility
        $this->execute("DELETE FROM ibl_fa_offers", "");

        return [
            'success' => true,
            'message' => 'Successfully cleared all free agency offers from the database.',
        ];
    }

    /**
     * Calculate number of years in an offer
     */
    private function calculateOfferYears(
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6
    ): int {
        $years = 6;
        if ($offer6 === 0) {
            $years = 5;
        }
        if ($offer5 === 0) {
            $years = 4;
        }
        if ($offer4 === 0) {
            $years = 3;
        }
        if ($offer3 === 0) {
            $years = 2;
        }
        if ($offer2 === 0) {
            $years = 1;
        }
        return $years;
    }

    /**
     * Get player demands adjusted for the current day
     */
    private function getPlayerDemands(string $playerName, int $day): float
    {
        /** @var array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}|null $demRow */
        $demRow = $this->fetchOne(
            "SELECT dem1, dem2, dem3, dem4, dem5, dem6 FROM ibl_demands WHERE name = ?",
            "s",
            $playerName
        );

        if ($demRow === null) {
            return 0.0;
        }

        $dem1 = $demRow['dem1'];
        $dem2 = $demRow['dem2'];
        $dem3 = $demRow['dem3'];
        $dem4 = $demRow['dem4'];
        $dem5 = $demRow['dem5'];
        $dem6 = $demRow['dem6'];

        // Calculate demand years
        $demYears = 6;
        if ($dem6 === 0) {
            $demYears = 5;
        }
        if ($dem5 === 0) {
            $demYears = 4;
        }
        if ($dem4 === 0) {
            $demYears = 3;
        }
        if ($dem3 === 0) {
            $demYears = 2;
        }
        if ($dem2 === 0) {
            $demYears = 1;
        }

        // Calculate demands with day adjustment (demands decrease as days progress)
        $totalDemand = $dem1 + $dem2 + $dem3 + $dem4 + $dem5 + $dem6;
        return ($totalDemand / $demYears) * ((11 - $day) / 10);
    }

    /**
     * Build offer line for Discord text
     */
    private function buildOfferLine(
        string $teamName,
        int $offer1,
        int $offer2,
        int $offer3,
        int $offer4,
        int $offer5,
        int $offer6,
        string $discordId
    ): string {
        $line = "{$teamName} - {$offer1}";
        if ($offer2 !== 0) {
            $line .= "/{$offer2}";
        }
        if ($offer3 !== 0) {
            $line .= "/{$offer3}";
        }
        if ($offer4 !== 0) {
            $line .= "/{$offer4}";
        }
        if ($offer5 !== 0) {
            $line .= "/{$offer5}";
        }
        if ($offer6 !== 0) {
            $line .= "/{$offer6}";
        }
        $line .= " <@!{$discordId}>\n";
        return $line;
    }
}
