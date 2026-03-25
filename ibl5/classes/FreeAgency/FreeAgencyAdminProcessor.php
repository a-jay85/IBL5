<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminProcessorInterface;
use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use Player\Player;
use Team\Team;

/**
 * Processes admin free agency operations
 *
 * Handles the administrative workflow for processing free agency day results.
 * Database operations are delegated to FreeAgencyAdminRepository.
 *
 * @see FreeAgencyAdminProcessorInterface
 *
 * @phpstan-import-type OfferRow from FreeAgencyAdminRepositoryInterface
 */
class FreeAgencyAdminProcessor implements FreeAgencyAdminProcessorInterface
{
    private FreeAgencyAdminRepositoryInterface $repository;
    private \mysqli $db;

    public function __construct(FreeAgencyAdminRepositoryInterface $repository, \mysqli $db)
    {
        $this->repository = $repository;
        $this->db = $db;
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::processDay()
     */
    public function processDay(int $day): array
    {
        // Get all offers with bird years, ordered by player then perceived value
        $offers = $this->repository->getAllOffersWithBirdYears();

        $signings = [];
        $rejections = [];
        $autoRejections = [];
        $allOffers = [];
        $newsHomeText = '';
        $newsBodyText = '';
        $discordText = '';

        $processedPlayers = [];

        // Pre-load all demands in a single batch query to avoid N+1
        $playerIds = array_values(array_unique(array_map(
            static fn (array $row): int => $row['pid'],
            $offers
        )));
        $demandsMap = $this->repository->getPlayerDemandsBatch($playerIds);

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
            $offerYears = OfferType::calculateYears([
                'offer1' => $offer1, 'offer2' => $offer2, 'offer3' => $offer3,
                'offer4' => $offer4, 'offer5' => $offer5, 'offer6' => $offer6,
            ]);
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

            // Get demands for this player (from pre-loaded batch)
            $demands = $this->calculateDemandValue($demandsMap[$playerId] ?? null, $day);

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
                $offeringTeam = Team::initialize($this->db, $offeringTeamName);
                $player = Player::withPlayerID($this->db, $playerId);
                $playerTeam = Team::initialize($this->db, $player->teamName ?? '');

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
                $offeringTeam = Team::initialize($this->db, $offeringTeamName);
                // Only add if offer wasn't auto-rejected
                $offeringTeamDiscordId = (string) ($offeringTeam->discordID ?? '');
                $discordText .= $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId);
            }
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
        if ($signings === []) {
            return [
                'success' => false,
                'successCount' => 0,
                'errorCount' => 0,
                'message' => 'No operations were executed.',
            ];
        }

        $counts = $this->repository->executeSigningsTransactionally(
            $signings,
            $newsTitle,
            $newsHomeText,
            $newsBodyText
        );

        $successCount = $counts['successCount'];
        $errorCount = $counts['errorCount'];

        if ($errorCount === 0 && $successCount > 0) {
            return [
                'success' => true,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => "Successfully executed {$successCount} operations. Free agents have been assigned to teams.",
            ];
        }

        return [
            'success' => false,
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'message' => "Completed with errors: {$successCount} operations succeeded, {$errorCount} operations failed.",
        ];
    }

    /**
     * @see FreeAgencyAdminProcessorInterface::clearOffers()
     */
    public function clearOffers(): array
    {
        $this->repository->clearAllOffers();

        return [
            'success' => true,
            'message' => 'Successfully cleared all free agency offers from the database.',
        ];
    }

    /**
     * Calculate day-adjusted demand value from pre-loaded demand data
     *
     * @param array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}|null $demRow
     */
    private function calculateDemandValue(?array $demRow, int $day): float
    {
        if ($demRow === null) {
            return 0.0;
        }

        $demYears = OfferType::calculateYears([
            'offer1' => $demRow['dem1'], 'offer2' => $demRow['dem2'], 'offer3' => $demRow['dem3'],
            'offer4' => $demRow['dem4'], 'offer5' => $demRow['dem5'], 'offer6' => $demRow['dem6'],
        ]);

        // Calculate demands with day adjustment (demands decrease as days progress)
        $totalDemand = $demRow['dem1'] + $demRow['dem2'] + $demRow['dem3']
                     + $demRow['dem4'] + $demRow['dem5'] + $demRow['dem6'];
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
