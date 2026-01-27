<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersViewInterface;

/**
 * View class for rendering the all-time IBL record holders page.
 *
 * All record data is static (historical facts) and stored as structured arrays.
 *
 * @see RecordHoldersViewInterface
 */
class RecordHoldersView implements RecordHoldersViewInterface
{
    /**
     * @see RecordHoldersViewInterface::render()
     */
    public function render(): string
    {
        $output = '<style>
.record-holders-page td { text-align: center; vertical-align: middle; }
.record-holders-page td img { margin: 0 auto; }
.record-holders-page .ibl-data-table { max-width: 900px; margin-left: auto; margin-right: auto; table-layout: fixed; }
.record-holders-page .cols-5 col.col-player { width: 25%; }
.record-holders-page .cols-5 col.col-team { width: 15%; }
.record-holders-page .cols-5 col.col-date { width: 30%; }
.record-holders-page .cols-5 col.col-opponent { width: 15%; }
.record-holders-page .cols-5 col.col-amount { width: 15%; }
.record-holders-page .cols-4-season col.col-player { width: 30%; }
.record-holders-page .cols-4-season col.col-team { width: 20%; }
.record-holders-page .cols-4-season col.col-season { width: 25%; }
.record-holders-page .cols-4-season col.col-amount { width: 25%; }
.record-holders-page .cols-4-team col.col-team { width: 20%; }
.record-holders-page .cols-4-team col.col-date { width: 35%; }
.record-holders-page .cols-4-team col.col-opponent { width: 20%; }
.record-holders-page .cols-4-team col.col-amount { width: 25%; }
</style>';
        $output .= '<div class="record-holders-page">';
        $output .= $this->renderPlayerSingleGameRecords();
        $output .= $this->renderPlayerFullSeasonRecords();
        $output .= $this->renderPlayerPlayoffRecords();
        $output .= $this->renderPlayerHeatRecords();
        $output .= $this->renderTeamRecords();
        $output .= '</div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 1: Regular Season (Single Game)
    // ---------------------------------------------------------------

    /**
     * Get player single-game regular season records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getPlayerSingleGameRecords(): array
    {
        return [
            'Most Points in a Single Game' => [
                ['image' => '927', 'name' => 'Bob Pettit', 'pid' => '927', 'team' => 'min', 'teamTid' => '14', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/box1731.htm', 'date' => 'January 16, 1996', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '1996', 'amount' => '80'],
            ],
            'Most Rebounds in a Single Game [tie]' => [
                ['image' => '2008', 'name' => 'Jayson Williams', 'pid' => '2008', 'team' => 'den', 'teamTid' => '15', 'teamYr' => '1998', 'boxScore' => 'ibl/archive/97-98/box2787.htm', 'date' => 'March 20, 1998', 'oppTeam' => 'sea', 'oppTid' => '22', 'oppYr' => '1998', 'amount' => '34'],
                ['image' => '2982', 'name' => 'Clyde Lovellette', 'pid' => '2982', 'team' => 'gsw', 'teamTid' => '24', 'teamYr' => '2000', 'boxScore' => 'ibl/archive/99-00/box2108.htm', 'date' => 'February 8, 2000', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '2000', 'amount' => '34'],
            ],
            'Most Assists in a Single Game' => [
                ['image' => '2421', 'name' => 'Magic Johnson', 'pid' => '2421', 'team' => 'cha', 'teamTid' => '10', 'teamYr' => '2001', 'boxScore' => 'ibl/archive/00-01/box1075.htm', 'date' => 'December 6, 2000', 'oppTeam' => 'okc', 'oppTid' => '16', 'oppYr' => '2001', 'amount' => '25'],
            ],
            'Most Steals in a Single Game' => [
                ['image' => '50', 'name' => 'Lester Conner', 'pid' => '50', 'team' => 'ind', 'teamTid' => '11', 'teamYr' => '1989', 'boxScore' => 'ibl/archive/88-89/box1195.htm', 'date' => 'December 14, 1988', 'oppTeam' => 'orl', 'oppTid' => '5', 'oppYr' => '1989', 'amount' => '15'],
            ],
            'Most Blocks in a Single Game' => [
                ['image' => '4527', 'name' => 'Satnam Singh', 'pid' => '4527', 'team' => 'det', 'teamTid' => '9', 'teamYr' => '2006', 'boxScore' => 'ibl/archive/02-03/box2953.htm', 'date' => 'March 31, 2006', 'oppTeam' => 'njn', 'oppTid' => '4', 'oppYr' => '2006', 'amount' => '18'],
            ],
            'Most Turnovers in a Single Game' => [
                ['image' => '2975', 'name' => 'Shaquille O\'Neal', 'pid' => '2975', 'team' => 'dal', 'teamTid' => '28', 'teamYr' => '2000', 'boxScore' => 'ibl/archive/99-00/box2804.htm', 'date' => 'March 21, 2000', 'oppTeam' => 'tor', 'oppTid' => '12', 'oppYr' => '2000', 'amount' => '17'],
            ],
            'Most Field Goals in a Single Game' => [
                ['image' => '927', 'name' => 'Bob Pettit', 'pid' => '927', 'team' => 'min', 'teamTid' => '14', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/box1731.htm', 'date' => 'January 16, 1996', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '1996', 'amount' => '36'],
            ],
            'Most Free Throws in a Single Game' => [
                ['image' => '131', 'name' => 'Kelly Tripucka', 'pid' => '131', 'team' => 'chi', 'teamTid' => '7', 'teamYr' => '1991', 'boxScore' => 'ibl/archive/90-91/box1108.htm', 'date' => 'December 8, 1990', 'oppTeam' => 'por', 'oppTid' => '18', 'oppYr' => '1991', 'amount' => '23'],
            ],
            'Most Three Pointers in a Single Game' => [
                ['image' => '3851', 'name' => 'Stephen Curry', 'pid' => '3851', 'team' => 'lac', 'teamTid' => '19', 'teamYr' => '2005', 'boxScore' => 'ibl/archive/04-05/box1577.htm', 'date' => 'January 7, 2005', 'oppTeam' => 'tor', 'oppTid' => '12', 'oppYr' => '2005', 'amount' => '11'],
            ],
        ];
    }

    /**
     * Get quadruple double records.
     *
     * @return array<int, array<string, string>> Quadruple double entries
     */
    private function getQuadrupleDoubles(): array
    {
        return [
            ['image' => '1481', 'name' => 'Lenny Wilkens', 'pid' => '1481', 'team' => 'mia', 'teamTid' => '2', 'teamYr' => '1993', 'boxScore' => 'ibl/archive/92-93/box1168.htm', 'date' => 'December 12, 1992', 'oppTeam' => 'det', 'oppTid' => '25', 'oppYr' => '1993', 'amount' => "12pts\n10rbs\n14ast\n10stl"],
            ['image' => '1230', 'name' => 'Michael Jordan', 'pid' => '1230', 'team' => 'det', 'teamTid' => '25', 'teamYr' => '1999', 'boxScore' => 'ibl/archive/98-99/HEAT/box710.htm', 'date' => '1998 IBL HEAT', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '1998', 'amount' => "47pts\n10rbs\n13ast\n11stl"],
            ['image' => '618', 'name' => 'Jason Kidd', 'pid' => '618', 'team' => 'uta', 'teamTid' => '13', 'teamYr' => '1999', 'boxScore' => 'ibl/archive/98-99/box2405.htm', 'date' => 'February 28, 1999', 'oppTeam' => 'sea', 'oppTid' => '22', 'oppYr' => '1999', 'amount' => "35pts\n15rbs\n14ast\n10stl"],
            ['image' => '4497', 'name' => 'Dejan Milojevic', 'pid' => '4497', 'team' => 'lac', 'teamTid' => '19', 'teamYr' => '2003', 'boxScore' => 'ibl/archive/02-03/box1227.htm', 'date' => 'December 16, 2002', 'oppTeam' => 'nor', 'oppTid' => '8', 'oppYr' => '2003', 'amount' => "21pts\n10rbs\n10ast\n11stl"],
            ['image' => '2421', 'name' => 'Magic Johnson', 'pid' => '1481', 'team' => 'cha', 'teamTid' => '10', 'teamYr' => '2005', 'boxScore' => 'ibl/archive/04-05/box2875.htm', 'date' => 'March 26, 2005', 'oppTeam' => 'uta', 'oppTid' => '25', 'oppYr' => '2005', 'amount' => "14pts\n10rbs\n14ast\n10stl"],
        ];
    }

    /**
     * Get all-star appearances record.
     *
     * @return array<string, string> All-star appearance record data
     */
    private function getAllStarRecord(): array
    {
        return [
            'image' => '304',
            'name' => 'Mitch Richmond',
            'pid' => '304',
            'teams' => 'cha,mia',
            'teamTids' => '10,2',
            'amount' => '10',
            'years' => '1989, 1990, 1991, 1992, 1993, 1994, 1995, 1996, 1997, 1998',
        ];
    }

    /**
     * Render Section 1: Player Regular Season (Single Game) records.
     *
     * @return string HTML output
     */
    private function renderPlayerSingleGameRecords(): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Regular Season (Single Game)</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">Individual Single-Game Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($this->getPlayerSingleGameRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($records as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        // Quadruple Doubles
        $output .= $this->renderCategoryHeader('Quadruple Doubles');
        $output .= $this->renderPlayerColumnHeaders();
        foreach ($this->getQuadrupleDoubles() as $record) {
            $output .= $this->renderPlayerRecordRow($record, true);
        }

        // Most All-Star Appearances
        $allStar = $this->getAllStarRecord();
        $output .= $this->renderCategoryHeader('Most All-Star Appearances');
        $output .= '<tr class="text-center">';
        $output .= '<td><strong>Player</strong></td>';
        $output .= '<td><strong>Team</strong></td>';
        $output .= '<td><strong>Amount</strong></td>';
        $output .= '<td colspan="2"><strong>Years</strong></td>';
        $output .= '</tr>';

        $teams = explode(',', $allStar['teams']);
        $teamTids = explode(',', $allStar['teamTids']);
        $teamLogos = '';
        foreach ($teams as $i => $team) {
            $teamLogos .= '<a href="../online/team.php?tid=' . $teamTids[$i] . '"><img src="images/topics/' . $team . '.png" alt="' . strtoupper($team) . '"></a> ';
        }

        $years = str_replace(', ', '<br>', $allStar['years']);

        $output .= '<tr class="text-center">';
        $output .= '<td><img src="images/player/' . $allStar['image'] . '.jpg" alt="' . $allStar['name'] . '"><strong><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $allStar['pid'] . '">' . $allStar['name'] . '</a></strong></td>';
        $output .= '<td><strong>' . $teamLogos . '</strong></td>';
        $output .= '<td><strong>' . $allStar['amount'] . '</strong></td>';
        $output .= '<td colspan="2"><strong>' . $years . '</strong></td>';
        $output .= '</tr>';

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 2: Regular Season (Full Season)
    // ---------------------------------------------------------------

    /**
     * Get player full-season regular season records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getPlayerFullSeasonRecords(): array
    {
        return [
            'Highest Scoring Average in a Regular Season' => [
                ['image' => '304', 'name' => 'Mitch Richmond', 'pid' => '304', 'team' => 'mia', 'teamTid' => '2', 'teamYr' => '1994', 'season' => '1993-94', 'amount' => '34.2'],
            ],
            'Highest Rebounding Average in a Regular Season' => [
                ['image' => '627', 'name' => 'Hanamichi Sakuragi', 'pid' => '627', 'team' => 'chi', 'teamTid' => '7', 'teamYr' => '1995', 'season' => '1994-95', 'amount' => '19.6'],
            ],
            'Highest Assist Average in a Regular Season' => [
                ['image' => '127', 'name' => 'Muggsy Bogues', 'pid' => '127', 'team' => 'bkn', 'teamTid' => '4', 'teamYr' => '1989', 'season' => '1988-89', 'amount' => '12.0'],
            ],
            'Highest Steals Average in a Regular Season' => [
                ['image' => '616', 'name' => 'Eddie Jones', 'pid' => '616', 'team' => 'ind', 'teamTid' => '11', 'teamYr' => '1994', 'season' => '1993-94', 'amount' => '4.7'],
            ],
            'Highest Blocks Average in a Regular Season' => [
                ['image' => '172', 'name' => 'Mark Eaton', 'pid' => '172', 'team' => 'chi', 'teamTid' => '7', 'teamYr' => '1989', 'season' => '1990-91', 'amount' => '6.0'],
            ],
        ];
    }

    /**
     * Render Section 2: Player Regular Season (Full Season) records.
     *
     * @return string HTML output
     */
    private function renderPlayerFullSeasonRecords(): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Regular Season (Full Season) [minimum 50 games]</h2>';
        $output .= '<table class="ibl-data-table cols-4-season">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-season"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="4">Season Average Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($this->getPlayerFullSeasonRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong>Player</strong></td>';
            $output .= '<td><strong>Team</strong></td>';
            $output .= '<td><strong>Season</strong></td>';
            $output .= '<td><strong>Amount</strong></td>';
            $output .= '</tr>';
            foreach ($records as $record) {
                $output .= '<tr class="text-center">';
                $output .= '<td><img src="images/player/' . $record['image'] . '.jpg" alt="' . $record['name'] . '"><strong><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $record['pid'] . '">' . $record['name'] . '</a></strong></td>';
                $output .= '<td><strong><a href="../online/team.php?tid=' . $record['teamTid'] . '&amp;yr=' . $record['teamYr'] . '"><img src="images/topics/' . $record['team'] . '.png" alt="' . strtoupper($record['team']) . '"></a></strong></td>';
                $output .= '<td><strong>' . $record['season'] . '</strong></td>';
                $output .= '<td><strong>' . $record['amount'] . '</strong></td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 3: Playoffs
    // ---------------------------------------------------------------

    /**
     * Get player playoff records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getPlayerPlayoffRecords(): array
    {
        return [
            'Most Points in a Single Game' => [
                ['image' => '1230', 'name' => 'Michael Jordan', 'pid' => '1230', 'team' => 'mia', 'teamTid' => '2', 'teamYr' => '2003', 'boxScore' => 'ibl/archive/02-03/box6300.htm', 'date' => 'June 21, 2003', 'oppTeam' => 'nyk', 'oppTid' => '3', 'oppYr' => '2003', 'amount' => '65'],
            ],
            'Most Rebounds in a Single Game' => [
                ['image' => '627', 'name' => 'Hanamichi Sakuragi', 'pid' => '627', 'team' => 'chi', 'teamTid' => '7', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/box6106.htm', 'date' => 'June 8, 1996', 'oppTeam' => 'nyk', 'oppTid' => '3', 'oppYr' => '1996', 'amount' => '35'],
            ],
            'Most Assists in a Single Game' => [
                ['image' => '2421', 'name' => 'Magic Johnson', 'pid' => '2421', 'team' => 'cha', 'teamTid' => '10', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/box6046.htm', 'date' => 'May 4, 1996', 'oppTeam' => 'nor', 'oppTid' => '8', 'oppYr' => '1996', 'amount' => '20'],
            ],
            'Most Steals in a Single Game' => [
                ['image' => '190', 'name' => 'Fat Lever', 'pid' => '190', 'team' => 'lac', 'teamTid' => '19', 'teamYr' => '1989', 'boxScore' => 'ibl/archive/88-89/box6122.htm', 'date' => 'May 9, 1989', 'oppTeam' => 'gsw', 'oppTid' => '24', 'oppYr' => '1989', 'amount' => '14'],
            ],
            'Most Blocks in a Single Game' => [
                ['image' => '643', 'name' => 'D.J. Mbenga', 'pid' => '643', 'team' => 'min', 'teamTid' => '14', 'teamYr' => '1993', 'boxScore' => 'ibl/archive/92-93/box6052.htm', 'date' => 'June 4, 1993', 'oppTeam' => 'lac', 'oppTid' => '19', 'oppYr' => '1993', 'amount' => '12'],
            ],
            'Most Field Goals in a Single Game' => [
                ['image' => '228', 'name' => 'Clyde Drexler', 'pid' => '228', 'team' => 'por', 'teamTid' => '18', 'teamYr' => '1990', 'boxScore' => 'ibl/archive/89-90/box6107.htm', 'date' => 'May 8, 1989', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '1990', 'amount' => '22'],
            ],
            'Most Free Throws in a Single Game' => [
                ['image' => '927', 'name' => 'Bob Pettit', 'pid' => '927', 'team' => 'min', 'teamTid' => '14', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/box6271.htm', 'date' => 'May 19, 1996', 'oppTeam' => 'van', 'oppTid' => '20', 'oppYr' => '1996', 'amount' => '19'],
            ],
            'Most Three Pointers in a Single Game' => [
                ['image' => '143', 'name' => 'Reggie Miller', 'pid' => '143', 'team' => 'atl', 'teamTid' => '9', 'teamYr' => '1992', 'boxScore' => 'ibl/archive/91-92/box6360.htm', 'date' => 'May 1, 1992', 'oppTeam' => 'bos', 'oppTid' => '1', 'oppYr' => '1992', 'amount' => '10'],
            ],
        ];
    }

    /**
     * Render Section 3: Player Playoff records.
     *
     * @return string HTML output
     */
    private function renderPlayerPlayoffRecords(): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Playoffs</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">Playoff Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($this->getPlayerPlayoffRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($records as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 4: H.E.A.T.
    // ---------------------------------------------------------------

    /**
     * Get player H.E.A.T. records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getPlayerHeatRecords(): array
    {
        return [
            'Most Points in a Single Game' => [
                ['image' => '656', 'name' => 'Tony Dumas', 'pid' => '656', 'team' => 'orl', 'teamTid' => '5', 'teamYr' => '1995', 'boxScore' => 'ibl/archive/94-95/HEAT/box665.htm', 'date' => '1994 HEAT', 'oppTeam' => 'ind', 'oppTid' => '11', 'oppYr' => '1995', 'amount' => '65'],
            ],
            'Most Rebounds in a Single Game [tie]' => [
                ['image' => '3562', 'name' => 'Marcus Camby', 'pid' => '3562', 'team' => 'van', 'teamTid' => '20', 'teamYr' => '2007', 'boxScore' => 'ibl/archive/06-07/HEAT/box579.htm', 'date' => '2006 HEAT', 'oppTeam' => 'tor', 'oppTid' => '12', 'oppYr' => '2007', 'amount' => '31'],
                ['image' => '156', 'name' => 'Dennis Rodman', 'pid' => '156', 'team' => 'mia', 'teamTid' => '2', 'teamYr' => '1993', 'boxScore' => 'ibl/archive/92-93/HEAT/box800.htm', 'date' => '1992 HEAT', 'oppTeam' => 'nor', 'oppTid' => '8', 'oppYr' => '1993', 'amount' => '31'],
                ['image' => '1761', 'name' => 'Tyson Chandler', 'pid' => '1761', 'team' => 'phx', 'teamTid' => '23', 'teamYr' => '1995', 'boxScore' => 'ibl/archive/94-95/HEAT/box525.htm', 'date' => '1994 HEAT', 'oppTeam' => 'orl', 'oppTid' => '5', 'oppYr' => '1995', 'amount' => '31'],
            ],
            'Most Assists in a Single Game' => [
                ['image' => '127', 'name' => 'Muggsy Bogues', 'pid' => '127', 'team' => 'bkn', 'teamTid' => '2', 'teamYr' => '1989', 'boxScore' => 'ibl/archive/88-89/HEAT/box570.htm', 'date' => '1988 HEAT', 'oppTeam' => 'phx', 'oppTid' => '23', 'oppYr' => '1989', 'amount' => '21'],
            ],
            'Most Steals in a Single Game' => [
                ['image' => '1999', 'name' => 'Tony Jackson', 'pid' => '1999', 'team' => 'nor', 'teamTid' => '8', 'teamYr' => '2000', 'boxScore' => 'ibl/archive/99-00/HEAT/box595.htm', 'date' => '1999 HEAT', 'oppTeam' => 'den', 'oppTid' => '15', 'oppYr' => '2000', 'amount' => '13'],
            ],
            'Most Blocks in a Single Game' => [
                ['image' => '1765', 'name' => 'Samuel Dalembert', 'pid' => '1765', 'team' => 'mil', 'teamTid' => '6', 'teamYr' => '1995', 'boxScore' => 'ibl/archive/94-95/HEAT/box697.htm', 'date' => '1984 HEAT', 'oppTeam' => 'den', 'oppTid' => '15', 'oppYr' => '1995', 'amount' => '12'],
            ],
            'Most Field Goals in a Single Game' => [
                ['image' => '2006', 'name' => 'Cedric Ceballos', 'pid' => '2006', 'team' => 'uta', 'teamTid' => '13', 'teamYr' => '2001', 'boxScore' => 'ibl/archive/00-01/HEAT/box557.htm', 'date' => '2000 HEAT', 'oppTeam' => 'lal', 'oppTid' => '21', 'oppYr' => '2001', 'amount' => '26'],
            ],
            'Most Free Throws in a Single Game' => [
                ['image' => '304', 'name' => 'Mitch Richmond', 'pid' => '304', 'team' => 'cha', 'teamTid' => '10', 'teamYr' => '1989', 'boxScore' => 'ibl/archive/88-89/HEAT/box500.htm', 'date' => '1988 HEAT', 'oppTeam' => 'lac', 'oppTid' => '19', 'oppYr' => '1989', 'amount' => '20'],
            ],
            'Most Three Pointers in a Single Game [tie]' => [
                ['image' => '628', 'name' => 'David Wesley', 'pid' => '628', 'team' => 'den', 'teamTid' => '15', 'teamYr' => '1993', 'boxScore' => 'ibl/archive/92-93/HEAT/box520.htm', 'date' => '1992 HEAT', 'oppTeam' => 'ind', 'oppTid' => '11', 'oppYr' => '1993', 'amount' => '9'],
                ['image' => '1252', 'name' => 'Caris LeVert', 'pid' => '1252', 'team' => 'bkn', 'teamTid' => '4', 'teamYr' => '1995', 'boxScore' => 'ibl/archive/94-95/HEAT/box573.htm', 'date' => '1994 HEAT', 'oppTeam' => 'den', 'oppTid' => '15', 'oppYr' => '1995', 'amount' => '9'],
                ['image' => '668', 'name' => 'J.J. Barea', 'pid' => '668', 'team' => 'bos', 'teamTid' => '1', 'teamYr' => '1996', 'boxScore' => 'ibl/archive/95-96/HEAT/box584.htm', 'date' => '1995 HEAT', 'oppTeam' => 'lal', 'oppTid' => '21', 'oppYr' => '1995', 'amount' => '9'],
            ],
        ];
    }

    /**
     * Render Section 4: Player H.E.A.T. records.
     *
     * @return string HTML output
     */
    private function renderPlayerHeatRecords(): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, H.E.A.T.</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">H.E.A.T. Tournament Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($this->getPlayerHeatRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($records as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 5: Team Records
    // ---------------------------------------------------------------

    /**
     * Get team single-game records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getTeamGameRecords(): array
    {
        return [
            'Most Points in a Single Game' => [
                ['team' => 'uta', 'boxScore' => 'ibl/archive/99-00/box940.htm', 'date' => 'November 30, 1999', 'oppTeam' => 'gsw', 'amount' => '180'],
            ],
            'Fewest Points in a Single Game' => [
                ['team' => 'bos', 'boxScore' => 'ibl/archive/89-90/box534.htm', 'date' => 'November 3, 1989', 'oppTeam' => 'chi', 'amount' => '56'],
            ],
            'Most Points in a Single Half' => [
                ['team' => 'chi', 'boxScore' => 'ibl/archive/95-96/box575.htm', 'date' => 'November 6, 1995', 'oppTeam' => 'atl', 'amount' => '93'],
            ],
            'Fewest Points in a Single Half' => [
                ['team' => 'min', 'boxScore' => 'ibl/archive/88-89/box1625.htm', 'date' => 'January 9, 1989', 'oppTeam' => 'van', 'amount' => '20'],
            ],
            'Largest Margin of Victory [overall]' => [
                ['team' => 'mil', 'boxScore' => 'ibl/archive/02-03/box2650.htm', 'date' => 'March 11, 2003', 'oppTeam' => 'chi', 'amount' => '75'],
            ],
            'Largest Margin of Victory [playoffs]' => [
                ['team' => 'bkn', 'boxScore' => 'ibl/archive/90-91/box6002.htm', 'date' => 'May 1, 1991', 'oppTeam' => 'mil', 'amount' => '60'],
            ],
            'Most Rebounds in a Single Game' => [
                ['team' => 'bos', 'boxScore' => 'ibl/archive/98-99/box2060.htm', 'date' => 'February 5, 1999', 'oppTeam' => 'njn', 'amount' => '89'],
            ],
            'Most Assists in a Single Game' => [
                ['team' => 'lac', 'boxScore' => 'ibl/archive/05-06/box716.htm', 'date' => 'November 15, 2005', 'oppTeam' => 'lva', 'amount' => '45'],
            ],
            'Most Steals in a Single Game' => [
                ['team' => 'ind', 'boxScore' => 'ibl/88-89/box1847.htm', 'date' => 'January 24, 1989', 'oppTeam' => 'nyk', 'amount' => '33'],
            ],
            'Most Blocks in a Single Game [tie]' => [
                ['team' => 'van', 'boxScore' => 'ibl/archive/88-89/box2094.htm', 'date' => 'February 7, 1989', 'oppTeam' => 'phx', 'amount' => '21'],
                ['team' => 'atl', 'boxScore' => 'ibl/archive/02-03/box2804.htm', 'date' => 'March 2, 2002', 'oppTeam' => 'det', 'amount' => '21'],
            ],
            'Most Field Goals in a Single Game' => [
                ['team' => 'min', 'boxScore' => 'ibl/archive/95-96/box1731.htm', 'date' => 'January 16, 1996', 'oppTeam' => 'van', 'amount' => '76'],
            ],
            'Most Free Throws in a Single Game' => [
                ['team' => 'mil', 'boxScore' => 'ibl/archive/91-92/box1287.htm', 'date' => 'December 20, 1991', 'oppTeam' => 'ind', 'amount' => '47'],
            ],
            'Most Three Pointers in a Single Game' => [
                ['team' => 'san', 'boxScore' => 'ibl/archive/91-92/box3273.htm', 'date' => 'April 19, 1992', 'oppTeam' => 'min', 'amount' => '24'],
            ],
        ];
    }

    /**
     * Get team season/franchise records.
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getTeamSeasonRecords(): array
    {
        return [
            'Best Season Record' => [
                ['team' => 'chi', 'season' => '1992-93', 'amount' => '71-11'],
            ],
            'Worst Season Record' => [
                ['team' => 'por', 'season' => '1994-95', 'amount' => '4-78'],
            ],
            'Best Season Record, Start' => [
                ['team' => 'nyk', 'season' => '1996-97', 'amount' => '12-0'],
            ],
            'Worst Season Record, Start' => [
                ['team' => 'lvt', 'season' => '2001-02', 'amount' => '0-31'],
            ],
            'Longest Winning Streak' => [
                ['team' => 'lac', 'season' => '2004-05, 2005-06', 'amount' => '44'],
            ],
            'Longest Losing Streak' => [
                ['team' => 'bos', 'season' => '1989-90', 'amount' => '34'],
            ],
        ];
    }

    /**
     * Get team franchise records (with year lists).
     *
     * @return array<string, array<int, array<string, string>>> Records grouped by category
     */
    private function getTeamFranchiseRecords(): array
    {
        return [
            'Most Playoff Appearances' => [
                ['team' => 'bkn', 'amount' => '7', 'years' => "1989\n1990\n1991\n1992\n1993\n1994\n1995"],
            ],
            'Most Division Championships' => [
                ['team' => 'mia', 'amount' => '4', 'years' => "1988-89\n1991-92\n1992-93\n1993-94"],
            ],
            'Most IBL Finals Appearances [tie]' => [
                ['team' => 'bkn', 'amount' => '2', 'years' => "1990\n1991"],
                ['team' => 'lac', 'amount' => '2', 'years' => "1989\n1992"],
            ],
            'Most IBL Championships' => [
                ['team' => 'bkn', 'amount' => '2', 'years' => "1990\n1991"],
            ],
        ];
    }

    /**
     * Render Section 5: Team records.
     *
     * @return string HTML output
     */
    private function renderTeamRecords(): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Team</h2>';
        $output .= '<table class="ibl-data-table cols-4-team">';
        $output .= '<colgroup><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="4">Team Records</th></tr></thead>';
        $output .= '<tbody>';

        // Game records (with box scores and opponents)
        foreach ($this->getTeamGameRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong>Team</strong></td>';
            $output .= '<td><strong>Date</strong></td>';
            $output .= '<td><strong>Opponent</strong></td>';
            $output .= '<td><strong>Amount</strong></td>';
            $output .= '</tr>';
            foreach ($records as $record) {
                $output .= $this->renderTeamGameRow($record);
            }
        }

        // Season records (team, season, record/amount)
        foreach ($this->getTeamSeasonRecords() as $category => $records) {
            $colLabel = ($category === 'Best Season Record' || $category === 'Worst Season Record') ? 'Record' : 'Amount';
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong>Team</strong></td>';
            $output .= '<td><strong>Season</strong></td>';
            $output .= '<td colspan="2"><strong>' . $colLabel . '</strong></td>';
            $output .= '</tr>';
            foreach ($records as $record) {
                $output .= '<tr class="text-center">';
                $output .= '<td><strong><img src="images/topics/' . $record['team'] . '.png" alt="' . strtoupper($record['team']) . '"></strong></td>';
                $output .= '<td><strong>' . $record['season'] . '</strong></td>';
                $output .= '<td colspan="2"><strong>' . $record['amount'] . '</strong></td>';
                $output .= '</tr>';
            }
        }

        // Franchise records (team, amount, years)
        foreach ($this->getTeamFranchiseRecords() as $category => $records) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong>Team</strong></td>';
            $output .= '<td><strong>Amount</strong></td>';
            $output .= '<td colspan="2"><strong>Years</strong></td>';
            $output .= '</tr>';
            foreach ($records as $record) {
                $years = str_replace("\n", '<br>', $record['years']);
                $output .= '<tr class="text-center">';
                $output .= '<td><strong><img src="images/topics/' . $record['team'] . '.png" alt="' . strtoupper($record['team']) . '"></strong></td>';
                $output .= '<td><strong>' . $record['amount'] . '</strong></td>';
                $output .= '<td colspan="2"><strong>' . $years . '</strong></td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Shared rendering helpers
    // ---------------------------------------------------------------

    /**
     * Render a category sub-header row.
     *
     * @param string $category Category name
     * @param int $colspan Number of columns to span
     * @return string HTML table row
     */
    private function renderCategoryHeader(string $category, int $colspan = 5): string
    {
        return '<tr class="text-center"><td colspan="' . $colspan . '"><strong><em>' . $category . '</em></strong></td></tr>';
    }

    /**
     * Render standard player column headers (Player, Team, Date, Opponent, Amount).
     *
     * @return string HTML table row
     */
    private function renderPlayerColumnHeaders(): string
    {
        return '<tr class="text-center">'
            . '<td><strong>Player</strong></td>'
            . '<td><strong>Team</strong></td>'
            . '<td><strong>Date</strong></td>'
            . '<td><strong>Opponent</strong></td>'
            . '<td><strong>Amount</strong></td>'
            . '</tr>';
    }

    /**
     * Render a single player record row.
     *
     * @param array<string, string> $record Record data
     * @param bool $multiLineAmount Whether amount contains multiple lines (e.g., quadruple doubles)
     * @return string HTML table row
     */
    private function renderPlayerRecordRow(array $record, bool $multiLineAmount = false): string
    {
        $amount = $multiLineAmount
            ? '<strong>' . str_replace("\n", '<br>', $record['amount']) . '</strong>'
            : '<strong>' . $record['amount'] . '</strong>';

        $output = '<tr class="text-center">';
        $output .= '<td><img src="images/player/' . $record['image'] . '.jpg" alt="' . $record['name'] . '"><strong><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $record['pid'] . '">' . $record['name'] . '</a></strong></td>';
        $output .= '<td><strong><a href="../online/team.php?tid=' . $record['teamTid'] . '&amp;yr=' . $record['teamYr'] . '"><img src="images/topics/' . $record['team'] . '.png" alt="' . strtoupper($record['team']) . '"></a></strong></td>';
        $output .= '<td><strong><a href="' . $record['boxScore'] . '">' . $record['date'] . '</a></strong></td>';
        $output .= '<td><strong><a href="../online/team.php?tid=' . $record['oppTid'] . '&amp;yr=' . $record['oppYr'] . '"><img src="images/topics/' . $record['oppTeam'] . '.png" alt="' . strtoupper($record['oppTeam']) . '"></a></strong></td>';
        $output .= '<td>' . $amount . '</td>';
        $output .= '</tr>';

        return $output;
    }

    /**
     * Render a team game record row.
     *
     * @param array<string, string> $record Record data
     * @return string HTML table row
     */
    private function renderTeamGameRow(array $record): string
    {
        $output = '<tr class="text-center">';
        $output .= '<td><strong><img src="images/topics/' . $record['team'] . '.png" alt="' . strtoupper($record['team']) . '"></strong></td>';
        $output .= '<td><strong><a href="' . $record['boxScore'] . '">' . $record['date'] . '</a></strong></td>';
        $output .= '<td><strong><img src="images/topics/' . $record['oppTeam'] . '.png" alt="' . strtoupper($record['oppTeam']) . '"></strong></td>';
        $output .= '<td><strong>' . $record['amount'] . '</strong></td>';
        $output .= '</tr>';

        return $output;
    }
}
