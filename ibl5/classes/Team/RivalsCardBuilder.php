<?php

declare(strict_types=1);

namespace Team;

use Rivalries\Contracts\RivalriesServiceInterface;
use Team\Views\RivalsView;

/**
 * RivalsCardBuilder - Fetches a team's and its GM's top rivals and renders the card.
 */
final class RivalsCardBuilder
{
    private const CARD_LIMIT = 3;

    private RivalsView $view;

    /**
     * @param \Closure(int): ?string $ownerName resolves a teamid to its GM's name
     */
    public function __construct(
        private readonly RivalriesServiceInterface $service,
        private readonly \Closure $ownerName,
        ?RivalsView $view = null,
    ) {
        $this->view = $view ?? new RivalsView();
    }

    public function build(int $teamid, string $color1, string $color2): string
    {
        $rivals = $this->service->getTopFranchiseRivals($teamid, self::CARD_LIMIT);
        $gm = ($this->ownerName)($teamid);
        $gmRivals = ($gm !== null && $gm !== '') ? $this->service->getTopGmRivals($gm, self::CARD_LIMIT) : [];

        return $this->view->render($rivals, $gm !== '' ? $gm : null, $gmRivals, \UI\TableStyles::inlineTeamVars($color1, $color2));
    }
}
