<?php

declare(strict_types=1);

namespace TradeSuggestions\Feedback;

use TradeSuggestions\Engine\ScoredDeal;

/**
 * Splits scored deals into the ones a GM still sees and the ones they hid.
 * A deal is hidden when its deal key OR its core key matches a stored hide,
 * so a variant with a different pick sweetener stays hidden too.
 */
final class HiddenDealFilter
{
    /**
     * @param list<ScoredDeal> $scored
     * @param array{deal: list<string>, core: list<string>} $hidden
     * @return array{kept: list<ScoredDeal>, dropped: list<ScoredDeal>}
     */
    public static function apply(array $scored, array $hidden): array
    {
        $deal = array_flip($hidden['deal']);
        $core = array_flip($hidden['core']);
        $kept = [];
        $dropped = [];
        foreach ($scored as $d) {
            $key = DealKey::forCandidate($d->candidate);
            if (isset($deal[$key->deal]) || isset($core[$key->core])) {
                $dropped[] = $d;
            } else {
                $kept[] = $d;
            }
        }

        return ['kept' => $kept, 'dropped' => $dropped];
    }
}
