<?php

declare(strict_types=1);

namespace TradeSuggestions\Valuation;

use League\ContractRules;
use TradeSuggestions\SlotMapper;

/**
 * F9 fair salary for ONE season offset: the league's median salary per slot
 * and rank tier. An off-the-books salary (0) is never a data point.
 */
final readonly class FairSalaryTable
{
    public const int TIER_SIZE = 5;

    /**
     * @param array<string, array<int, int>> $medians slot => tier => median salary
     */
    private function __construct(private int $offset, private array $medians)
    {
    }

    public static function tierFor(int $rank): int
    {
        if ($rank < 1) {
            throw new \InvalidArgumentException("Rank {$rank} is below 1");
        }

        return intdiv($rank - 1, self::TIER_SIZE) + 1;
    }

    /**
     * @param array<string, array<int, int>> $poolRanks slot => pid => pool rank
     * @param array<int, int> $salaryByPid pid => salary at this offset
     */
    public static function build(int $offset, array $poolRanks, array $salaryByPid): self
    {
        foreach (array_keys($poolRanks) as $slot) {
            self::assertSlot($slot);
        }

        $medians = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $salariesByTier = [];
            foreach ($poolRanks[$slot] ?? [] as $pid => $rank) {
                $salary = $salaryByPid[$pid] ?? 0;
                if ($salary > 0) {
                    $salariesByTier[self::tierFor($rank)][] = $salary;
                }
            }
            $medians[$slot] = array_map(self::median(...), $salariesByTier);
        }

        return new self($offset, $medians);
    }

    /**
     * The stored median. An empty tier prices like the next WORSE tier with
     * data, then the next better one, then the veteran minimum. A thin top
     * tier should price like the tier under it before the tier above it, so a
     * lone star never inflates the table.
     */
    public function fairSalary(string $slot, int $tier): int
    {
        $tiers = $this->tiers($slot);
        if (isset($tiers[$tier])) {
            return $tiers[$tier];
        }

        $worse = array_filter($tiers, static fn (int $t): bool => $t > $tier, ARRAY_FILTER_USE_KEY);
        if ($worse !== []) {
            return $worse[min(array_keys($worse))];
        }

        if ($tiers !== []) {
            return $tiers[max(array_keys($tiers))];
        }

        return ContractRules::VETERAN_MINIMUM_SALARIES[1];
    }

    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * @return array<int, int> tier => median, data tiers only
     */
    public function tiers(string $slot): array
    {
        self::assertSlot($slot);

        return $this->medians[$slot];
    }

    private static function assertSlot(string $slot): void
    {
        if (!in_array($slot, SlotMapper::SLOTS, true)) {
            throw new \InvalidArgumentException("Unknown slot: {$slot}");
        }
    }

    /**
     * Even counts take the mean of the two middle values, rounded half up.
     *
     * @param non-empty-list<int> $salaries
     */
    private static function median(array $salaries): int
    {
        sort($salaries);
        $count = count($salaries);
        $upperMiddle = $salaries[intdiv($count, 2)];
        if ($count % 2 === 1) {
            return $upperMiddle;
        }

        return intdiv($salaries[intdiv($count, 2) - 1] + $upperMiddle + 1, 2);
    }
}
