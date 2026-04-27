<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Extension\ExtensionOfferEvaluator;
use FreeAgency\FreeAgencyDemandCalculator;
use Negotiation\NegotiationDemandCalculator;

/**
 * Cross-module divergence guard for contract modifier formulas.
 *
 * Verifies that Extension, Negotiation, and FreeAgency produce identical
 * modifier components for the same inputs, ensuring no future drift.
 *
 * @covers \ContractRules
 * @covers \Extension\ExtensionOfferEvaluator
 * @covers \Negotiation\NegotiationDemandCalculator
 * @covers \FreeAgency\FreeAgencyDemandCalculator
 */
class ModifierConsistencyTest extends TestCase
{
    private const WINS = 60;
    private const LOSSES = 22;
    private const TRAD_WINS = 700;
    private const TRAD_LOSSES = 300;
    private const MONEY_COMMITTED = 800;
    private const WINNER_PREF = 5;
    private const TRADITION_PREF = 3;
    private const LOYALTY_PREF = 4;
    private const PLAYING_TIME_PREF = 5;

    public function testAllModulesProduceIdenticalWinnerModifier(): void
    {
        $expected = \ContractRules::calculateWinnerModifier(self::WINS, self::LOSSES, self::WINNER_PREF);

        $evaluator = new ExtensionOfferEvaluator();
        $extensionResult = $evaluator->calculateWinnerModifier(
            ['wins' => self::WINS, 'losses' => self::LOSSES],
            ['winner' => self::WINNER_PREF]
        );

        $this->assertEqualsWithDelta($expected, $extensionResult, 0.000001, 'Extension winner modifier diverged from ContractRules');
    }

    public function testAllModulesProduceIdenticalTraditionModifier(): void
    {
        $expected = \ContractRules::calculateTraditionModifier(self::TRAD_WINS, self::TRAD_LOSSES, self::TRADITION_PREF);

        $evaluator = new ExtensionOfferEvaluator();
        $extensionResult = $evaluator->calculateTraditionModifier(
            ['tradition_wins' => self::TRAD_WINS, 'tradition_losses' => self::TRAD_LOSSES],
            ['tradition' => self::TRADITION_PREF]
        );

        $this->assertEqualsWithDelta($expected, $extensionResult, 0.000001, 'Extension tradition modifier diverged from ContractRules');
    }

    public function testAllModulesProduceIdenticalLoyaltyModifier(): void
    {
        $expected = \ContractRules::calculateLoyaltyModifier(self::LOYALTY_PREF);

        $evaluator = new ExtensionOfferEvaluator();
        $extensionResult = $evaluator->calculateLoyaltyModifier(['loyalty' => self::LOYALTY_PREF]);

        $this->assertEqualsWithDelta($expected, $extensionResult, 0.000001, 'Extension loyalty modifier diverged from ContractRules');
    }

    public function testAllModulesProduceIdenticalPlayingTimeModifier(): void
    {
        $expected = \ContractRules::calculatePlayingTimeModifier(self::MONEY_COMMITTED, self::PLAYING_TIME_PREF);

        $evaluator = new ExtensionOfferEvaluator();
        $extensionResult = $evaluator->calculatePlayingTimeModifier(
            ['money_committed_at_position' => self::MONEY_COMMITTED],
            ['playing_time' => self::PLAYING_TIME_PREF]
        );

        $this->assertEqualsWithDelta($expected, $extensionResult, 0.000001, 'Extension playing time modifier diverged from ContractRules');
    }

    public function testNegotiationModifierMatchesContractRules(): void
    {
        $mockDb = new \MockDatabase();
        $mockDb->onQuery('MAX\(', [
            [
                'fga' => 100, 'fgp' => 100, 'fta' => 100, 'ftp' => 100,
                'tga' => 100, 'tgp' => 100, 'orb' => 100, 'drb' => 100,
                'ast' => 100, 'stl' => 100, 'r_tvr' => 100, 'blk' => 100,
                'foul' => 100, 'oo' => 100, 'od' => 100, 'r_drive_off' => 100,
                'dd' => 100, 'po' => 100, 'pd' => 100, 'r_trans_off' => 100, 'td' => 100,
            ],
        ]);

        $calculator = new NegotiationDemandCalculator($mockDb);

        $expectedWinner = \ContractRules::calculateWinnerModifier(self::WINS, self::LOSSES, self::WINNER_PREF);
        $expectedTradition = \ContractRules::calculateTraditionModifier(self::TRAD_WINS, self::TRAD_LOSSES, self::TRADITION_PREF);
        $expectedLoyalty = \ContractRules::calculateLoyaltyModifier(self::LOYALTY_PREF);
        $expectedPT = \ContractRules::calculatePlayingTimeModifier(self::MONEY_COMMITTED, self::PLAYING_TIME_PREF);
        $expectedTotal = 1.0 + $expectedWinner + $expectedTradition + $expectedLoyalty + $expectedPT;

        $player = $this->createConfiguredPlayer();

        $teamFactors = [
            'wins' => self::WINS,
            'losses' => self::LOSSES,
            'tradition_wins' => self::TRAD_WINS,
            'tradition_losses' => self::TRAD_LOSSES,
            'money_committed_at_position' => self::MONEY_COMMITTED,
        ];

        $demands = $calculator->calculateDemands($player, $teamFactors);

        $this->assertEqualsWithDelta($expectedTotal, $demands['modifier'], 0.000001, 'Negotiation combined modifier diverged from ContractRules');
    }

    private function createConfiguredPlayer(): \Player\Player
    {
        $mockDb = new \MockDatabase();
        $mockDb->setMockData([
            [
                'pid' => 1, 'ordinal' => 1, 'name' => 'Consistency Test Player',
                'nickname' => 'CTP', 'age' => 27, 'teamid' => 1,
                'teamname' => 'Test Team', 'pos' => 'SF',
                'r_fga' => 50, 'r_fgp' => 50, 'r_fta' => 50, 'r_ftp' => 50,
                'r_3ga' => 50, 'r_3gp' => 50, 'r_orb' => 50, 'r_drb' => 50,
                'r_ast' => 50, 'r_stl' => 50, 'r_tvr' => 50, 'r_blk' => 50,
                'r_foul' => 50, 'oo' => 50, 'od' => 50, 'r_drive_off' => 50,
                'dd' => 50, 'po' => 50, 'pd' => 50, 'r_trans_off' => 50, 'td' => 50,
                'clutch' => 50, 'consistency' => 50, 'talent' => 50,
                'skill' => 50, 'intangibles' => 50,
                'draftyear' => 2018, 'draftround' => 1, 'draftpickno' => 15,
                'draftedby' => 'Test Team', 'draftedbycurrentname' => 'Test Team',
                'college' => 'Test University',
                'htft' => 6, 'htin' => 8, 'wt' => 210,
                'injured' => 0, 'retired' => 0, 'droptime' => 0,
                'exp' => 5, 'bird' => 2,
                'cy' => 1, 'cyt' => 1,
                'salary_yr1' => 800, 'salary_yr2' => 0, 'salary_yr3' => 0,
                'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0,
                'winner' => self::WINNER_PREF, 'tradition' => self::TRADITION_PREF,
                'loyalty' => self::LOYALTY_PREF, 'playing_time' => self::PLAYING_TIME_PREF,
                'security' => 1,
                'teamid' => 1, 'team_city' => 'Test', 'team_name' => 'Team',
                'color1' => 'Blue', 'color2' => 'White',
                'league_record' => '0-0',
            ],
        ]);

        return \Player\Player::withPlayerID($mockDb, 1);
    }
}
