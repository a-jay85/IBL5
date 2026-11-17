<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * The laugh-test vetoes, run together. A deal that fires any of them is never
 * shown.
 */
final class VetoSet
{
    /**
     * @param list<VetoInterface> $vetoes
     */
    public function __construct(private readonly array $vetoes)
    {
    }

    /**
     * The six F15 vetoes, in F15 order.
     */
    public static function standard(): self
    {
        return new self([
            new YoungerBetterCheaperForOlderWorse(),
            new WinNowWorseThisSeason(),
            new RebuilderOldLongMoneyUnpaid(),
            new PickToNonBurdenTaker(),
            new RosterOverMaxAfter(),
            new StrongForWeakWithoutNetGain(),
        ]);
    }

    /**
     * Runs every veto, never stopping at the first hit, so a deal that trips
     * two reports both ids.
     *
     * A veto that throws propagates. The panel's caller hides the panel
     * rather than show an unchecked idea.
     */
    public function evaluate(DealView $deal): VetoReport
    {
        $fired = [];
        foreach ($this->vetoes as $veto) {
            if ($veto->fires($deal)) {
                $fired[] = $veto->id();
            }
        }

        return new VetoReport($fired);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (VetoInterface $veto): string => $veto->id(), $this->vetoes);
    }
}
