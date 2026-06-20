<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordHoldersView;

final class RecordHoldersViewTest extends TestCase
{
    private RecordHoldersView $view;

    protected function setUp(): void
    {
        $this->view = new RecordHoldersView();
    }

    public function testRenderReturnsHtmlWithTitle(): void
    {
        $records = $this->createMinimalRecords();

        $html = $this->view->render($records);

        $this->assertStringContainsString('Record Holders', $html);
        $this->assertStringContainsString('ibl-title', $html);
    }

    public function testRenderContainsAllSectionHeaders(): void
    {
        $records = $this->createMinimalRecords();

        $html = $this->view->render($records);

        $this->assertStringContainsString('Regular Season (Single Game)', $html);
        $this->assertStringContainsString('Full Season', $html);
        $this->assertStringContainsString('Playoffs', $html);
        $this->assertStringContainsString('H.E.A.T.', $html);
        $this->assertStringContainsString('Team Records', $html);
    }

    public function testRenderPlayerRecordIncludesPlayerLink(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                [
                    'pid' => 927,
                    'name' => 'Bob Pettit',
                    'teamAbbr' => 'min',
                    'teamTid' => 14,
                    'teamYr' => '1996',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'January 16, 1996',
                    'oppAbbr' => 'van',
                    'oppTid' => 20,
                    'oppYr' => '1996',
                    'amount' => '80',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('pid=927', $html);
        $this->assertStringContainsString('Bob Pettit', $html);
        $this->assertStringContainsString('images/player/927.jpg', $html);
    }

    public function testRenderPlayerRecordIncludesTeamLogo(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                [
                    'pid' => 927,
                    'name' => 'Bob Pettit',
                    'teamAbbr' => 'min',
                    'teamTid' => 14,
                    'teamYr' => '1996',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'January 16, 1996',
                    'oppAbbr' => 'van',
                    'oppTid' => 20,
                    'oppYr' => '1996',
                    'amount' => '80',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('images/topics/min.png', $html);
        $this->assertStringContainsString('images/topics/van.png', $html);
    }

    public function testRenderBoxScoreLinkWhenUrlAvailable(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                [
                    'pid' => 927,
                    'name' => 'Bob Pettit',
                    'teamAbbr' => 'min',
                    'teamTid' => 14,
                    'teamYr' => '1996',
                    'boxScoreUrl' => 'https://ibl6.iblhoops.net/1996-01-16-game-3/boxscore',
                    'dateDisplay' => 'January 16, 1996',
                    'oppAbbr' => 'van',
                    'oppTid' => 20,
                    'oppYr' => '1996',
                    'amount' => '80',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('1996-01-16-game-3/boxscore', $html);
    }

    public function testRenderDateWithoutLinkWhenNoBoxScoreUrl(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                [
                    'pid' => 927,
                    'name' => 'Bob Pettit',
                    'teamAbbr' => 'min',
                    'teamTid' => 14,
                    'teamYr' => '1996',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'January 16, 1996',
                    'oppAbbr' => 'van',
                    'oppTid' => 20,
                    'oppYr' => '1996',
                    'amount' => '80',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('January 16, 1996', $html);
    }

    public function testRenderQuadrupleDoubleMultiLineAmount(): void
    {
        $records = $this->createMinimalRecords();
        $records['quadrupleDoubles'] = [
            [
                'pid' => 1481,
                'name' => 'Lenny Wilkens',
                'teamAbbr' => 'mia',
                'teamTid' => 2,
                'teamYr' => '1993',
                'boxScoreUrl' => '',
                'dateDisplay' => 'December 12, 1992',
                'oppAbbr' => 'det',
                'oppTid' => 25,
                'oppYr' => '1993',
                'amount' => "12pts\n10rbs\n14ast\n10stl",
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('12pts', $html);
        $this->assertStringContainsString('<br>', $html);
    }

    public function testRenderAllStarAppearances(): void
    {
        $records = $this->createMinimalRecords();
        $records['allStarRecord'] = [
            'name' => 'Mitch Richmond',
            'pid' => 304,
            'teams' => 'cha,mia',
            'teamTids' => '10,2',
            'amount' => 10,
            'years' => '1989, 1990, 1991',
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('Mitch Richmond', $html);
        $this->assertStringContainsString('All-Star Appearances', $html);
        $this->assertStringContainsString('pid=304', $html);
    }

    public function testRenderSeasonRecordIncludesSeasonRange(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerFullSeason'] = [
            'Highest Scoring Average in a Regular Season' => [
                [
                    'pid' => 304,
                    'name' => 'Mitch Richmond',
                    'teamAbbr' => 'mia',
                    'teamTid' => 2,
                    'teamYr' => '1994',
                    'season' => '1993-94',
                    'amount' => '34.2',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('1993-94', $html);
        $this->assertStringContainsString('34.2', $html);
    }

    public function testRenderPlayerFullSeasonLinksSeasonToTeamPage(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerFullSeason'] = [
            'Highest Scoring Average in a Regular Season' => [
                [
                    'pid' => 304,
                    'name' => 'Mitch Richmond',
                    'teamAbbr' => 'mia',
                    'teamTid' => 2,
                    'teamYr' => '1994',
                    'season' => '1993-94',
                    'amount' => '34.2',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamid=2&amp;yr=1994', $html);
        $this->assertStringContainsString('>1993-94</a>', $html);
    }

    public function testRenderTeamGameRecordIncludesTeamLogo(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamGameRecords'] = [
            'Most Points in a Single Game' => [
                [
                    'teamAbbr' => 'uta',
                    'teamTid' => 13,
                    'teamYr' => '2000',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'November 30, 1999',
                    'oppAbbr' => 'gsw',
                    'oppTid' => 24,
                    'oppYr' => '2000',
                    'amount' => '180',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('images/topics/uta.png', $html);
        $this->assertStringContainsString('180', $html);
    }

    public function testRenderTeamSeasonRecord(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamSeasonRecords'] = [
            'Best Season Record' => [
                [
                    'teamAbbr' => 'chi',
                    'teamTid' => 7,
                    'teamYr' => '1993',
                    'season' => '1992-93',
                    'amount' => '71-11',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('71-11', $html);
        $this->assertStringContainsString('1992-93', $html);
    }

    public function testRenderTeamSeasonRecordLinksSeasonToTeamPage(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamSeasonRecords'] = [
            'Best Season Record' => [
                [
                    'teamAbbr' => 'chi',
                    'teamTid' => 7,
                    'teamYr' => '1993',
                    'season' => '1992-93',
                    'amount' => '71-11',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamid=7&amp;yr=1993', $html);
        $this->assertStringContainsString('>1992-93</a>', $html);
    }

    public function testRenderFranchiseRecord(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamFranchise'] = [
            'Most Playoff Appearances' => [
                [
                    'teamAbbr' => 'bkn',
                    'teamTid' => 4,
                    'amount' => '7',
                    'years' => '1989, 1990, 1991',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('Most Playoff Appearances', $html);
        $this->assertStringContainsString('images/topics/bkn.png', $html);
    }

    public function testRenderFranchiseRecordLinksYearsToTeamPage(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamFranchise'] = [
            'Most Playoff Appearances' => [
                [
                    'teamAbbr' => 'bkn',
                    'teamTid' => 4,
                    'amount' => '3',
                    'years' => '1989, 1990, 1991',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamid=4&amp;yr=1989', $html);
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamid=4&amp;yr=1990', $html);
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamid=4&amp;yr=1991', $html);
    }

    public function testRenderSanitizesOutput(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                [
                    'pid' => 1,
                    'name' => '<script>alert("xss")</script>',
                    'teamAbbr' => 'bos',
                    'teamTid' => 1,
                    'teamYr' => '2000',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'January 1, 2000',
                    'oppAbbr' => 'mia',
                    'oppTid' => 2,
                    'oppYr' => '2000',
                    'amount' => '50',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderUsesRecordSectionWrapper(): void
    {
        $records = $this->createMinimalRecords();

        $html = $this->view->render($records);

        $this->assertStringContainsString('record-section', $html);
        $this->assertStringNotContainsString('<style>', $html);
    }

    public function testRenderUsesCardWrapperPerSection(): void
    {
        $records = $this->createMinimalRecords();

        $html = $this->view->render($records);

        $this->assertSame(5, substr_count($html, 'ibl-card__header'));
    }

    public function testRenderCategoryHasOwnTable(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
            'Most Rebounds in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];

        $html = $this->view->render($records);

        // Each category gets its own <table> — the two regular season categories
        // plus Quadruple Doubles and All-Star = 4 tables in section 1 alone
        $this->assertGreaterThanOrEqual(4, substr_count($html, '<table'));
    }

    public function testRenderStatValueUsesHighlightClass(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('ibl-stat-highlight', $html);
    }

    public function testRenderNoBoldStyleWrappers(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];
        $records['playerFullSeason'] = [
            'Highest Scoring Average in a Regular Season' => [
                [
                    'pid' => 304,
                    'name' => 'Mitch Richmond',
                    'teamAbbr' => 'mia',
                    'teamTid' => 2,
                    'teamYr' => '1994',
                    'season' => '1993-94',
                    'amount' => '34.2',
                ],
            ],
        ];
        $records['teamGameRecords'] = [
            'Most Points in a Single Game' => [
                [
                    'teamAbbr' => 'uta',
                    'teamTid' => 13,
                    'teamYr' => '2000',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'November 30, 1999',
                    'oppAbbr' => 'gsw',
                    'oppTid' => 24,
                    'oppYr' => '2000',
                    'amount' => '180',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringNotContainsString('<strong style="font-weight: bold;">', $html);
    }

    public function testRenderColumnHeadersAreThElements(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('<th>Player</th>', $html);
        $this->assertStringContainsString('<th>Team</th>', $html);
        $this->assertStringContainsString('<th>Date</th>', $html);
        $this->assertStringContainsString('<th>Opponent</th>', $html);
    }

    public function testRenderStatSpecificColumnLabel(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
            'Most Rebounds in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('<th>Pts</th>', $html);
        $this->assertStringContainsString('<th>Reb</th>', $html);
    }

    public function testRenderCategoryHeadingsAreH3Elements(): void
    {
        $records = $this->createMinimalRecords();
        $records['playerSingleGame']['regularSeason'] = [
            'Most Points in a Single Game' => [
                $this->createPlayerRecord(),
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('<h3 class="record-category__title">', $html);
        $this->assertStringContainsString('Most Points in a Single Game</h3>', $html);
    }

    public function testRenderTeamRecordsHasSubsectionHeadings(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamGameRecords'] = [
            'Most Points in a Single Game' => [
                [
                    'teamAbbr' => 'uta',
                    'teamTid' => 13,
                    'teamYr' => '2000',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'November 30, 1999',
                    'oppAbbr' => 'gsw',
                    'oppTid' => 24,
                    'oppYr' => '2000',
                    'amount' => '180',
                ],
            ],
        ];
        $records['teamSeasonRecords'] = [
            'Best Season Record' => [
                [
                    'teamAbbr' => 'chi',
                    'teamTid' => 7,
                    'teamYr' => '1993',
                    'season' => '1992-93',
                    'amount' => '71-11',
                ],
            ],
        ];
        $records['teamFranchise'] = [
            'Most Playoff Appearances' => [
                [
                    'teamAbbr' => 'bkn',
                    'teamTid' => 4,
                    'amount' => '7',
                    'years' => '1989, 1990, 1991',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('Game Records</h3>', $html);
        $this->assertStringContainsString('Season Records</h3>', $html);
        $this->assertStringContainsString('Franchise Records</h3>', $html);
    }

    /**
     * Create a minimal valid records structure for testing.
     *
     * @return array{
     *     playerSingleGame: array{regularSeason: array<string, list<mixed>>, playoffs: array<string, list<mixed>>, heat: array<string, list<mixed>>},
     *     quadrupleDoubles: list<mixed>,
     *     allStarRecord: array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string},
     *     playerFullSeason: array<string, list<mixed>>,
     *     teamGameRecords: array<string, list<mixed>>,
     *     teamSeasonRecords: array<string, list<mixed>>,
     *     teamFranchise: array<string, list<mixed>>
     * }
     */
    private function createMinimalRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => [],
                'playoffs' => [],
                'heat' => [],
            ],
            'quadrupleDoubles' => [],
            'allStarRecord' => ['name' => '', 'pid' => null, 'teams' => '', 'teamTids' => '', 'amount' => 0, 'years' => ''],
            'playerFullSeason' => [],
            'teamGameRecords' => [],
            'teamSeasonRecords' => [],
            'teamFranchise' => [],
        ];
    }

    /**
     * Characterization: the full rendered page (all five category-block
     * renderers + all-star) must stay byte-identical across the
     * renderCategoryTable extraction. Golden captured from pre-extraction
     * behavior; a single shared helper must reproduce it exactly.
     */
    public function testRenderGoldenOutputUnchanged(): void
    {
        $html = $this->view->render($this->createComprehensiveRecords());
        $goldenPath = __DIR__ . '/fixtures/recordholders-golden.html';

        $this->assertStringEqualsFile($goldenPath, $html);
    }

    /**
     * Build a records fixture exercising every category-block renderer:
     * player single-game (regular/playoffs/heat), full-season, quadruple
     * doubles, all-star, team game, team season, and franchise.
     *
     * @return array{
     *     playerSingleGame: array{regularSeason: array<string, list<mixed>>, playoffs: array<string, list<mixed>>, heat: array<string, list<mixed>>},
     *     quadrupleDoubles: list<mixed>,
     *     allStarRecord: array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string},
     *     playerFullSeason: array<string, list<mixed>>,
     *     teamGameRecords: array<string, list<mixed>>,
     *     teamSeasonRecords: array<string, list<mixed>>,
     *     teamFranchise: array<string, list<mixed>>
     * }
     */
    private function createComprehensiveRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => [
                    'Most Points in a Single Game' => [$this->createPlayerRecord()],
                ],
                'playoffs' => [
                    'Most Rebounds in a Single Game' => [[
                        'pid' => 304,
                        'name' => 'Mitch Richmond',
                        'teamAbbr' => 'mia',
                        'teamTid' => 2,
                        'teamYr' => '1994',
                        'boxScoreUrl' => '1994-05-01-game-7/boxscore',
                        'dateDisplay' => 'May 1, 1994',
                        'oppAbbr' => 'nyk',
                        'oppTid' => 18,
                        'oppYr' => '1994',
                        'amount' => '30',
                    ]],
                ],
                'heat' => [
                    'Most Assists in a Single Game' => [[
                        'pid' => 511,
                        'name' => 'John Stockton',
                        'teamAbbr' => 'uta',
                        'teamTid' => 13,
                        'teamYr' => '1998',
                        'boxScoreUrl' => '',
                        'dateDisplay' => 'February 14, 1998',
                        'oppAbbr' => 'lal',
                        'oppTid' => 12,
                        'oppYr' => '1998',
                        'amount' => '25',
                    ]],
                ],
            ],
            'quadrupleDoubles' => [[
                'pid' => 33,
                'name' => 'Hakeem Olajuwon',
                'teamAbbr' => 'hou',
                'teamTid' => 9,
                'teamYr' => '1990',
                'boxScoreUrl' => '',
                'dateDisplay' => 'March 29, 1990',
                'oppAbbr' => 'mil',
                'oppTid' => 15,
                'oppYr' => '1990',
                'amount' => '18pts/16reb/10ast/11blk',
            ]],
            'allStarRecord' => [
                'name' => 'Mitch Richmond',
                'pid' => 304,
                'teams' => 'sac,gsw',
                'teamTids' => '21,24',
                'amount' => 6,
                'years' => '1993, 1994, 1995',
            ],
            'playerFullSeason' => [
                'Highest Scoring Average in a Regular Season' => [[
                    'pid' => 304,
                    'name' => 'Mitch Richmond',
                    'teamAbbr' => 'mia',
                    'teamTid' => 2,
                    'teamYr' => '1994',
                    'season' => '1993-94',
                    'amount' => '34.2',
                ]],
            ],
            'teamGameRecords' => [
                'Most Points in a Single Game' => [[
                    'teamAbbr' => 'uta',
                    'teamTid' => 13,
                    'teamYr' => '2000',
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'November 30, 1999',
                    'oppAbbr' => 'gsw',
                    'oppTid' => 24,
                    'oppYr' => '2000',
                    'amount' => '180',
                ]],
            ],
            'teamSeasonRecords' => [
                'Best Season Record' => [[
                    'teamAbbr' => 'chi',
                    'teamTid' => 7,
                    'teamYr' => '1993',
                    'season' => '1992-93',
                    'amount' => '71-11',
                ]],
            ],
            'teamFranchise' => [
                'Most Playoff Appearances' => [[
                    'teamAbbr' => 'bkn',
                    'teamTid' => 4,
                    'amount' => '7',
                    'years' => '1989, 1990, 1991',
                ]],
            ],
        ];
    }

    /**
     * Create a sample player record for testing.
     *
     * @return array{pid: int, name: string, teamAbbr: string, teamTid: int, teamYr: string, boxScoreUrl: string, dateDisplay: string, oppAbbr: string, oppTid: int, oppYr: string, amount: string}
     */
    private function createPlayerRecord(): array
    {
        return [
            'pid' => 927,
            'name' => 'Bob Pettit',
            'teamAbbr' => 'min',
            'teamTid' => 14,
            'teamYr' => '1996',
            'boxScoreUrl' => '',
            'dateDisplay' => 'January 16, 1996',
            'oppAbbr' => 'van',
            'oppTid' => 20,
            'oppYr' => '1996',
            'amount' => '80',
        ];
    }
}
