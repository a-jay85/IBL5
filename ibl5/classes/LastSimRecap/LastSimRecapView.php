<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapViewInterface;
use LastSimRecap\Dto\RecapSlate;

class LastSimRecapView implements LastSimRecapViewInterface
{
    private readonly RecapHeaderRenderer $headerRenderer;

    private readonly RecapTabsRenderer $tabsRenderer;

    private readonly RecapPanelRenderer $panelRenderer;

    public function __construct(
        ?RecapHeaderRenderer $headerRenderer = null,
        ?RecapTabsRenderer $tabsRenderer = null,
        ?RecapPanelRenderer $panelRenderer = null,
        ?RecapInjuryCellRenderer $injuryCellRenderer = null,
        ?RecapBattlesRenderer $battlesRenderer = null,
    ) {
        $this->headerRenderer = $headerRenderer ?? new RecapHeaderRenderer();
        $this->tabsRenderer = $tabsRenderer ?? new RecapTabsRenderer();
        $this->panelRenderer = $panelRenderer ?? new RecapPanelRenderer(
            $injuryCellRenderer ?? new RecapInjuryCellRenderer(),
            $battlesRenderer ?? new RecapBattlesRenderer(),
        );
    }

    public function render(RecapSlate $slate): string
    {
        $games = $slate->games;

        $html = '<section class="last-sim-recap" data-component="last-sim-recap">';
        $html .= $this->headerRenderer->render($slate);

        if ($games === []) {
            $html .= '<div class="last-sim-recap__empty">No games this last sim.</div>';
        } else {
            $tabCount = count($games);
            $html .= $this->tabsRenderer->render($games, $tabCount);
            foreach ($games as $idx => $game) {
                $html .= $this->panelRenderer->render($slate, $game, $idx);
            }
            $html .= '<script src="jslib/last-sim-recap-tabs.js" defer></script>';
        }

        $html .= '</section>';

        return $html;
    }
}
