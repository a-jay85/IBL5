<?php

declare(strict_types=1);

namespace Tests\TradeBlock;

use PHPUnit\Framework\TestCase;
use Team\Team;
use Tests\WideUnit\Mocks\MockDatabase;
use TradeBlock\TradeBlockView;

class TradeBlockViewXssTest extends TestCase
{
    private TradeBlockView $view;

    protected function setUp(): void
    {
        $this->view = new TradeBlockView();
    }

    private function makeTeam(): Team
    {
        $team = new Team(new MockDatabase());
        $team->teamid = 1;
        $team->city = 'New York';
        $team->name = 'Metros';
        $team->color1 = '000000';
        $team->color2 = 'FFFFFF';

        return $team;
    }

    public function testBrowseEscapesPlayerName(): void
    {
        $xss = '<script>alert("xss")</script>';

        $html = $this->view->renderBrowse([
            'teams' => [[
                'teamid' => 3,
                'team_name' => 'Cougars',
                'team_city' => 'Carolina',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'players' => [['pid' => 23, 'name' => $xss, 'note' => '']],
                'seekingNote' => '',
            ]],
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBrowseEscapesSeekingNote(): void
    {
        $xss = '<script>alert("seeking")</script>';

        $html = $this->view->renderBrowse([
            'teams' => [[
                'teamid' => 3,
                'team_name' => 'Cougars',
                'team_city' => 'Carolina',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'players' => [['pid' => 23, 'name' => 'Safe Player', 'note' => '']],
                'seekingNote' => $xss,
            ]],
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBrowseEscapesPerPlayerNote(): void
    {
        $xss = '<img src=x onerror=alert(1)>';

        $html = $this->view->renderBrowse([
            'teams' => [[
                'teamid' => 3,
                'team_name' => 'Cougars',
                'team_city' => 'Carolina',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'players' => [['pid' => 23, 'name' => 'Safe Player', 'note' => $xss]],
                'seekingNote' => '',
            ]],
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    public function testEditEscapesPlayerName(): void
    {
        $xss = '<script>alert("xss")</script>';

        $html = $this->view->renderEditForm(
            $this->makeTeam(),
            [['pid' => 10, 'name' => $xss]],
            [],
            ''
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEditEscapesSeekingNotePrefill(): void
    {
        $xss = '<script>alert("seeking")</script>';

        $html = $this->view->renderEditForm(
            $this->makeTeam(),
            [['pid' => 10, 'name' => 'Safe Player']],
            [],
            $xss
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEditEscapesPerPlayerNotePrefill(): void
    {
        $xss = '<script>alert("note")</script>';

        $html = $this->view->renderEditForm(
            $this->makeTeam(),
            [['pid' => 10, 'name' => 'Safe Player']],
            [10 => $xss],
            ''
        );

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testEditFormHasExactlyOneCsrfToken(): void
    {
        $html = $this->view->renderEditForm(
            $this->makeTeam(),
            [['pid' => 10, 'name' => 'Safe Player']],
            [],
            ''
        );

        self::assertSame(1, substr_count($html, 'name="_csrf_token"'));
    }

    public function testBrowseContainsNoFormElements(): void
    {
        $html = $this->view->renderBrowse([
            'teams' => [[
                'teamid' => 3,
                'team_name' => 'Cougars',
                'team_city' => 'Carolina',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'players' => [['pid' => 23, 'name' => 'Safe Player', 'note' => '']],
                'seekingNote' => 'Seeking help',
            ]],
        ]);

        self::assertStringNotContainsString('<form', $html);
    }
}
