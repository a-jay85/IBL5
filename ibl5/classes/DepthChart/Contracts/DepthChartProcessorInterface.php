<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartProcessorInterface - Contract for depth chart data processing
 * 
 * Transforms raw POST data into structured player data and statistics,
 * including sanitization, validation bounds checking, and aggregation.
 */
interface DepthChartProcessorInterface
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
     * @param array $postData Raw POST data from form submission ($_POST)
     * @param int $maxPlayers Maximum number of players to process (default 15)
     * @return array<string, mixed> Processed data with these keys:
     *     - playerData: array<int, array> Array of processed player arrays
     *     - activePlayers: int (count of players with active=1)
     *     - pos_1: int (count of PG slot assignments, excluding injured)
     *     - pos_2: int (count of SG slot assignments, excluding injured)
     *     - pos_3: int (count of SF slot assignments, excluding injured)
     *     - pos_4: int (count of PF slot assignments, excluding injured)
     *     - pos_5: int (count of C slot assignments, excluding injured)
     *     - hasStarterAtMultiplePositions: bool (true if conflict detected)
     *     - nameOfProblemStarter: string (player name if conflict detected, empty string otherwise)
     * 
     * **Player Array Structure (within playerData):**
     *     - name: Sanitized player name
     *     - pg, sg, sf, pf, c: Position depths (0-5)
     *     - active: Active status (0 or 1)
     *     - min: Projected minutes (0-40)
     *     - of, df: Offensive/Defensive focus (0-3)
     *     - oi, di, bh: Intensity/handling settings (-2 to 2)
     *     - injury: Injury flag (0 or 1)
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
     * @param array<int, array<string, mixed>> $playerData Array of processed player arrays (from processSubmission)
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
