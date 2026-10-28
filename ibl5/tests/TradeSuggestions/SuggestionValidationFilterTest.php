<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\Season;
use TradeSuggestions\SuggestionValidationFilter;
use TradeSuggestions\TradeCandidate;
use Trading\Contracts\TradeFormRepositoryInterface;
use Trading\Contracts\TradeValidatorInterface;
use Trading\TradeCapCalculator;
use Validation\ValidationError;
use Validation\ValidationResult;
use Validation\ValidationResultWithContext;

class SuggestionValidationFilterTest extends TestCase
{
    private const array CAP_DATA = [
        'userCurrentSeasonCapTotal' => 6000,
        'partnerCurrentSeasonCapTotal' => 5000,
        'userCapSentToPartner' => 800,
        'partnerCapSentToUser' => 900,
    ];

    public function testBuildTradeFormDataChecksOnlyCandidateAssets(): void
    {
        $filter = $this->buildFilter(self::createStub(TradeValidatorInterface::class));
        $candidate = new TradeCandidate(1, 2, [12], [501], [21], []);

        $data = $filter->buildTradeFormData('Usermen', 'Partners', 1, 2, $candidate);

        $this->assertSame('Usermen', $data['offeringTeam']);
        $this->assertSame('Partners', $data['listeningTeam']);
        // User side: 2 players + 2 picks; partner side: 2 players + 1 pick.
        $this->assertSame(4, $data['switchCounter']);
        $this->assertSame(7, $data['fieldsCounter']);
        $this->assertSame(['11', '12', '501', '502', '21', '22', '601'], $data['index']);
        $this->assertSame(['1', '1', '0', '0', '1', '1', '0'], $data['type']);
        $this->assertSame([null, 'on', 'on', null, 'on', null, null], $data['check']);
        // Regular season: the current contract year (cy) salary is posted.
        $this->assertSame(['400', '800', '0', '0', '900', '300', '0'], $data['contract']);
        $this->assertSame([], $data['userSendsCash']);
        $this->assertSame([], $data['partnerSendsCash']);
    }

    public function testRejectsWhenSalaryCapFails(): void
    {
        $validator = self::createMock(TradeValidatorInterface::class);
        $validator->expects($this->once())->method('validateSalaryCaps')->with(self::CAP_DATA)
            ->willReturn(ValidationResultWithContext::fromErrors([new ValidationError('cap', 'Over the hard cap.')], ['userPostTradeCapTotal' => 7100, 'partnerPostTradeCapTotal' => 4900]));
        $validator->expects($this->never())->method('validateRosterLimits');
        $validator->expects($this->never())->method('canPlayerBeTraded');

        $this->assertFalse($this->runPasses($validator));
    }

