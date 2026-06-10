<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Security\HtmlSanitizer;
use Player\PlayerImageHelper;
use RookieOption\Contracts\RookieOptionViewInterface;

/**
 * @see RookieOptionViewInterface
 */
class RookieOptionView implements RookieOptionViewInterface
{
    /**
     * @see RookieOptionViewInterface::renderForm()
     */
    public function renderForm(Player $player, string $teamName, int $rookieOptionValue, ?string $error = null, ?string $result = null, ?string $from = null): string
    {
        $playerID = $player->getPlayerID() ?? 0;
        $playerImageUrl = PlayerImageHelper::getImageUrl($playerID);

        ob_start();
        ?>
<div class="ibl-form-container">
        <?php

        echo \UI\AlertRenderer::fromCode($result, [
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ], $error);

        // Card: Player Info + Rookie Option Form
        ?>

<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title"><?= HtmlSanitizer::e($player->getPosition() ?? '') ?> <?= HtmlSanitizer::e($player->getName() ?? '') ?> - Rookie Option</h2>
    </div>
    <div class="ibl-card__body text-center">
        <img src="<?= HtmlSanitizer::e($playerImageUrl) ?>" alt="<?= HtmlSanitizer::e($player->getName() ?? '') ?>" class="rookie-option-img">
        <div>
            <span class="ibl-label">Rookie Option Value:</span>
            <strong><?= HtmlSanitizer::e($rookieOptionValue) ?></strong>
        </div>
    </div>
</div>

<div class="ibl-alert ibl-alert--warning">
    <strong>Warning:</strong><br>By exercising this option, you cannot use an in-season contract extension on this player next season. They will become a free agent after the option year.
</div>

<form name="RookieExtend" method="post" action="modules.php?name=Player&amp;pa=processrookieoption" class="text-center">
    <input type="hidden" name="teamname" value="<?= HtmlSanitizer::e($teamName) ?>">
    <input type="hidden" name="playerID" value="<?= HtmlSanitizer::e($playerID) ?>">
    <input type="hidden" name="rookieOptionValue" value="<?= HtmlSanitizer::e($rookieOptionValue) ?>">
    <input type="hidden" name="from" value="<?= HtmlSanitizer::e($from ?? '') ?>">
    <button type="submit" class="ibl-btn ibl-btn--danger">Exercise Rookie Option</button>
</form>

</div>
        <?php
        return (string) ob_get_clean();
    }

}
