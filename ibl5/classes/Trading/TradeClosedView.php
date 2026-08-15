<?php

declare(strict_types=1);

namespace Trading;

use Season\Season;

class TradeClosedView
{
    /**
     * @see \Trading\Contracts\TradingViewInterface::renderTradesClosed()
     */
    public function renderTradesClosed(Season $season): string
    {
        ob_start();
        echo 'Sorry, but trades are not allowed right now.';
        if ($season->areWaiversAllowed()) {
            echo '<br>Players may still be <a href="modules.php?name=Waivers&amp;action=add">Added From Waivers</a>';
            echo ' or they may be <a href="modules.php?name=Waivers&amp;action=waive">Waived</a>.';
        } else {
            echo '<br>The waiver wire is also closed.';
        }
        return (string) ob_get_clean();
    }
}
