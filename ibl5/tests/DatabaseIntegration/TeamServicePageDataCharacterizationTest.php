<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use PHPUnit\Framework\Attributes\Group;
use Team\TeamRepository;
use Team\TeamService;

/**
 * Golden-master characterization net for Team\TeamService::getTeamPageData().
 *
 * This file pins the CURRENT byte-for-byte output of getTeamPageData() so a
 * FUTURE refactor (moving view instantiation out of TeamService into a
 * controller) can run green-green against a frozen baseline. It adds NO
 * production code — every method here captures and freezes real behavior.
 *
 * Golden discipline (capture-and-freeze, never imagine): each HTML blob is a
 * `G_*` nowdoc class constant whose body was captured verbatim from a real run
 * against the deterministic fixtures below — never hand-written. See the plan
 * `god-class-net-teamservice.md` for the capture procedure. Re-capture by
 * running this class with the env var CAPTURE_GOLDENS=1 (which dumps every
 * blob to ibl5/.goldens/<label>.txt) and regenerating the constants.
 *
 * Determinism: a synthetic team "CharTest" (teamid 99, sole occupant of a
 * uniquely-named division/conference → standings position deterministically 1)
 * is self-seeded inside each test's rolled-back transaction. Every repository
 * query getTeamPageData fans out to is name- or id-scoped to this team, so the
 * goldens never depend on ambient db-seed.sql rows. Season reads the league
 * 'ibl' settings (Regular Season / ending year 2026). No date/random tokens
 * leak into the render path (verified: no date()/rand()/uniqid() in the
 * TeamService → view chain).
 */
#[Group('database')]
final class TeamServicePageDataCharacterizationTest extends DatabaseTestCase
{
    private const TEAM_ID = 99;
    private const TEAM_NAME = 'CharTest';
    private const TEAM_CITY = 'Testville';

    private TeamService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Real wiring: TeamService builds its own TeamQueryRepository, League,
        // Season and TeamTableService internally. LeagueContext::getConfig()
        // for the default 'ibl' league returns a hardcoded images_path
        // ('images/') with no DB hit, so the real object is deterministic.
        $this->service = new TeamService($this->db, new TeamRepository($this->db), new LeagueContext());
    }

    /**
     * Seed a self-contained, deterministic current-season team.
     *
     * One ibl_team_info row (fixed colors/arena/capacity/owner), one
     * ibl_standings row in a uniquely-named division+conference (sole occupant
     * → position 1), one ibl_power row. Every value is fixed; nothing is read
     * from ambient seed rows. The 'ibl' "Current Season Phase"/"Current Season
     * Ending Year" settings already exist in the loaded seed (Regular Season /
     * 2026).
     */
    private function seedCharTeam(
        int $teamid = self::TEAM_ID,
        string $name = self::TEAM_NAME,
        string $city = self::TEAM_CITY,
    ): void {
        $this->insertRow('ibl_team_info', [
            'teamid' => $teamid,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => '102030',
            'color2' => 'A0B0C0',
            'arena' => 'Test Arena',
            'capacity' => 18000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);

        $this->insertRow('ibl_standings', [
            'teamid' => $teamid,
            'team_name' => $name,
            'pct' => 0.600,
            'league_record' => '12-8',
            'wins' => 12,
            'losses' => 8,
            'conference' => 'CharTestConf',
            'conf_record' => '7-5',
            'conf_gb' => 0.0,
            'division' => 'CharTestDiv',
            'div_record' => '4-2',
            'div_gb' => 0.0,
            'home_record' => '8-2',
            'away_record' => '4-6',
            'games_unplayed' => 62,
        ]);

        $this->insertRow('ibl_power', [
            'teamid' => $teamid,
            'ranking' => 5,
            'last_win' => 3,
            'last_loss' => 1,
            'streak_type' => 'W',
            'streak' => 2,
            'sos' => 0.500,
            'remaining_sos' => 0.510,
        ]);
    }

    /**
     * Capture-or-assert a golden HTML blob.
     *
     * In capture mode (env CAPTURE_GOLDENS=1) the actual value is dumped to
     * ibl5/.goldens/<label>.txt for constant regeneration and no assertion is
     * made. Otherwise the value is asserted byte-for-byte against the frozen
     * `G_<UPPER(label)>` class constant; a missing constant FAILS (never passes
     * vacuously).
     */
    private function assertGolden(string $label, string $actual): void
    {
        if (getenv('CAPTURE_GOLDENS') === '1') {
            $dir = __DIR__ . '/../../.goldens';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir . '/' . $label . '.txt', $actual);
            return;
        }

        $const = self::class . '::G_' . strtoupper($label);
        self::assertTrue(defined($const), "Missing golden constant for label '$label' ($const)");
        self::assertSame(constant($const), $actual, "Golden mismatch for label '$label'");
    }

    // === GENERATED GOLDENS START ===
    // Nowdoc `G_*` constants are spliced in here from ibl5/.goldens/*.txt.
    // Do not edit by hand — re-capture with CAPTURE_GOLDENS=1 and regenerate.

    private const G_AWARDS_EMPTY_ACCOMPLISHMENTS = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Awards</h2></div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">GM History</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2020-2026</span> Test Owner</li></ul></div></div>
