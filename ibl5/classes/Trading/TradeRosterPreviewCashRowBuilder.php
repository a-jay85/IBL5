<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradeRosterPreviewCashRowBuilderInterface;
use Trading\Contracts\TradeRosterPreviewParamValidatorInterface;

/**
 * Builds the synthetic cash-consideration rows for the trade roster preview's
 * contracts view.
 *
 * Extracted verbatim from TradeRosterPreviewApiHandler. Pure — reads the
 * already-validated cash `$_GET` parameters through the injected
 * TradeRosterPreviewParamValidatorInterface and never touches the database, so
 * it needs no database connection. Behaviour must not change; the sanitizer calls and the
 * cash-label strings are security-load-bearing.
 */
class TradeRosterPreviewCashRowBuilder implements TradeRosterPreviewCashRowBuilderInterface
{
    /**
     * Maximum cash year index accepted by buildCashRows().
     *
     * cashStartYear/cashEndYear are contract-year indices (1–6), matching the
     * six salary columns (salary_yr1…salary_yr6) and makeCashRow()'s own
     * cyIndex guard. Anything above 6 would be silently dropped by makeCashRow()
     * anyway; reject it here so a crafted cashEndYear cannot drive the loop past
     * the useful range.
     */
    public const CASH_YEAR_FORWARD_HORIZON = 6;

    public function __construct(private TradeRosterPreviewParamValidatorInterface $validator)
    {
    }

    /**
     * Build synthetic cash rows for the contracts view
     *
     * Creates in-memory player-format rows representing cash exchanges,
     * mirroring the pattern used by CashTransactionHandler::createCashTransaction().
     *
     * @param int $maxCashYear Ceiling for cashEndYear; a requested end year above
     *                         this is rejected outright (no rows built) rather than
     *                         clamped.
     * @return list<array<string, mixed>> Synthetic cash player rows with isCashRow flag
     */
    public function buildCashRows(int $viewingTeamId, int $maxCashYear): array
    {
        $userTeam = $this->validator->validateStringParam('userTeam');
        $partnerTeam = $this->validator->validateStringParam('partnerTeam');
        $userTeamId = $this->validator->validateIntParam('userTeamId');
        [$cashStartYear, $cashEndYear] = $this->validator->validateCashYearRange($maxCashYear);

        if ($userTeam === '' || $partnerTeam === '' || $cashStartYear === 0 || $cashEndYear === 0) {
            return [];
        }

        // Sanitize team names before embedding in HTML labels
        $userTeam = \Security\HtmlSanitizer::safeHtmlOutput($userTeam);
        $partnerTeam = \Security\HtmlSanitizer::safeHtmlOutput($partnerTeam);

        $cash = $this->collectCashByYear($cashStartYear, $cashEndYear);

        if (!$cash['user']['hasCash'] && !$cash['partner']['hasCash']) {
            return [];
        }

        // The viewed team's outgoing cash renders as "Cash to <counterparty>"
        // (positive) and its incoming cash as "Cash from <counterparty>" (negated).
        if ($viewingTeamId === $userTeamId) {
            return $this->rowsForSide($partnerTeam, $viewingTeamId, $cash['user'], $cash['partner'], $cashStartYear, $cashEndYear);
        }

        return $this->rowsForSide($userTeam, $viewingTeamId, $cash['partner'], $cash['user'], $cashStartYear, $cashEndYear);
    }

    /**
     * Read the validated per-year cash amounts for both sides of the trade.
     *
     * Reads userCash{yr} and partnerCash{yr} from the validator for every year
     * in [$cashStartYear, $cashEndYear]. A side "has cash" when any of its
     * amounts is above zero; validateCashAmount() never returns a negative.
     *
     * @return array{
     *     user: array{amounts: array<int, int>, hasCash: bool},
     *     partner: array{amounts: array<int, int>, hasCash: bool}
     * }
     */
    private function collectCashByYear(int $cashStartYear, int $cashEndYear): array
    {
        /** @var array<int, int> $userCash */
        $userCash = [];
        /** @var array<int, int> $partnerCash */
        $partnerCash = [];
        $hasUserCash = false;
        $hasPartnerCash = false;

        for ($yr = $cashStartYear; $yr <= $cashEndYear; $yr++) {
            $uAmount = $this->validator->validateCashAmount('userCash' . $yr);
            $pAmount = $this->validator->validateCashAmount('partnerCash' . $yr);
            $userCash[$yr] = $uAmount;
            $partnerCash[$yr] = $pAmount;
            if ($uAmount > 0) {
                $hasUserCash = true;
            }
            if ($pAmount > 0) {
                $hasPartnerCash = true;
            }
        }

        return [
            'user' => ['amounts' => $userCash, 'hasCash' => $hasUserCash],
            'partner' => ['amounts' => $partnerCash, 'hasCash' => $hasPartnerCash],
        ];
    }

