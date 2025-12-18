<?php

declare(strict_types=1);

namespace DepthChart\Contracts;

/**
 * DepthChartViewInterface - Contract for depth chart view rendering
 * 
 * Renders all HTML components for depth chart entry forms and submission results.
 * Uses output buffering for form rendering, direct echo for option rendering.
 */
interface DepthChartViewInterface
{
    /**
     * Render team logo image centered on page
     * 
     * @param int $teamID Team ID used to locate logo file (images/logo/{teamID}.jpg)
     * @return void Echoes HTML image tag
     * 
     * **HTML Output:**
     * `<center><img src="images/logo/{teamID}.jpg"></center><br>`
     * 
     * **Important Behaviors:**
     * - Directly echoes HTML (does not buffer)
     * - Team ID is embedded directly (already validated as int)
     * - Image path is relative to web root
     * - Includes line break after centered container
     */
    public function renderTeamLogo(int $teamID): void;

    /**
     * Render position depth dropdown options (0-5)
     * 
     * @param int $selectedValue Currently selected value to mark SELECTED in output
     * @return void Echoes option tags
     * 
     * **Option Values and Labels:**
     * - 0 => 'No'
     * - 1 => '1st'
     * - 2 => '2nd'
     * - 3 => '3rd'
     * - 4 => '4th'
     * - 5 => 'ok'
     * 
     * **HTML Output:**
     * `<option value="{value}"{SELECTED if matches}>{label}</option>` for each value
     * 
     * **Important Behaviors:**
     * - Directly echoes option tags (caller wraps in select element)
     * - Only one option receives SELECTED attribute
     * - Uses loose equality (==) for comparison (safe for ints)
     */
    public function renderPositionOptions(int $selectedValue): void;

    /**
     * Render offensive/defensive focus dropdown options (0-3)
     * 
     * @param int $selectedValue Currently selected value to mark SELECTED in output
     * @return void Echoes option tags
     * 
     * **Option Values and Labels:**
     * - 0 => 'Auto'
     * - 1 => 'Outside'
     * - 2 => 'Drive'
     * - 3 => 'Post'
     * 
     * **Important Behaviors:**
     * - Directly echoes option tags (caller wraps in select element)
     * - Only one option receives SELECTED attribute
     * - Uses loose equality (==) for comparison
     */
    public function renderOffDefOptions(int $selectedValue): void;

    /**
     * Render OI/DI/BH setting dropdown options (-2 to 2)
     * 
     * @param int $selectedValue Currently selected value to mark SELECTED in output
     * @return void Echoes option tags
     * 
     * **Option Values and Display Labels:**
     * - -2 => '-2'
     * - -1 => '-1'
     * - 0 => '-' (special display for neutral)
     * - 1 => '1'
     * - 2 => '2'
     * 
     * **Important Behaviors:**
     * - Directly echoes option tags (caller wraps in select element)
     * - Only one option receives SELECTED attribute
     * - Neutral value (0) displays as '-' for clarity
     */
    public function renderSettingOptions(int $selectedValue): void;

    /**
     * Render active/inactive dropdown options (0 or 1)
     * 
     * @param int $selectedValue Currently selected value (1 or 0)
     * @return void Echoes option tags
     * 
     * **Option Values and Labels:**
     * - 1 => 'Yes'
     * - 0 => 'No'
     * 
     * **Important Behaviors:**
     * - Directly echoes option tags (caller wraps in select element)
     * - Only one option receives SELECTED attribute
     */
    public function renderActiveOptions(int $selectedValue): void;

    /**
     * Render minutes dropdown options (0 to staminaCap)
     * 
     * @param int $selectedValue Currently selected value to mark SELECTED
     * @param int $staminaCap Maximum minutes based on player stamina (typically 40)
     * @return void Echoes option tags
     * 
     * **Option Values and Labels:**
     * - 0 => 'Auto' (special label for automatic minute assignment)
     * - 1-{staminaCap} => Numeric value as label
     * 
     * **Important Behaviors:**
     * - Directly echoes option tags (caller wraps in select element)
     * - First option is always 'Auto' (value 0)
     * - Generates {staminaCap} numeric options
     * - Only one option receives SELECTED attribute
     */
    public function renderMinutesOptions(int $selectedValue, int $staminaCap): void;