    public function testRejectsWhenRosterLimitFails(): void
    {
        $validator = self::createMock(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(self::capOk());
        $validator->expects($this->once())->method('validateRosterLimits')->with(1, 2, 1, 1)
            ->willReturn(ValidationResult::failure('Roster limit exceeded.'));
        $validator->expects($this->never())->method('canPlayerBeTraded');

        $this->assertFalse($this->runPasses($validator));
    }

    public function testRejectsUntradeablePlayer(): void
    {
        $validator = self::createStub(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(self::capOk());
        $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
        $validator->method('canPlayerBeTraded')->willReturnCallback(static fn (int $pid): bool => $pid !== 21);

        $this->assertFalse($this->runPasses($validator));
    }

    public function testPassesWhenAllValidatorsPass(): void
    {
        $validator = self::createMock(TradeValidatorInterface::class);
        $validator->method('validateSalaryCaps')->willReturn(self::capOk());
        $validator->method('validateRosterLimits')->willReturn(ValidationResult::success());
        $checked = [];
        $validator->expects($this->exactly(2))->method('canPlayerBeTraded')
            ->willReturnCallback(static function (int $pid) use (&$checked): bool {
                $checked[] = $pid;

                return true;
            });

        $this->assertTrue($this->runPasses($validator));
        $this->assertSame([12, 21], $checked);
    }

    /**
     * A deal naming an asset the form does not list (sold, waived) would leave
     * the validator judging a smaller trade than the one shown.
     */
    public function testRejectsDealTheFormCannotFullyTick(): void
    {
        $validator = self::createMock(TradeValidatorInterface::class);
        $validator->expects($this->never())->method('validateSalaryCaps');
        $filter = $this->buildFilter($validator);
        $candidate = new TradeCandidate(1, 2, [12, 99], [], [21], []);

        $data = $filter->buildTradeFormData('Usermen', 'Partners', 1, 2, $candidate);

        $this->assertSame(SuggestionValidationFilter::FAIL_FORM, $filter->rejection($data, 1, 2, $candidate));
    }

    public function testCapRoomUsesTheValidatorsCapMathWithNothingTicked(): void
    {
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getTeamPlayersForTrading')->willReturn([self::playerRow(11, 1, 400)]);
        $formRepo->method('getTeamDraftPicksForTrading')->willReturn([]);

        $capCalculator = self::createMock(TradeCapCalculator::class);
        $capCalculator->expects($this->once())->method('calculateSalaryCapData')
            ->with(self::callback(static fn (array $form): bool => $form['offeringTeam'] === 'Usermen'
                && $form['listeningTeam'] === 'Usermen'
                && !in_array('on', $form['check'], true)))
            ->willReturn(['userCurrentSeasonCapTotal' => 6650, 'partnerCurrentSeasonCapTotal' => 6650, 'userCapSentToPartner' => 0, 'partnerCapSentToUser' => 0]);

        $filter = new SuggestionValidationFilter(self::createStub(TradeValidatorInterface::class), $capCalculator, $formRepo, new Season(self::createStub(\mysqli::class)));

        $this->assertSame(\League\League::HARD_CAP_MAX - 6650, $filter->capRoom('Usermen', 1));
    }

    private function runPasses(TradeValidatorInterface $validator): bool
    {
        $filter = $this->buildFilter($validator);
        $candidate = new TradeCandidate(1, 2, [12], [], [21], []);
        $data = $filter->buildTradeFormData('Usermen', 'Partners', 1, 2, $candidate);

        return $filter->passes($data, 1, 2, $candidate);
    }

    private function buildFilter(TradeValidatorInterface $validator): SuggestionValidationFilter
    {
        $formRepo = self::createStub(TradeFormRepositoryInterface::class);
        $formRepo->method('getTeamPlayersForTrading')->willReturnMap([
            [1, [self::playerRow(11, 1, 400), self::playerRow(12, 2, 800)]],
            [2, [self::playerRow(21, 1, 900), self::playerRow(22, 1, 300)]],
        ]);
        $formRepo->method('getTeamDraftPicksForTrading')->willReturnMap([
            [1, [self::pickRow(501), self::pickRow(502)]],
            [2, [self::pickRow(601)]],
        ]);

        $capCalculator = self::createStub(TradeCapCalculator::class);
        $capCalculator->method('calculateSalaryCapData')->willReturn(self::CAP_DATA);

        $season = new Season(self::createStub(\mysqli::class));

        return new SuggestionValidationFilter($validator, $capCalculator, $formRepo, $season);
    }

    /**
     * @return ValidationResultWithContext<array{userPostTradeCapTotal: int, partnerPostTradeCapTotal: int}>
     */
    private static function capOk(): ValidationResultWithContext
    {
        return ValidationResultWithContext::success(['userPostTradeCapTotal' => 6100, 'partnerPostTradeCapTotal' => 4900]);
    }

    /**
     * Salary sits in the current contract year's column; other years are 0.
     *
     * @return array{pos: string, name: string, pid: int, ordinal: ?int, cy: ?int, salary_yr1: ?int, salary_yr2: ?int, salary_yr3: ?int, salary_yr4: ?int, salary_yr5: ?int, salary_yr6: ?int}
     */
    private static function playerRow(int $pid, int $cy, int $salary): array
    {
        return [
            'pos' => 'SF',
            'name' => 'Player ' . $pid,
            'pid' => $pid,
            'ordinal' => 1,
            'cy' => $cy,
            'salary_yr1' => $cy === 1 ? $salary : 0,
            'salary_yr2' => $cy === 2 ? $salary : 0,
            'salary_yr3' => 0,
            'salary_yr4' => 0,
            'salary_yr5' => 0,
            'salary_yr6' => 0,
        ];
    }

    /**
     * @return array{pickid: int, ownerofpick: string, teampick: string, teampick_id: int, year: string, round: string, notes: ?string, created_at: string, updated_at: string}
     */
    private static function pickRow(int $pickId): array
    {
        return [
            'pickid' => $pickId,
            'ownerofpick' => 'Owner',
            'teampick' => 'Original',
            'teampick_id' => 1,
            'year' => '2027',
            'round' => '1',
            'notes' => null,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ];
    }
}
