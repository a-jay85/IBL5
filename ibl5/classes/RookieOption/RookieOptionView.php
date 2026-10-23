<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Security\CsrfGuard;
use Security\HtmlSanitizer;
use RookieOption\Contracts\RookieOptionViewInterface;

/**
 * @see RookieOptionViewInterface
 */
class RookieOptionView implements RookieOptionViewInterface
{
    /**
     * @see RookieOptionViewInterface::renderForm()
     */
    public function renderForm(Player $player, string $teamName, int $rookieOptionValue, ?string $error = null, ?string $result = null, ?string $from = null, ?string $cardHtml = null): string
    {
        $playerID = $player->getPlayerID() ?? 0;

        ob_start();
        ?>
<div class="ibl-form-container">
        <?php

        echo \UI\AlertRenderer::fromCodeDaisy($result, [
            'rookie_option_success' => ['variant' => 'success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['variant' => 'warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ], $error);

        // Title, then the flippable trading card (unwrapped), then the option card
        ?>

<h1 class="ibl-title">Rookie Option</h1>

        <?php
        return (string) ob_get_clean() . ($cardHtml ?? '') . $this->renderOptionCard($playerID, $teamName, $rookieOptionValue, $from) . "</div>\n";
    }

    private function renderOptionCard(int $playerID, string $teamName, int $rookieOptionValue, ?string $from): string
    {
        ob_start();
        ?>
<div class="d-card d-card-border bg-base-100 shadow-sm mb-4 overflow-hidden">
    <div class="bg-(image:--gradient-navy-header) px-(--space-4) py-(--space-3) text-white">
        <h2 class="d-card-title justify-center text-center text-(length:--text-2xl)">Rookie Option Value: <?= HtmlSanitizer::e($rookieOptionValue) ?></h2>
    </div>
    <div class="d-card-body text-center">
        <div role="alert" class="d-alert d-alert-warning mb-4">
            By exercising this option, you cannot use an in-season contract extension on this player next season. They will become a free agent after the option year.
        </div>

        <form name="RookieExtend" method="post" action="modules.php?name=Player&amp;pa=processrookieoption" class="text-center">
            <?= CsrfGuard::generateToken('rookie_option') ?>
            <input type="hidden" name="teamname" value="<?= HtmlSanitizer::e($teamName) ?>">
            <input type="hidden" name="playerID" value="<?= HtmlSanitizer::e($playerID) ?>">
            <input type="hidden" name="rookieOptionValue" value="<?= HtmlSanitizer::e($rookieOptionValue) ?>">
            <input type="hidden" name="from" value="<?= HtmlSanitizer::e($from ?? '') ?>">
            <button type="submit" class="d-btn d-btn-error">Exercise Rookie Option</button>
        </form>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

}