    /**
     * Render complete depth chart form header with table structure
     * 
     * @param string $teamName Team name to embed in hidden form field
     * @param int $teamID Team ID (not used in output, kept for consistency)
     * @param array<string> $slotNames Array of 5 position slot names (e.g., ['PG', 'SG', 'SF', 'PF', 'C'])
     * @return void Echoes HTML form and table header
     * 
     * **HTML Output:**
     * - Form element with POST method to modules.php?name=Depth_Chart_Entry&op=submit
     * - Hidden Team_Name field with team name value
     * - Table with 14 columns (Position, Player, 5 slots, Active, Min, OF, DF, OI, DI, BH)
     * - Header row with column labels
     * - Form is NOT closed (caller adds rows, then closes with renderFormFooter)
     * 
     * **Important Behaviors:**
     * - Creates opening tags only (form, table, header row)
     * - Team name is directly embedded in hidden field
     * - Slot names from parameter are used as column headers
     * - Table is ready for player rows to be added
     */
    public function renderFormHeader(string $teamName, int $teamID, array $slotNames): void;

    /**
     * Render a single player row in the depth chart form
     * 
     * @param array<string, mixed> $player Player data from database with these keys:
     *                                      - pid: Player ID
     *                                      - pos: Player position
     *                                      - name: Player name (will be HTML escaped)
     *                                      - injured: Injury status (0 or 1)
     *                                      - sta: Player stamina rating
     *                                      - dc_active: Current active setting (0 or 1)
     *                                      - dc_minutes: Current minute setting (0-40)
     *                                      - dc_of: Current offensive focus setting (0-3)
     *                                      - dc_df: Current defensive focus setting (0-3)
     *                                      - dc_oi: Current offensive intensity setting (-2 to 2)
     *                                      - dc_di: Current defensive intensity setting (-2 to 2)
     *                                      - dc_bh: Current ball handling setting (-2 to 2)
     *                                      - dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth: Position depths
     * @param int $depthCount Row counter/ordinal used in form field names (Name{depthCount}, pg{depthCount}, etc.)
     * @return void Echoes HTML table row with player data and form controls
     * 
     * **HTML Output:**
     * - Single table row with 14 cells
     * - Player position in first cell
     * - Player name as hyperlink to player page
     * - Dropdown selects for each position slot
     * - Dropdown selects for active, minutes, and settings
     * - Player name and injury status in hidden fields
     * 
     * **Important Behaviors:**
     * - Directly echoes HTML (called repeatedly to build table rows)
     * - Player name is HTML-escaped via HtmlSanitizer::safeHtmlOutput()
     * - All dropdown values are prefilled with player's current settings
     * - Minute ceiling is capped at 40 (stamina + 40, then max 40)
     * - Renders dropdowns for all 5 position slots (even if not eligible)
     * - Hidden fields preserve player metadata for submission processing
     */
    public function renderPlayerRow(array $player, int $depthCount): void;

    /**
     * Render complete form footer with reset button, submit button, and JavaScript
     * 
     * @return void Echoes HTML table row, JavaScript, buttons, and closing tags
     * 
     * **HTML Output:**
     * - JavaScript resetDepthChart() function
     * - Table footer row with two buttons in a single cell
     * - Reset button: Calls JavaScript, resets all dropdowns to defaults
     * - Submit button: Submits form to modules.php?name=Depth_Chart_Entry&op=submit
     * - Closing </form> and </table> tags
     * 
     * **JavaScript Behavior:**
     * - resetDepthChart() confirms before resetting
     * - Sets default values for each field type:
     *   - active fields: Default 1 (Yes)
     *   - Position fields (pg-c): Default 0 (No)
     *   - Other fields: Default 0 (Auto or neutral)
     * 
     * **Important Behaviors:**
     * - Provides user-friendly interface for form submission
     * - JavaScript confirmation prevents accidental resets
     * - Includes custom styling for buttons
     * - Closes both form and table elements
     */
    public function renderFormFooter(): void;

    /**
     * Render depth chart submission result page with success or error status
     * 
     * @param string $teamName Team name displayed at top of result
     * @param array<int, array<string, mixed>> $playerData Player data submitted (for display in confirmation table)
     * @param bool $success True if submission succeeded, false for error display
     * @param string $errorHtml Error messages HTML (if $success is false, displayed instead of success message)
     * @return void Echoes HTML result page
     * 
     * **HTML Output (Success):**
     * - Centered confirmation message
     * - Table showing submitted depth chart with all player data
     * 
     * **HTML Output (Failure):**
     * - Centered error heading
     * - Error messages (from $errorHtml parameter)
     * - Table showing submitted depth chart (for reference)
     * 
     * **Table Columns:**
     * - Name
     * - All position slots (from JSB::PLAYER_POSITIONS)
     * - Active, Min, OF, DF, OI, DI, BH
     * 
     * **Important Behaviors:**
     * - Success/failure determined solely by $success parameter
     * - Displays $errorHtml directly (assumed to be pre-formatted)
     * - Shows complete depth chart data for user verification
     * - Uses legacy HTML elements (font, b, center) for backward compatibility
     */
    public function renderSubmissionResult(
        string $teamName,
        array $playerData,
        bool $success,
        string $errorHtml = ''
    ): void;
}
