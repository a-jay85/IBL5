<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\RankGate;
use TradeSuggestions\Engine\RankGateCallback;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

/**
 * Built over the quiet fixture (two identical backups swapped) with each
 * side's gain scripted, so the reason outcome is set by the test.
 */
class RankGateCallbackTest extends TestCase
{
    private const array TEAM_NAMES = [VetoDealFactory::USER_TID => 'Lakers', VetoDealFactory::PARTNER_TID => 'Heat'];

    public function testValidatorRejectionReturnsValidatorWithFailCode(): void
    {
        [$scored, $view] = self::quiet();
        $callback = RankGateCallback::make(self::filter(tradeable: false, scored: $scored), self::TEAM_NAMES);

        self::assertSame(
            [RankGate::Validator, SuggestionValidationFilter::FAIL_PLAYER, null, null],
            $callback($scored, $view),
        );
    }

    public function testNullReasonReturnsNoMotive(): void
    {
        [$scored, $view] = self::quiet();
        $callback = RankGateCallback::make(self::filter(tradeable: true, scored: $scored), self::TEAM_NAMES);

        $neither = new DealView(self::withGain($view->user, []), self::withGain($view->partner, []), $view->candidate, $view->shapeId);
        self::assertSame([RankGate::NoMotive, 'user', null, null], $callback($scored, $neither));

        $userOnly = new DealView(self::withGain($view->user, ['roster_spots' => 3.0]), self::withGain($view->partner, []), $view->candidate, $view->shapeId);
        self::assertSame([RankGate::NoMotive, 'partner', null, null], $callback($scored, $userOnly));
    }

    public function testBothPassReturnsPass(): void
    {
        [$scored, $view] = self::quiet();
        $callback = RankGateCallback::make(self::filter(tradeable: true, scored: $scored), self::TEAM_NAMES);
        $both = new DealView(self::withGain($view->user, ['roster_spots' => 3.0]), self::withGain($view->partner, ['roster_spots' => 3.0]), $view->candidate, $view->shapeId);

        [$gate, $code, $userReason, $partnerReason] = $callback($scored, $both);

        self::assertSame(RankGate::Pass, $gate);
        self::assertSame('', $code);
        self::assertIsString($userReason);
        self::assertIsString($partnerReason);
        self::assertNotSame('', $userReason);
        self::assertNotSame('', $partnerReason);
    }

    /**
     * @return array{ScoredDeal, DealView}
     */
    private static function quiet(): array
    {
        $fixture = VetoDealFactory::quiet();
        $view = $fixture['deal'];

        return [DealScorer::score($fixture['snapshot'], $view->candidate, 'fixture'), $view];
    }

    /**
     * The same side with scripted offset-0 gain components. No positive
     * component means ReasonWriter has nothing to say.
     *
     * @param array<string, float> $offsetZero
     */
    private static function withGain(SideView $base, array $offsetZero): SideView
    {
        $components = [0 => $offsetZero, 1 => [], 2 => [], 3 => []];

        return new SideView(
            $base->tid,
            $base->mode,
            $base->sent,
            $base->received,
            $base->sentPicks,
            $base->receivedPicks,
            $base->before,
            $base->after,
            new SideGain($base->tid, $base->mode, $components, [0.4, 0.3, 0.2, 0.1], 1.0),
            $base->situation,
            $base->surplusByPid,
            $base->delta,
            $base->pickWorthById,
            $base->availabilityByPid,
            $base->injuryByPid,
        );
    }

    /** A real filter whose form rows hold exactly the deal's players. */
    private static function filter(bool $tradeable, ScoredDeal $scored): SuggestionValidationFilter
    {
        $c = $scored->candidate;
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getTeamPlayersForTrading')->willReturnCallback(
            static fn (int $tid): array => array_map(
                static fn (int $pid): array => ['pid' => $pid, 'cy' => 1, 'salary_yr1' => 100],
                $tid === $c->userTid ? $c->userPids : $c->partnerPids,
            ),
        );
        $formRepo->method('getTeamDraftPicksForTrading')->willReturn([]);

        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(ValidationResultWithContext::success(['userPostTradeCapTotal' => 0, 'partnerPostTradeCapTotal' => 0]));
        $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
        $validator->method('canPlayerBeTraded')->willReturn($tradeable);

        $capCalculator = self::createStub(\Trading\TradeCapCalculator::class);
        $capCalculator->method('calculateSalaryCapData')->willReturn([
            'userCurrentSeasonCapTotal' => 0,
            'partnerCurrentSeasonCapTotal' => 0,
            'userCapSentToPartner' => 0,
            'partnerCapSentToUser' => 0,
        ]);

        $season = new \Tests\WideUnit\Mocks\Season(self::createStub(\mysqli::class));
        $season->endingYear = 2027;

        return new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season);
    }
}