GOLDEN;

    private const G_AWARDS_GM_AND_TEAM = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Awards</h2></div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">GM History</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2020-2026</span> Test Owner</li></ul></div><div class="team-card__body team-card__body--tight team-card__body--bordered"><strong class="team-card__section-label">Team Accomplishments</strong></div><div class="team-card__body"><ul class="team-awards-list"><li><span class="award-year">2024</span> Atlantic Division Champions</li></ul></div></div>
GOLDEN;

    private const G_BASELINE_AWARDSCARD = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_CURRENTSEASONCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_BASELINE_DRAFTPICKSTABLE = <<<'GOLDEN'
<ul class="draft-picks-list"><li class="draft-picks-list__item"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1"><img class="draft-picks-list__logo" src="images/logo/Metros.png" height="24" width="24" alt="Metros"></a><div class="draft-picks-list__info"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1">2027 R1 New York Metros</a></div></li></ul>
GOLDEN;

    private const G_BASELINE_FRANCHISEHISTORYCARD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_BASELINE_RAFTERS = <<<'GOLDEN'

GOLDEN;

    private const G_BASELINE_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_CURRENT_FKA = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">f.k.a.</span><span class="team-info-list__value">Old City OldName (2020-2021)</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_CURRENT_NO_FKA = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_CURRENT_NO_POWER = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"></div></div>
GOLDEN;

    private const G_CURRENT_POPULATED = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div><div class="team-card__body"><div class="team-info-list"><span class="team-info-list__label">Team</span><span class="team-info-list__value">CharTest</span><span class="team-info-list__label">Record</span><span class="team-info-list__value">12-8</span><span class="team-info-list__label">Arena</span><span class="team-info-list__value">Test Arena</span><span class="team-info-list__label">Capacity</span><span class="team-info-list__value">18000</span><span class="team-info-list__label">Conference</span><span class="team-info-list__value">CharTestConf (1st)</span><span class="team-info-list__label">Division</span><span class="team-info-list__value">CharTestDiv (1st)</span><span class="team-info-list__label">Games Back</span><span class="team-info-list__value">0</span><span class="team-info-list__label">Home</span><span class="team-info-list__value">8-2</span><span class="team-info-list__label">Road</span><span class="team-info-list__value">4-6</span><span class="team-info-list__label">Last 10</span><span class="team-info-list__value">3-1</span></div></div></div>
GOLDEN;

    private const G_DRAFT_EMPTY = <<<'GOLDEN'
<ul class="draft-picks-list"></ul>
GOLDEN;

    private const G_DRAFT_POPULATED = <<<'GOLDEN'
