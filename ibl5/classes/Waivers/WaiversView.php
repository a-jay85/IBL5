<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversViewInterface;

/**
 * @see WaiversViewInterface
 */
class WaiversView implements WaiversViewInterface
{
    /**
     * @see WaiversViewInterface::renderWaiverForm()
     *
     * @param array<int, string> $players
     */
    public function renderWaiverForm(
        string $teamName,
        int $teamid,
        string $action,
        array $players,
        int $openRosterSpots,
        int $healthyOpenRosterSpots,
        ?string $result = null,
        ?string $error = null
    ): string {
        ob_start();
        ?>
        <div class="waivers-page">
        <h2 class="ibl-title">Waivers</h2>
        <?= \UI\AlertRenderer::fromCode($result, [
            'player_added'   => ['class' => 'ibl-alert--success', 'message' => 'Player successfully signed from waivers.'],
            'player_dropped' => ['class' => 'ibl-alert--success', 'message' => 'Player successfully dropped to waivers.'],
        ], $error) ?>
        <form name="Waiver_Move" method="post" action="" class="ibl-form-container">
            <?= \Security\CsrfGuard::generateToken('waivers') ?>
            <input type="hidden" name="Team_Name" value="<?= \Security\HtmlSanitizer::e($teamName) ?>">
            <div class="text-center">
                <img src="images/logo/<?= \Security\HtmlSanitizer::e($teamid) ?>.jpg" alt="Team Logo" class="team-logo-banner">
                <div class="ibl-card">
                    <div class="ibl-card__header">
                        <h2 class="ibl-card__title"><?= \Security\HtmlSanitizer::e($openRosterSpots) ?> OPEN SPOTS / <?= \Security\HtmlSanitizer::e($healthyOpenRosterSpots) ?> HEALTHY SPOTS</h2>
                    </div>
                    <div class="ibl-card__body">
                        <div class="ibl-form-group">
                            <select name="Player_ID" class="ibl-select" aria-label="Select player">
                                <option value="">Select player...</option>
                                <?php foreach ($players as $optionHtml): ?>
                                <?= \Security\HtmlSanitizer::trusted($optionHtml) ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="Action" value="<?= \Security\HtmlSanitizer::e($action) ?>">
                        <input type="hidden" name="rosterslots" value="<?= \Security\HtmlSanitizer::e($openRosterSpots) ?>">
                        <input type="hidden" name="healthyrosterslots" value="<?= \Security\HtmlSanitizer::e($healthyOpenRosterSpots) ?>">
                        <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block">
                            Click to <?= \Security\HtmlSanitizer::e($action) ?> player(s) to/from Waiver Pool
                        </button>
                    </div>
                </div>
            </div>
        </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see WaiversViewInterface::buildPlayerOption()
     */
    public function buildPlayerOption(
        int $playerID,
        string $playerName,
        string $contract,
        string $waitTime = ''
    ): string {
        $playerNameEscaped = \Security\HtmlSanitizer::safeHtmlOutput($playerName);
        $contractEscaped = \Security\HtmlSanitizer::safeHtmlOutput($contract);
        $waitTimeEscaped = \Security\HtmlSanitizer::safeHtmlOutput($waitTime);

        $displayText = "{$playerNameEscaped} {$contractEscaped}";
        if ($waitTime !== '') {
            $displayText .= " {$waitTimeEscaped}";
        }

        return "<option value=\"{$playerID}\">{$displayText}</option>";
    }

    /**
     * @see WaiversViewInterface::renderWaiversClosed()
     */
    public function renderWaiversClosed(): string
    {
        return '<div class="waivers-page">'
            . '<h2 class="ibl-title">Waivers</h2>'
            . '<p>Sorry, but players may not be added from or dropped to waivers at the present time.</p>'
            . '</div>';
    }
}
