<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use FreeAgency\FreeAgencyFormComponents;
use FreeAgency\FreeAgencyOfferView;
use PHPUnit\Framework\TestCase;
use Player\Player;
use Team\Team;

class FreeAgencyViewXssTest extends TestCase
{
    public function testNegotiationViewEscapesErrorMessage(): void
    {
        $xssPayload = '<script>alert("xss")</script>';

        $player = $this->createStub(Player::class);
        $player->position = 'PG';
        $player->name = 'Test Player';
        $player->playerID = 1;
        $player->teamName = 'TestTeam';
        $player->birdYears = 0;
        $player->yearsOfExperience = 5;

        $team = $this->createStub(Team::class);
        $team->name = 'TestTeam';
        $team->teamid = 1;

        $formComponents = new FreeAgencyFormComponents('TestTeam', $player);

        $view = new FreeAgencyOfferView($formComponents);

        $negotiationData = [
            'player' => $player,
            'capMetrics' => [
                'totalSalaries' => [0, 0, 0, 0, 0, 0],
                'softCapSpace' => [0, 0, 0, 0, 0, 0],
                'hardCapSpace' => [0, 0, 0, 0, 0, 0],
                'rosterSpots' => [0, 0, 0, 0, 0, 0],
            ],
            'demands' => ['dem1' => 100, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            'existingOffer' => ['offer1' => 0, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
            'amendedCapSpace' => 500,
            'hasExistingOffer' => false,
            'veteranMinimum' => 70,
            'maxContract' => 1063,
            'team' => $team,
        ];

        $html = $view->render($negotiationData, $xssPayload);

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNegotiationViewShowsNoSpotsWarningWithXssTeam(): void
    {
        $player = $this->createStub(Player::class);
        $player->position = 'SG';
        $player->name = '<img src=x onerror=alert(1)>';
        $player->playerID = 2;
        $player->teamName = 'TestTeam';
        $player->birdYears = 0;
        $player->yearsOfExperience = 3;

        $team = $this->createStub(Team::class);
        $team->name = 'TestTeam';
        $team->teamid = 1;

        $formComponents = new FreeAgencyFormComponents('TestTeam', $player);
        $view = new FreeAgencyOfferView($formComponents);

        $negotiationData = [
            'player' => $player,
            'capMetrics' => [
                'totalSalaries' => [0, 0, 0, 0, 0, 0],
                'softCapSpace' => [0, 0, 0, 0, 0, 0],
                'hardCapSpace' => [0, 0, 0, 0, 0, 0],
                'rosterSpots' => [0, 0, 0, 0, 0, 0],
            ],
            'demands' => ['dem1' => 0, 'dem2' => 0, 'dem3' => 0, 'dem4' => 0, 'dem5' => 0, 'dem6' => 0],
            'existingOffer' => ['offer1' => 0, 'offer2' => 0, 'offer3' => 0, 'offer4' => 0, 'offer5' => 0, 'offer6' => 0],
            'amendedCapSpace' => 0,
            'hasExistingOffer' => false,
            'veteranMinimum' => 61,
            'maxContract' => 1063,
            'team' => $team,
        ];

        $html = $view->render($negotiationData);

        $this->assertStringNotContainsString('onerror=', $html);
    }
}
