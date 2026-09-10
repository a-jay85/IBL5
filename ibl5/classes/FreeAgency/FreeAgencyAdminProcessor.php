<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyAdminProcessorInterface;
use FreeAgency\Contracts\FreeAgencyAdminRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyDiscordDispatcherInterface;
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

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;

    private FreeAgencyDiscordDispatcherInterface $discordDispatcher;

    /** Dedicated channel for Discord delivery failures, separate from the audit log. */
    private \Psr\Log\LoggerInterface $discordLogger;

    public function __construct(
        FreeAgencyAdminRepositoryInterface $repository,
        \mysqli $db,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?FreeAgencyDiscordDispatcherInterface $discordDispatcher = null
    ) {
        $this->repository = $repository;
        $this->db = $db;
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
        $this->discordDispatcher = $discordDispatcher ?? new FreeAgencyDiscordDispatcher();
        $this->discordLogger = \Logging\LoggerFactory::getChannel('discord');
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
        $pendingHeader = '';
        $pendingAcceptanceLine = '';
        /** @var array<int, array{teamName: string, line: string}> */
        $pendingOfferLines = [];

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
            $mle = $row['mle'];
            $lle = $row['lle'];
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
            $newsBodyText .= "The {$offeringTeamName} offered {$playerName} a {$offerYears}-year deal worth a total of {$offerTotal} million dollars.\n";

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
                // Flush previous player's buffered offer lines (sorted by team name)
                $discordText .= $this->flushPlayerOfferLines($pendingHeader, $pendingOfferLines, $pendingAcceptanceLine);
                $pendingHeader = '';
                $pendingAcceptanceLine = '';
                $pendingOfferLines = [];

                $processedPlayers[$playerName] = true;

                // Get team info for IDs
                $offeringTeam = Team::initialize($this->db, $offeringTeamName);
                $player = Player::withPlayerID($this->db, $playerId);
                $playerTeam = Team::initialize($this->db, $player->getTeamName() ?? '');

                // Buffer Discord header
                $pendingHeader = "**" . strtoupper("{$playerName}, {$playerTeam->city} {$player->getTeamName()}") . "** <@!{$playerTeam->discord_id}>\n";

                $offeringTeamDiscordId = (string) ($offeringTeam->discord_id ?? '');
                $pendingOfferLines[] = [
                    'teamName' => $offeringTeamName,
                    'line' => $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId),
                ];

                if ($perceivedValue > $demands) {
                    // Offer accepted
                    $signings[] = [
                        'playerName' => $playerName,
                        'playerId' => $playerId,
                        'teamName' => $offeringTeamName,
                        'teamId' => $offeringTeam->teamid,
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
                    $newsHomeText .= $outcomeText . "\n";
                    $pendingAcceptanceLine = $outcomeText . " <@!{$offeringTeamDiscordId}>\n\n";
                } else {
                    // Offer rejected
                    $rejections[] = [
                        'playerName' => $playerName,
                        'reason' => 'Best offer did not meet player demands',
                    ];
                    $pendingAcceptanceLine = "**REJECTED**\n\n";
                }
            } else {
                // Additional offer for already-processed player - buffer for sorting
                $offeringTeam = Team::initialize($this->db, $offeringTeamName);
                $offeringTeamDiscordId = (string) ($offeringTeam->discord_id ?? '');
                $pendingOfferLines[] = [
                    'teamName' => $offeringTeamName,
                    'line' => $this->buildOfferLine($offeringTeamName, $offer1, $offer2, $offer3, $offer4, $offer5, $offer6, $offeringTeamDiscordId),
                ];
            }
        }

        // Flush last player's buffered offer lines
        $discordText .= $this->flushPlayerOfferLines($pendingHeader, $pendingOfferLines, $pendingAcceptanceLine);

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
        $newsSid = $counts['newsSid'];

        $this->logger->info('fa_signings_executed', [
            'action' => 'fa_signings_executed',
            'day' => $day,
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ]);

        if ($errorCount === 0 && $successCount > 0) {
            $message = "Successfully executed {$successCount} operations. Free agents have been assigned to teams.";

            if ($newsSid > 0) {
                $chunks = $this->buildDiscordChunks($newsHomeText, $newsSid);
                $totalChunks = count($chunks);
                $deliveredChunks = 0;

                try {
                    foreach ($chunks as $chunk) {
                        $this->discordDispatcher->dispatch($chunk);
                        $deliveredChunks++;
                    }
                } catch (\Throwable $e) {
                    $this->discordLogger->error('fa_signings_discord_post_failed', [
                        'action' => 'fa_signings_discord_post_failed',
                        'day' => $day,
                        'news_sid' => $newsSid,
                        'delivered_chunks' => $deliveredChunks,
                        'total_chunks' => $totalChunks,
                        'error' => $e->getMessage(),
                    ]);
                    // Parts already delivered are visible to the whole league, so the
                    // operator must be told where to resume — reposting the whole story
                    // would duplicate everything up to the failure point.
                    $message .= $deliveredChunks === 0
                        ? ' (Discord post to #free-agency failed — nothing was posted; post manually.)'
                        : " (Discord post to #free-agency failed after {$deliveredChunks} of {$totalChunks}"
                            . " parts — parts 1-{$deliveredChunks} are already in the channel; post manually"
                            . ' from part ' . ($deliveredChunks + 1) . '.)';
                }
            }

            return [
                'success' => true,
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'message' => $message,
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

        $this->logger->info('fa_offers_cleared', [
            'action' => 'fa_offers_cleared',
        ]);

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

    private const DISCORD_MESSAGE_LIMIT = 2000;

    /**
     * Split the signings summary into Discord-sized messages.
     *
     * @return list<string> Each entry is at most self::DISCORD_MESSAGE_LIMIT characters.
     */
    private function buildDiscordChunks(string $text, int $newsSid): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = str_replace('<br>', "\n", $normalized);
        $normalized = (string) preg_replace('/\n{2,}/', "\n", $normalized);
        $normalized = trim($normalized);

        if ($normalized === '') {
            return [];
        }

        $lines = [];
        foreach (explode("\n", $normalized) as $line) {
            if (mb_strlen($line) > self::DISCORD_MESSAGE_LIMIT) {
                foreach (mb_str_split($line, self::DISCORD_MESSAGE_LIMIT) as $piece) {
                    $lines[] = $piece;
                }
                continue;
            }
            $lines[] = $line;
        }

        $chunks = [];
        $current = '';
        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : $current . "\n" . $line;
            if (mb_strlen($candidate) > self::DISCORD_MESSAGE_LIMIT) {
                $chunks[] = $current;
                $current = $line;
                continue;
            }
            $current = $candidate;
        }
        $chunks[] = $current;

        $link = 'https://iblhoops.net/ibl5/modules.php?name=News&file=article&sid=' . $newsSid;
        $lastIndex = count($chunks) - 1;
        if (mb_strlen($chunks[$lastIndex]) + mb_strlen($link) + 1 <= self::DISCORD_MESSAGE_LIMIT) {
            $chunks[$lastIndex] .= "\n" . $link;
        } else {
            $chunks[] = $link;
        }

        return $chunks;
    }

    /**
     * Flush buffered offer lines for a player, sorted by team name.
     *
     * @param array<int, array{teamName: string, line: string}> $offerLines
     */
    private function flushPlayerOfferLines(string $header, array $offerLines, string $acceptanceLine): string
    {
        if ($header === '') {
            return '';
        }

        usort($offerLines, static fn (array $a, array $b): int => strcasecmp($a['teamName'], $b['teamName']));

        $text = $header;
        foreach ($offerLines as $entry) {
            $text .= $entry['line'];
        }
        $text .= $acceptanceLine;

        return $text;
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
