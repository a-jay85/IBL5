<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Utilities\HtmlSanitizer;
use Player\PlayerImageHelper;
use RookieOption\Contracts\RookieOptionFormViewInterface;

/**
 * @see RookieOptionFormViewInterface
 */
class RookieOptionFormView implements RookieOptionFormViewInterface
{
    /**
     * @see RookieOptionFormViewInterface::renderForm()
     */
    public function renderForm(Player $player, string $teamName, int $rookieOptionValue, ?string $error = null, ?string $result = null, ?string $from = null): string
    {
        $playerID = $player->playerID ?? 0;
        /** @var string $playerPosition */
        $playerPosition = HtmlSanitizer::safeHtmlOutput($player->position ?? '');
        /** @var string $playerName */
        $playerName = HtmlSanitizer::safeHtmlOutput($player->name ?? '');
        /** @var string $teamNameEscaped */
        $teamNameEscaped = HtmlSanitizer::safeHtmlOutput($teamName);
        $playerImageUrl = PlayerImageHelper::getImageUrl($playerID);
        if ($from !== null) {
            /** @var string $fromEscaped */
            $fromEscaped = HtmlSanitizer::safeHtmlOutput($from);
        } else {
            $fromEscaped = '';
        }

        ob_start();

        // Error banner from PRG redirect
        if ($error !== null) {
            /** @var string $safeError */
            $safeError = HtmlSanitizer::safeHtmlOutput($error);
            ?>
<div class="ibl-alert ibl-alert--error"><?= $safeError ?></div>
            <?php
        }

        // Result banner from PRG redirect
        echo $this->renderResultBanner($result);

        // Card: Player Info + Rookie Option Form
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title"><?= $playerPosition ?> <?= $playerName ?> - Rookie Option</h2>
    </div>
    <div class="ibl-card__body">
        <div style="display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap;">
            <img src="<?= htmlspecialchars($playerImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $playerName ?>" style="max-width: 120px; border-radius: 0.375rem;">
            <div>
                <div style="margin-bottom: 0.5rem;">
                    <span class="ibl-label">Rookie Option Value:</span>
                    <strong style="font-weight: bold;"><?= $rookieOptionValue ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ibl-alert ibl-alert--warning">
    <strong style="font-weight: bold;">Warning:</strong> By exercising this option, you cannot use an in-season contract extension on this player next season. They will become a free agent after the option year.
</div>

<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Exercise Option</h2>
    </div>
    <div class="ibl-card__body">
        <form name="RookieExtend" method="post" action="modules.php?name=Player&amp;pa=processrookieoption">
            <input type="hidden" name="teamname" value="<?= $teamNameEscaped ?>">
            <input type="hidden" name="playerID" value="<?= $playerID ?>">
            <input type="hidden" name="rookieOptionValue" value="<?= $rookieOptionValue ?>">
            <input type="hidden" name="from" value="<?= $fromEscaped ?>">
            <button type="submit" class="ibl-btn ibl-btn--primary">Exercise Rookie Option</button>
        </form>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    private function renderResultBanner(?string $result): string
    {
        if ($result === null) {
            return '';
        }

        $banners = [
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ];

        if (!isset($banners[$result])) {
            return '';
        }

        $banner = $banners[$result];
        /** @var string $safeMessage */
        $safeMessage = HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<div class="ibl-alert ' . $banner['class'] . '">' . $safeMessage . '</div>';
    }
}