<ul class="draft-picks-list"><li class="draft-picks-list__item"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1"><img class="draft-picks-list__logo" src="images/logo/Metros.png" height="24" width="24" alt="Metros"></a><div class="draft-picks-list__info"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1">2027 R1 New York Metros</a></div></li></ul>
GOLDEN;

    private const G_FRANCHISE_BEST_BOLD = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">60-22 (0.732)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">41-41 (0.500)</span></li></ul><div class="team-card__footer">Totals: 101-63 (0.616)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_BEST_TIEBREAK = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">50-50 (0.500)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">41-41 (0.500)</span></li></ul><div class="team-card__footer">Totals: 91-91 (0.500)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_EMPTY_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_HEAT_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2025">2024 CharTest</a> <span class="record">6-2 (0.750)</span></strong></li></ul><div class="team-card__footer">Totals: 6-2 (0.750)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_NO_PLAYOFF = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_LOSS = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result">2099 &mdash; Rivals 4, CharTest 1</li></ul><div class="team-card__footer">Games: 1-4 (0.200) &middot; Series: 0-1 (0.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 1-4 (0.200) &middot; Series: 0-1 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_MULTIROUND = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 1</li></ul><div class="team-card__footer">Games: 4-1 (0.800) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">Conference Semis</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">Conference Finals</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 3</li></ul><div class="team-card__footer">Games: 4-3 (0.571) &middot; Series: 1-0 (1.000)</div><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">IBL Finals</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 0</li></ul><div class="team-card__footer">Games: 4-0 (1.000) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 16-6 (0.727) &middot; Series: 4-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_PLAYOFF_WIN = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_REGULAR_HISTORY = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"><li><strong><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024">2023-2024 CharTest</a> <span class="record">55-27 (0.671)</span></strong></li><li><a href="./modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2023">2022-2023 CharTest</a> <span class="record">40-42 (0.488)</span></li></ul><div class="team-card__footer">Totals: 95-69 (0.579)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__footer team-card__footer--bold">Post-Season: 0-0 (0.000) &middot; Series: 0-0 (0.000)</div></div></div></div>
GOLDEN;

    private const G_FRANCHISE_ROUND5_DROPPED = <<<'GOLDEN'
<div class="team-card" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div><div class="franchise-history-columns"><div class="franchise-history-column"><h3 class="franchise-history-column__title">H.E.A.T.</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Regular Season</h3><ul class="team-history-list"></ul><div class="team-card__footer">Totals: 0-0 (0.000)</div></div><div class="franchise-history-column"><h3 class="franchise-history-column__title">Playoffs</h3><div class="team-card__body team-card__body--tight"><strong class="team-card__section-label">First Round</strong></div><ul class="team-history-list team-history-list--padded"><li class="playoff-result playoff-result--win">2099 &mdash; CharTest 4, Rivals 2</li></ul><div class="team-card__footer">Games: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div><div class="team-card__footer team-card__footer--bold">Post-Season: 4-2 (0.667) &middot; Series: 1-0 (1.000)</div></div></div></div>
GOLDEN;

    private const G_RAFTERS_ALL_THREE = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2022<br>CharTest<br>Eastern Conf. Champions</strong></div></div><div class="banners-row"><div class="banner-item"><strong>2023<br>CharTest<br>Atlantic Div. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_AS_CLAUSE = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>Old CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_CONF_2V3 = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2021<br>CharTest<br>Eastern Conf. Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner2.gif')"><strong>2022<br>CharTest<br>Western Conf. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_DIV_ARMS = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item"><strong>2021<br>CharTest<br>Atlantic Div. Champions</strong></div><div class="banner-item"><strong>2022<br>CharTest<br>Central Div. Champions</strong></div><div class="banner-item"><strong>2023<br>CharTest<br>Midwest Div. Champions</strong></div><div class="banner-item"><strong>2024<br>CharTest<br>Pacific Div. Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_FIVE_PER_ROW = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2019<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2020<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2022<br>CharTest<br>IBL Champions</strong></div><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2023<br>CharTest<br>IBL Champions</strong></div></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2024<br>CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_RAFTERS_UNKNOWN_DROPPED = <<<'GOLDEN'
<div class="banners-container" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><div class="banners-header"><h2>CharTest Banners</h2></div><div class="banners-row"><div class="banner-item" style="--banner-bg-image: url('./images/banners/banner1.gif')"><strong>2021<br>CharTest<br>IBL Champions</strong></div></div></div>
GOLDEN;

    private const G_TABLE_AVG_S = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s" selected>Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.00</td>
            <td>0.00</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_BOGUS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_CHUNK = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk" selected>Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_CONTRACTS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table contracts-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts" selected>Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>Age</th>
            <th>Exp</th>
            <th class="sep-r-team">Bird</th>
            <th class="col-salary">23-24</th>
            <th class="col-salary">24-25</th>
            <th class="col-salary">25-26</th>
            <th class="col-salary">26-27</th>
            <th class="col-salary">27-28</th>
            <th class="col-salary sep-r-team">28-29</th>
            <th>Tal</th>
            <th>Skl</th>
            <th class="sep-r-team">Int</th>
            <th>Loy</th>
            <th>PFW</th>
            <th>PT</th>
            <th>Sec</th>
            <th>Trd</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>27</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td class="col-salary">1500</td>
                        <td class="col-salary">1600</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
                        <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>27</td>
            <td>5</td>
            <td class="sep-r-team">3</td>
            <td class="col-salary">1500</td>
                        <td class="col-salary">1600</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
                        <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td></td>
            <td class="sticky-col">Cap Totals</td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td class="col-salary">3000</td>
            <td class="col-salary">3200</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary">0</td>
            <td class="col-salary sep-r-team">0</td>
            <td></td>
            <td></td>
            <td class="sep-r-team"></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="tfoot-legend">
            <td colspan="19" class="text-left">
                Key: &nbsp; <em>(Waived)*</em> &nbsp; Becomes Free Agent^ &nbsp; Eligible for Rookie Option/Extension 0* (hover/tap to reveal link)
            </td>
        </tr>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TABLE_HISTORICAL = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99&amp;yr=2024&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;yr=2024&amp;display=contracts">Contracts</a></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full player-expiring">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev player-expiring">A. Anchor</span></a></td>            <td></td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_PER36 = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins" selected>Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th>mpg</th>
            <th class="sep-r-team">36min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-weak">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td class="sep-r-team">0.000</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
            <td>0.0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_RATINGS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>PG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>SG</td>
            <td class="sep-r-team">27</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_SPLIT_HOME = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home" selected>Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Home</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_SPLIT_NULL = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Home</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_SPLIT_WINS = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins" selected>Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong>Wins</strong> split.</td></tr>
    </tbody>
</table>
        
GOLDEN;

    private const G_TABLE_TOTAL_S = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #102030; --team-color-secondary: #A0B0C0;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=99" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+v.substring(6)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display='+d+s"><optgroup label="Views"><option value="ratings">Ratings</option><option value="total_s" selected>Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_13">vs. Apollos</option><option value="split:vs_16">vs. Blizzard</option><option value="split:vs_15">vs. Blues</option><option value="split:vs_18">vs. Bucks</option><option value="split:vs_22">vs. Cavaliers</option><option value="split:vs_3">vs. Cougars</option><option value="split:vs_4">vs. Diesels</option><option value="split:vs_9">vs. Flames</option><option value="split:vs_25">vs. Generals</option><option value="split:vs_17">vs. Huskies</option><option value="split:vs_27">vs. Jazz</option><option value="split:vs_21">vs. Mavericks</option><option value="split:vs_1">vs. Metros</option><option value="split:vs_5">vs. Minutemen</option><option value="split:vs_8">vs. Monarchs</option><option value="split:vs_24">vs. Nets</option><option value="split:vs_19">vs. Nuggets</option><option value="split:vs_26">vs. Pacers</option><option value="split:vs_14">vs. Phoenixes</option><option value="split:vs_20">vs. Pilots</option><option value="split:vs_11">vs. Pioneers</option><option value="split:vs_6">vs. Rage</option><option value="split:vs_12">vs. Royals</option><option value="split:vs_10">vs. Spurs</option><option value="split:vs_2">vs. Stars</option><option value="split:vs_23">vs. Supersonics</option><option value="split:vs_28">vs. Thunder</option><option value="split:vs_7">vs. Tropics</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=99&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th>gs</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>ftm</th>
            <th>fta</th>
            <th>3gm</th>
            <th class="sep-r-team">3ga</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>PG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000001"><img src="./images/player/200000001.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Aaron Anchor</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">A. Anchor</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
        <tr>
            <td>SG</td>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=200000002"><img src="./images/player/200000002.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Bobby Baseline</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">B. Baseline</span></a></td>            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
        </tr>
    </tbody>
    <tfoot>
    </tfoot>
</table>
        
GOLDEN;

    private const G_TID0_TABLEOUTPUT = <<<'GOLDEN'
<table class="ibl-data-table team-table responsive-table sortable" style="--team-color-primary: #888888; --team-color-secondary: #cccccc;"><caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #888888; --team-color-secondary: #cccccc;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts">Contracts</a></div></caption>
    <thead>
        <tr>
            <th class="sticky-col">Player</th>
            <th>Pos</th>
            <th class="sep-r-team">Age</th>
            <th>2ga</th>
            <th>2g%</th>
            <th>fta</th>
            <th>ft%</th>
            <th>3ga</th>
            <th class="sep-r-team">3g%</th>
            <th>orb</th>
            <th>drb</th>
            <th>ast</th>
            <th>stl</th>
            <th>tvr</th>
            <th>blk</th>
            <th class="sep-r-team">foul</th>
            <th>oo</th>
            <th>do</th>
            <th>po</th>
            <th>to</th>
            <th>od</th>
            <th>dd</th>
            <th>pd</th>
            <th class="sep-r-team">td</th>
            <th>Clu</th>
            <th class="sep-r-team">Con</th>
            <th>Days Injured</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="sticky-col ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=2"><img src="./images/player/2.jpg" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy"><span class="ibl-player-cell__name ibl-player-cell__name--full">Test Player Two</span><span class="ibl-player-cell__name ibl-player-cell__name--abbrev">T. Player Two</span></a></td>            <td>SF</td>
            <td class="sep-r-team">22</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-weak">0</td>
            <td>0</td>
            <td>0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
            <td>0</td>
            <td class="sep-r-team">0</td>
                        <td>0</td>
        </tr>
    </tbody>
</table>
        
GOLDEN;

    // === GENERATED GOLDENS END ===

    // -------------------------------------------------------------------------
    // Phase 0 — scaffold + contract smoke / not-found boundary
    // -------------------------------------------------------------------------

    public function testGetTeamPageDataReturnsExpectedTopLevelKeys(): void
    {
        $this->seedCharTeam();

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);

        self::assertSame([
            'teamid',
            'team',
            'imagesPath',
            'yr',
            'display',
            'insertyear',
            'isActualTeam',
            'tableOutput',
            'draftPicksTable',
            'currentSeasonCard',
            'awardsCard',
            'franchiseHistoryCard',
            'rafters',
            'userTeamName',
            'isOwnTeam',
            'extensionResult',
            'extensionMsg',
        ], array_keys($result));
    }

    public function testGetTeamPageDataThrowsOnUnknownTeam(): void
    {
        // teamid 12345 is not present in ibl_team_info → Team::initialize()'s
        // load() throws "Team not found", pinning the not-found contract.
        $this->expectException(\RuntimeException::class);
        $this->service->getTeamPageData(12345, null, 'ratings', '', null);
    }

    // -------------------------------------------------------------------------
    // Phase 1 — baseline full-array golden + teamid=0 boundary
    // -------------------------------------------------------------------------

    public function testBaselineCurrentSeasonRatingsFullArray(): void
    {
        $this->seedCharTeam();
        // Minimal fixed roster so the ratings table renders without bloating.
        $this->insertTestPlayer(200000001, 'Aaron Anchor', ['teamid' => self::TEAM_ID, 'pos' => 'PG', 'ordinal' => 1]);
        $this->insertTestPlayer(200000002, 'Bobby Baseline', ['teamid' => self::TEAM_ID, 'pos' => 'SG', 'ordinal' => 2]);
        // One owned draft pick (the pick belongs to a real team → resolvable).
        $this->insertDraftPickRow(self::TEAM_ID, 1, 2027, 1);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);

        // Non-object scalars.
        self::assertSame(self::TEAM_ID, $result['teamid']);
        self::assertSame('images/', $result['imagesPath']);
        self::assertNull($result['yr']);
        self::assertSame('ratings', $result['display']);
        self::assertSame('', $result['insertyear']);
        self::assertTrue($result['isActualTeam']);
        self::assertSame('', $result['userTeamName']);
        self::assertFalse($result['isOwnTeam']);
        self::assertNull($result['extensionResult']);
        self::assertNull($result['extensionMsg']);

        // The `team` key is a Team object — assert scalar public props individually.
        $team = $result['team'];
        self::assertInstanceOf(\Team\Team::class, $team);
        self::assertSame(self::TEAM_ID, $team->teamid);
        self::assertSame(self::TEAM_NAME, $team->name);
        self::assertSame(self::TEAM_CITY, $team->city);
        self::assertSame('102030', $team->color1);
        self::assertSame('A0B0C0', $team->color2);
        self::assertSame('Test Arena', $team->arena);
        self::assertSame(18000, $team->capacity);
        self::assertSame('Test Owner', $team->ownerName);
        self::assertSame('12-8', $team->seasonRecord);

        // The six rendered-HTML blobs, frozen verbatim.
        self::assertGolden('baseline_tableoutput', $result['tableOutput']);
        self::assertGolden('baseline_draftpickstable', $result['draftPicksTable']);
        self::assertGolden('baseline_currentseasoncard', $result['currentSeasonCard']);
        self::assertGolden('baseline_awardscard', $result['awardsCard']);
        self::assertGolden('baseline_franchisehistorycard', $result['franchiseHistoryCard']);
        self::assertGolden('baseline_rafters', $result['rafters']);
    }

    public function testTeamidZeroIsNotActualTeamAndSidebarsEmpty(): void
    {
        // teamid 0 = Free Agents (present in seed). isActualTeam=false short-
        // circuits all sidebar/draft rendering; only tableOutput renders.
        $result = $this->service->getTeamPageData(0, null, 'ratings', '', null);

        self::assertFalse($result['isActualTeam']);
        self::assertSame('', $result['draftPicksTable']);
        self::assertSame('', $result['currentSeasonCard']);
        self::assertSame('', $result['awardsCard']);
        self::assertSame('', $result['franchiseHistoryCard']);
        self::assertSame('', $result['rafters']);

        // tableOutput for the free-agents path is its own frozen golden.
        self::assertGolden('tid0_tableoutput', $result['tableOutput']);
    }

    private function seedCanonicalRoster(): void
    {
        $this->insertTestPlayer(200000001, 'Aaron Anchor', ['teamid' => self::TEAM_ID, 'pos' => 'PG', 'ordinal' => 1]);
        $this->insertTestPlayer(200000002, 'Bobby Baseline', ['teamid' => self::TEAM_ID, 'pos' => 'SG', 'ordinal' => 2]);
    }

    /**
     * @param array<string, int|float|string|null> $extra
     */
    private function seedBanner(int $year, int $bannertype, ?string $bannername = null): void
    {
        $this->insertRow('ibl_banners', [
            'year' => $year,
            'currentname' => self::TEAM_NAME,
            'bannername' => $bannername ?? self::TEAM_NAME,
            'bannertype' => $bannertype,
        ]);
    }

    private function rafters(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['rafters'];
    }

    private function franchiseCard(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['franchiseHistoryCard'];
    }

    private function currentSeasonCard(): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null)['currentSeasonCard'];
    }

    // -------------------------------------------------------------------------
    // Phase 2 — banner-type classification (scope: rafters)
    // -------------------------------------------------------------------------

    public function testRaftersAllThreeBannerCategories(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 1); // champ
        $this->seedBanner(2022, 2); // conference (Eastern)
        $this->seedBanner(2023, 4); // division (Atlantic)
        self::assertGolden('rafters_all_three', $this->rafters());
    }

    public function testRaftersConferenceType2VsType3Labels(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 2); // Eastern Conf. Champions
        $this->seedBanner(2022, 3); // Western Conf. Champions
        self::assertGolden('rafters_conf_2v3', $this->rafters());
    }

    public function testRaftersDivisionMatchAllArms(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 4); // Atlantic
        $this->seedBanner(2022, 5); // Central
        $this->seedBanner(2023, 6); // Midwest
        $this->seedBanner(2024, 7); // Pacific (match default arm)
        self::assertGolden('rafters_div_arms', $this->rafters());
    }

    public function testRaftersBannerNameDiffersFromTeamShowsAsClause(): void
    {
        $this->seedCharTeam();
        // bannername differs from the current team name → "(as ...)" branch.
        $this->seedBanner(2021, 1, 'Old CharTest');
        self::assertGolden('rafters_as_clause', $this->rafters());
    }

    public function testRaftersFivePerRowGrouping(): void
    {
        $this->seedCharTeam();
        // 6 same-type banners → the 6th wraps into a new banners row.
        for ($y = 2019; $y <= 2024; $y++) {
            $this->seedBanner($y, 1);
        }
        self::assertGolden('rafters_five_per_row', $this->rafters());
    }

    public function testRaftersEmptyWhenNoBanners(): void
    {
        $this->seedCharTeam();
        self::assertSame('', $this->rafters());
    }

    public function testRaftersIgnoresUnknownBannerType(): void
    {
        $this->seedCharTeam();
        $this->seedBanner(2021, 1); // valid champ
        $this->seedBanner(2022, 8); // out of range — dropped
        $this->seedBanner(2023, 0); // out of range — dropped
        self::assertGolden('rafters_unknown_dropped', $this->rafters());
    }

    // -------------------------------------------------------------------------
    // Phase 3 — playoff round aggregates (scope: franchiseHistoryCard)
    // Years far in the future (no ambient franchise_seasons) so opponent/team
    // names fall back to the literal series winner/loser.
    // -------------------------------------------------------------------------

    public function testFranchiseCardPlayoffWinAccumulatesGameAndSeriesWins(): void
    {
        $this->seedCharTeam();
        // CharTest wins a first-round series 4-2.
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        self::assertGolden('franchise_playoff_win', $this->franchiseCard());
    }

    public function testFranchiseCardPlayoffLossOrientation(): void
    {
        $this->seedCharTeam();
        // CharTest loses a series 1-4 (opponent is the series winner).
        $this->insertPlayoffSeriesResultRow(2099, 1, 1, self::TEAM_ID, 'Rivals', self::TEAM_NAME, 4, 1);
        self::assertGolden('franchise_playoff_loss', $this->franchiseCard());
    }

    public function testFranchiseCardPlayoffMultipleRoundsAggregate(): void
    {
        $this->seedCharTeam();
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 1);
        $this->insertPlayoffSeriesResultRow(2099, 2, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        $this->insertPlayoffSeriesResultRow(2099, 3, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 3);
        $this->insertPlayoffSeriesResultRow(2099, 4, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 0);
        self::assertGolden('franchise_playoff_multiround', $this->franchiseCard());
    }

    public function testFranchiseCardNoPlayoffHistoryStillEmitsZeroFooter(): void
    {
        $this->seedCharTeam();
        self::assertGolden('franchise_no_playoff', $this->franchiseCard());
    }

    public function testFranchiseCardIgnoresOutOfRangeRound(): void
    {
        $this->seedCharTeam();
        $this->insertPlayoffSeriesResultRow(2099, 1, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 2);
        $this->insertPlayoffSeriesResultRow(2099, 5, self::TEAM_ID, 1, self::TEAM_NAME, 'Rivals', 4, 0); // round 5 dropped
        self::assertGolden('franchise_round5_dropped', $this->franchiseCard());
    }

    // -------------------------------------------------------------------------
    // Phase 4 — regular-season & HEAT win/loss history (scope: franchiseHistoryCard)
    // -------------------------------------------------------------------------

    public function testFranchiseCardRegularSeasonHistoryLabelsAndTotals(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 55, 27);
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 40, 42);
        self::assertGolden('franchise_regular_history', $this->franchiseCard());
    }

    public function testFranchiseCardHeatHistoryLabelFormat(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 3, self::TEAM_NAME, self::TEAM_NAME, 6, 2);
        self::assertGolden('franchise_heat_history', $this->franchiseCard());
    }

    public function testFranchiseCardBestRecordBolding(): void
    {
        $this->seedCharTeam();
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 60, 22); // strictly higher pct
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 41, 41);
        self::assertGolden('franchise_best_bold', $this->franchiseCard());
    }

    public function testFranchiseCardBestRecordPctTieBreaksOnWins(): void
    {
        $this->seedCharTeam();
        // Equal pct (.500) but different absolute wins → higher-wins row bolded.
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2024, 1, self::TEAM_NAME, self::TEAM_NAME, 50, 50);
        $this->insertTeamSeasonRecordRow(self::TEAM_ID, 2023, 1, self::TEAM_NAME, self::TEAM_NAME, 41, 41);
        self::assertGolden('franchise_best_tiebreak', $this->franchiseCard());
    }

    public function testFranchiseCardEmptyHistoryShowsZeroTotals(): void
    {
        $this->seedCharTeam();
        self::assertGolden('franchise_empty_history', $this->franchiseCard());
    }

    // -------------------------------------------------------------------------
    // Phase 5 — current-season card incl. null-power and FKA (scope: currentSeasonCard)
    // -------------------------------------------------------------------------

    public function testCurrentSeasonCardPopulated(): void
    {
        $this->seedCharTeam();
        self::assertGolden('current_populated', $this->currentSeasonCard());
    }

    public function testCurrentSeasonCardFormerlyKnownAs(): void
    {
        $this->seedCharTeam();
        // Prior era (different city/name) then the current era (matches current
        // city/name → skipped by buildFormerlyKnownAs).
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2020,
            'season_ending_year' => 2021,
            'team_city' => 'Old City',
            'team_name' => 'OldName',
        ]);
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2025,
            'season_ending_year' => 2026,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
        ]);
        self::assertGolden('current_fka', $this->currentSeasonCard());
    }

    public function testCurrentSeasonCardEmptyWhenNoPowerRow(): void
    {
        // team_info only — no standings/power → prepareCurrentSeasonData()
        // returns null, but renderCurrentSeasonCard('') still wraps empty chrome.
        $this->insertRow('ibl_team_info', [
            'teamid' => self::TEAM_ID,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
            'color1' => '102030',
            'color2' => 'A0B0C0',
            'arena' => 'Test Arena',
            'capacity' => 18000,
            'owner_name' => 'Test Owner',
            'owner_email' => 'owner@test.local',
            'gm_username' => 'char_gm',
        ]);
        self::assertGolden('current_no_power', $this->currentSeasonCard());
    }

    public function testFormerlyKnownAsNullWhenOnlyCurrentEra(): void
    {
        $this->seedCharTeam();
        $this->insertRow('ibl_franchise_seasons', [
            'franchise_id' => self::TEAM_ID,
            'season_year' => 2025,
            'season_ending_year' => 2026,
            'team_city' => self::TEAM_CITY,
            'team_name' => self::TEAM_NAME,
        ]);
        self::assertGolden('current_no_fka', $this->currentSeasonCard());
    }

    // -------------------------------------------------------------------------
    // Phase 6 — $display / $split variants (scope: tableOutput) + $yr + isOwnTeam
    // -------------------------------------------------------------------------

    private function tableOutputFor(?string $yr, string $display, ?string $split = null): string
    {
        return $this->service->getTeamPageData(self::TEAM_ID, $yr, $display, '', $split)['tableOutput'];
    }

    public function testTableOutputRatings(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_ratings', $this->tableOutputFor(null, 'ratings'));
    }

    public function testTableOutputSeasonTotals(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_total_s', $this->tableOutputFor(null, 'total_s'));
    }

    public function testTableOutputSeasonAverages(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_avg_s', $this->tableOutputFor(null, 'avg_s'));
    }

    public function testTableOutputPer36(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_per36', $this->tableOutputFor(null, 'per36mins'));
    }

    public function testTableOutputChunk(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_chunk', $this->tableOutputFor(null, 'chunk'));
    }

    public function testTableOutputContracts(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_contracts', $this->tableOutputFor(null, 'contracts'));
    }

    public function testTableOutputSplitHome(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_split_home', $this->tableOutputFor(null, 'split', 'home'));
    }

    public function testTableOutputSplitWins(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        self::assertGolden('table_split_wins', $this->tableOutputFor(null, 'split', 'wins'));
    }

    public function testHistoricalYearSetsInsertyearAndHistoricalRoster(): void
    {
        $this->seedCharTeam();
        $this->insertHistRow(200000001, 'Aaron Anchor', 2024, ['teamid' => self::TEAM_ID, 'team' => self::TEAM_NAME]);

        $result = $this->service->getTeamPageData(self::TEAM_ID, '2024', 'ratings', '', null);

        self::assertSame('&yr=2024', $result['insertyear']);
        self::assertSame('2024', $result['yr']);
        self::assertGolden('table_historical', $result['tableOutput']);
    }

    public function testIsOwnTeamTrueWhenUserTeamMatches(): void
    {
        $this->seedCharTeam();
        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', self::TEAM_NAME, null);
        self::assertTrue($result['isOwnTeam']);
        self::assertSame(self::TEAM_NAME, $result['userTeamName']);
    }

    public function testIsOwnTeamFalseWhenUserTeamDiffers(): void
    {
        $this->seedCharTeam();
        $other = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', 'Some Other Team', null);
        self::assertFalse($other['isOwnTeam']);

        $empty = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertFalse($empty['isOwnTeam']);
    }

    public function testTableOutputUnknownDisplayFallsBackToRatings(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        // The switch default renders the Ratings *table body* for an unknown
        // display key, but the view-dropdown reflects the literal requested
        // value (so no <option> is marked selected) — the full blob therefore
        // differs from the ratings golden. Freeze the real fallback output.
        self::assertGolden('table_bogus', $this->tableOutputFor(null, 'bogus'));
    }

    public function testTableOutputSplitDefaultsToHomeWhenSplitNull(): void
    {
        $this->seedCharTeam();
        $this->seedCanonicalRoster();
        // renderSplitStats uses $split ?? 'home' for the table body, but the
        // dropdown active value is 'split:' (empty) so no option is selected —
        // the full blob differs from split=home. Freeze the real output.
        self::assertGolden('table_split_null', $this->tableOutputFor(null, 'split', null));
    }

    // -------------------------------------------------------------------------
    // Phase 7 — awards card + draft picks (scope: awardsCard, draftPicksTable)
    // -------------------------------------------------------------------------

    public function testAwardsCardWithGmTenureAndTeamAward(): void
    {
        $this->seedCharTeam();
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => self::TEAM_ID,
            'gm_display_name' => 'Test Owner',
            'start_season_year' => 2020,
            'end_season_year' => 2026,
        ]);
        $this->insertTeamAwardRow(self::TEAM_NAME, 'Atlantic Division Champions', 2024);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('awards_gm_and_team', $result['awardsCard']);
    }

    public function testAwardsCardEmptyAccomplishments(): void
    {
        // GM tenure but zero team awards and zero playoff rows → the
        // accomplishments section renders its empty state (not blank/fatal).
        $this->seedCharTeam();
        $this->insertRow('ibl_gm_tenures', [
            'franchise_id' => self::TEAM_ID,
            'gm_display_name' => 'Test Owner',
            'start_season_year' => 2020,
            'end_season_year' => 2026,
        ]);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('awards_empty_accomplishments', $result['awardsCard']);
    }

    public function testDraftPicksTablePopulated(): void
    {
        $this->seedCharTeam();
        $this->insertDraftPickRow(self::TEAM_ID, 1, 2027, 1);

        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('draft_populated', $result['draftPicksTable']);
    }

    public function testDraftPicksTableEmptyContainerWhenNoPicks(): void
    {
        $this->seedCharTeam();
        $result = $this->service->getTeamPageData(self::TEAM_ID, null, 'ratings', '', null);
        self::assertGolden('draft_empty', $result['draftPicksTable']);
    }
}
