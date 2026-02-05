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
                    'boxScoreUrl' => 'modules.php?name=Scores&pa=boxscore&boxid=1731',
                    'dateDisplay' => 'January 16, 1996',
                    'oppAbbr' => 'van',
                    'oppTid' => 20,
                    'oppYr' => '1996',
                    'amount' => '80',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('boxid=1731', $html);
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

    public function testRenderTeamGameRecordIncludesTeamLogo(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamGameRecords'] = [
            'Most Points in a Single Game' => [
                [
                    'teamAbbr' => 'uta',
                    'teamTid' => 13,
                    'boxScoreUrl' => '',
                    'dateDisplay' => 'November 30, 1999',
                    'oppAbbr' => 'gsw',
                    'oppTid' => 24,
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
                    'season' => '1992-93',
                    'amount' => '71-11',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('71-11', $html);
        $this->assertStringContainsString('1992-93', $html);
    }

    public function testRenderFranchiseRecord(): void
    {
        $records = $this->createMinimalRecords();
        $records['teamFranchise'] = [
            'Most Playoff Appearances' => [
                [
                    'teamAbbr' => 'bkn',
                    'amount' => '7',
                    'years' => '1989, 1990, 1991',
                ],
            ],
        ];

        $html = $this->view->render($records);

        $this->assertStringContainsString('Most Playoff Appearances', $html);
        $this->assertStringContainsString('images/topics/bkn.png', $html);
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

    public function testRenderIncludesStyleBlock(): void
    {
        $records = $this->createMinimalRecords();

        $html = $this->view->render($records);

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.record-holders-page', $html);
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
}
