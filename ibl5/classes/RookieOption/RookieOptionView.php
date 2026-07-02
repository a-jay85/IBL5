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

        echo \UI\AlertRenderer::fromCode($result, [
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ], $error);

        // Title, then the flippable trading card (unwrapped), then the option card
        ?>

<h1 class="ibl-title">Rookie Option</h1>

<?= HtmlSanitizer::trusted($cardHtml ?? '') ?>

<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Rookie Option Value: <?= HtmlSanitizer::e($rookieOptionValue) ?></h2>
    </div>
    <div class="ibl-card__body text-center">
        <div class="ibl-alert ibl-alert--warning">
            By exercising this option, you cannot use an in-season contract extension on this player next season. They will become a free agent after the option year.
        </div>

        <form name="RookieExtend" method="post" action="modules.php?name=Player&amp;pa=processrookieoption" class="text-center">
            <?= CsrfGuard::generateToken('rookie_option') ?>
            <input type="hidden" name="teamname" value="<?= HtmlSanitizer::e($teamName) ?>">
            <input type="hidden" name="playerID" value="<?= HtmlSanitizer::e($playerID) ?>">
            <input type="hidden" name="rookieOptionValue" value="<?= HtmlSanitizer::e($rookieOptionValue) ?>">
            <input type="hidden" name="from" value="<?= HtmlSanitizer::e($from ?? '') ?>">
            <button type="submit" class="ibl-btn ibl-btn--danger">Exercise Rookie Option</button>
        </form>
    </div>
</div>

</div>
        <?php
        return (string) ob_get_clean();
    }

}
