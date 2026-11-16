<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Repositories\TeamIdentityRepository;
use Tests\WideUnit\Mocks\Season;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TradeCandidate;
use Trading\TradeCapCalculator;
use Trading\TradeFormRepository;
use Trading\TradeValidator;

/**
 * Runs SuggestionValidationFilter against the real TradeValidator, TradeCapCalculator
 * and TradeFormRepository, so the payload it builds is checked by the same cap and
 * roster rules a submitted trade offer hits.
 */
#[Group('database')]
class SuggestionValidationFilterRealValidatorTest extends DatabaseTestCase
{
    private const int USER_TID = 1;
    private const int PARTNER_TID = 3;

    private SuggestionValidationFilter $filter;
    private string $userTeamName;
    private string $partnerTeamName;

    protected function setUp(): void
    {
        parent::setUp();

        $season = new Season($this->db);
        $teamIdentity = new TeamIdentityRepository($this->db);
        $validator = new TradeValidator($this->db, $season);
        $capCalculator = new TradeCapCalculator($teamIdentity, null, $season, $validator, $this->db);

        $this->filter = new SuggestionValidationFilter($validator, $capCalculator, new TradeFormRepository($this->db), $season);
        $this->userTeamName = $teamIdentity->getTeamnameFromTeamID(self::USER_TID) ?? '';
        $this->partnerTeamName = $teamIdentity->getTeamnameFromTeamID(self::PARTNER_TID) ?? '';
        $this->assertNotSame('', $this->userTeamName);
        $this->assertNotSame('', $this->partnerTeamName);
    }

    public function testRealValidatorRejectsOverCapSuggestion(): void
    {
        // The user keeps a 1000 contract and takes on a max (7000) deal, so its
        // post-trade payroll is above the 7000 hard cap whatever the seed holds.
        $this->insertTestPlayer(200000101, 'Suggest Keeper', ['teamid' => self::USER_TID, 'salary_yr1' => 1000]);
        $this->insertTestPlayer(200000102, 'Suggest Outgoing', ['teamid' => self::USER_TID, 'salary_yr1' => 100]);
        $this->insertTestPlayer(200000103, 'Suggest Max Deal', ['teamid' => self::PARTNER_TID, 'salary_yr1' => 7000]);

        $candidate = new TradeCandidate(self::USER_TID, self::PARTNER_TID, [200000102], [], [200000103], []);

        $this->assertFalse($this->runFilter($candidate));
    }

    public function testRealValidatorAcceptsLegalSuggestion(): void
    {
        $this->insertTestPlayer(200000111, 'Suggest Swap Out', ['teamid' => self::USER_TID, 'salary_yr1' => 500]);
        $this->insertTestPlayer(200000112, 'Suggest Swap In', ['teamid' => self::PARTNER_TID, 'salary_yr1' => 600]);

        $candidate = new TradeCandidate(self::USER_TID, self::PARTNER_TID, [200000111], [], [200000112], []);

        $this->assertTrue($this->runFilter($candidate));
    }

    private function runFilter(TradeCandidate $candidate): bool
    {
        $data = $this->filter->buildTradeFormData($this->userTeamName, $this->partnerTeamName, self::USER_TID, self::PARTNER_TID, $candidate);

        return $this->filter->passes($data, self::USER_TID, self::PARTNER_TID, $candidate);
    }
}
