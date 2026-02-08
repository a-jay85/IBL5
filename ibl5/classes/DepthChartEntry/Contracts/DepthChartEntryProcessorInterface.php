<?php

declare(strict_types=1);

namespace DepthChartEntry\Contracts;

/**
 * DepthChartEntryProcessorInterface - Contract for depth chart data processing
 *
 * Transforms raw POST data into structured player data and statistics,
 * including sanitization, validation bounds checking, and aggregation.
 *
 * @phpstan-type ProcessedPlayerData array{name: string, pg: int, sg: int, sf: int, pf: int, c: int, active: int, min: int, of: int, df: int, oi: int, di: int, bh: int, injury: int}
 * @phpstan-type ProcessedSubmission array{playerData: list<ProcessedPlayerData>, activePlayers: int, pos_1: int, pos_2: int, pos_3: int, pos_4: int, pos_5: int, hasStarterAtMultiplePositions: bool, nameOfProblemStarter: string}
 */
interface DepthChartEntryProcessorInterface
{
    /**
     * Process raw POST data into structured depth chart with statistics
     * 
     * Iterates through POST data for up to maxPlayers, extracting and sanitizing
     * depth chart values for each player. Calculates aggregate statistics including
     * active player count, per-position counts, and conflict detection.
     * 
     * **Processing Steps:**
     * 1. Loop through POST data for players 1 to maxPlayers
     * 2. For each player, sanitize and validate all 13 depth chart fields
     * 3. Count active players (where active=1)
     * 4. Count non-injured players at each position (where pos_N > 0 and not injured)
     * 5. Detect if any player is starting (depth=1) at multiple positions
     * 
     * **Sanitization Rules Applied:**
     * - Player names: trim whitespace, remove HTML tags via strip_tags()
     * - Depth values (pg-c): clamped to 0-5 range
     * - Active: normalized to 0 or 1
     * - Minutes: clamped to 0-40 range
     * - Focus values (OF/DF): clamped to 0-3 range
     * - Settings (OI/DI/BH): clamped to -2 to 2 range
     * - Injury flag: converted to int (0 or 1)
     * 
     * @param array<string, mixed> $postData Raw POST data from form submission ($_POST)
     * @param int $maxPlayers Maximum number of players to process (default 15)
     * @return ProcessedSubmission
     * 
     * **Important Behaviors:**
     * - Empty POST data results in empty playerData array
     * - Position counts exclude injured players (injury != 0)
     * - Active count includes all active players regardless of injury status
     * - Only the first player with multiple starting positions is recorded
     * - All sanitization happens during processing (no invalid values returned)
     */
    public function processSubmission(array $postData, int $maxPlayers = 15): array;

    /**
     * Generate CSV content from processed player data
     * 
     * Creates a CSV-formatted string suitable for file storage or email.
     * Header row includes position slot names from JSB::PLAYER_POSITIONS constant.
     * One data row per player in the order provided.
     * 
     * @param list<ProcessedPlayerData> $playerData Array of processed player arrays (from processSubmission)
     * @return string CSV-formatted content with header row and data rows
     * 
     * **CSV Format:**
     * - Header: Name, Position slot names, ACTIVE, MIN, OF, DF, OI, DI, BH
     * - Data rows: One per player with all fields comma-separated
     * - Line endings: \n (newline)
     * - No quote escaping (assume values are already sanitized)
     * - Numeric values are included as-is (not zero-padded)
     * 
     * **Example Header:**
     * Name,PG,SG,SF,PF,C,ACTIVE,MIN,OF,DF,OI,DI,BH
     * 
     * **Important Behaviors:**
     * - Player names are output as-is (already sanitized)
     * - Position slot names come from JSB::PLAYER_POSITIONS constant
     * - Order matches array iteration order (matches form order)
     * - Empty playerData results in header-only CSV
     */
    public function generateCsvContent(array $playerData): string;
}