    /**
     * Build the cash rows for one viewed team, in display order.
     *
     * @param array{amounts: array<int, int>, hasCash: bool} $outgoing cash the viewed team sends ("Cash to", positive)
     * @param array{amounts: array<int, int>, hasCash: bool} $incoming cash the viewed team receives ("Cash from", negated)
     * @return list<array<string, mixed>>
     */
    private function rowsForSide(string $counterparty, int $teamId, array $outgoing, array $incoming, int $cashStartYear, int $cashEndYear): array
    {
        $rowSpecs = [
            ['| Cash to ' . $counterparty, $outgoing, false],
            ['| Cash from ' . $counterparty, $incoming, true],
        ];

        $rows = [];
        foreach ($rowSpecs as [$label, $side, $negate]) {
            if (!$side['hasCash']) {
                continue;
            }
            $rows[] = $this->makeCashRow($label, $teamId, $side['amounts'], $cashStartYear, $cashEndYear, $negate);
        }

        return $rows;
    }

    /**
     * Create a single synthetic cash player row
     *
     * @param array<int, int> $amounts Cash amounts keyed by year index
     * @return array<string, mixed>
     */
    public function makeCashRow(string $label, int $teamId, array $amounts, int $startYear, int $endYear, bool $negate): array
    {
        $salaryYr1 = $salaryYr2 = $salaryYr3 = $salaryYr4 = $salaryYr5 = $salaryYr6 = 0;
        $totalYears = 0;

        for ($yr = $startYear; $yr <= $endYear; $yr++) {
            $amount = $amounts[$yr] ?? 0;
            if ($negate) {
                $amount = -$amount;
            }
            $cyIndex = $yr - $startYear + 1;
            if ($cyIndex >= 1 && $cyIndex <= 6) {
                match ($cyIndex) {
                    1 => $salaryYr1 = $amount,
                    2 => $salaryYr2 = $amount,
                    3 => $salaryYr3 = $amount,
                    4 => $salaryYr4 = $amount,
                    5 => $salaryYr5 = $amount,
                    6 => $salaryYr6 = $amount,
                };
                if ($amount !== 0 && $cyIndex > $totalYears) {
                    $totalYears = $cyIndex;
                }
            }
        }

        if ($totalYears === 0) {
            $totalYears = 1;
        }

        return [
            // Basic fields
            'pid' => 0,
            'name' => $label,
            'nickname' => '',
            'ordinal' => 100000,
            'teamid' => $teamId,
            'pos' => '',
            'age' => null,
            'color1' => null,
            'color2' => null,
            // Ratings (all zero, matching DB cash rows)
            'r_fga' => 0, 'r_fgp' => 0, 'r_fta' => 0, 'r_ftp' => 0,
            'r_3ga' => 0, 'r_3gp' => 0, 'r_orb' => 0, 'r_drb' => 0,
            'r_ast' => 0, 'r_stl' => 0, 'r_tvr' => 0, 'r_blk' => 0, 'r_foul' => 0,
            'oo' => 0, 'od' => 0, 'r_drive_off' => 0, 'dd' => 0,
            'po' => 0, 'pd' => 0, 'r_trans_off' => 0, 'td' => 0,
            'clutch' => null, 'consistency' => null,
            'talent' => 0, 'skill' => 0, 'intangibles' => 0,
            // Free agency (null, matching DB cash rows)
            'loyalty' => null, 'playing_time' => null, 'winner' => null,
            'tradition' => null, 'security' => null,
            // Contract fields
            'exp' => 1,
            'bird' => null,
            'cy' => 1,
            'cyt' => $totalYears,
            'salary_yr1' => $salaryYr1, 'salary_yr2' => $salaryYr2, 'salary_yr3' => $salaryYr3,
            'salary_yr4' => $salaryYr4, 'salary_yr5' => $salaryYr5, 'salary_yr6' => $salaryYr6,
            // Draft (zero/empty, matching DB cash rows)
            'draftyear' => 0, 'draftround' => 0, 'draftpickno' => 0,
            'draftedby' => '', 'draftedbycurrentname' => '', 'college' => '',
            // Physical (zero, matching DB cash rows)
            'htft' => 0, 'htin' => 0, 'wt' => 0,
            // Status
            'injured' => null,
            'retired' => 0,
            'droptime' => 0,
            // Cash row flag
            'isCashRow' => true,
        ];
    }
}
