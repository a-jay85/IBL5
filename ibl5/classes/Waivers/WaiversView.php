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
        int $teamID,
        string $action,
        array $players,
        int $openRosterSpots,
        int $healthyOpenRosterSpots,
        ?string $result = null,
        ?string $error = null
    ): void {
        /** @var string $teamNameEscaped */
        $teamNameEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($teamName);
        /** @var string $actionEscaped */
        $actionEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($action);

        ob_start();
        ?>
        <h2 class="ibl-title">Waivers</h2>
        <?= $this->renderResultBanner($result, $error) ?>
        <form name="Waiver_Move" method="post" action="" style="max-width: 600px; margin: 0 auto;">
            <input type="hidden" name="Team_Name" value="<?= $teamNameEscaped ?>">
            <div class="text-center">
                <img src="images/logo/<?= $teamID ?>.jpg" alt="Team Logo" class="team-logo-banner">
                <div class="ibl-card">
                    <div class="ibl-card__header">
                        <h2 class="ibl-card__title"><?= $openRosterSpots ?> OPEN SPOTS / <?= $healthyOpenRosterSpots ?> HEALTHY SPOTS</h2>
                    </div>
                    <div class="ibl-card__body">
                        <div class="ibl-form-group">
                            <select name="Player_ID" class="ibl-select">
                                <option value="">Select player...</option>
                                <?php foreach ($players as $optionHtml): ?>
                                <?= $optionHtml ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="Action" value="<?= $actionEscaped ?>">
                        <input type="hidden" name="rosterslots" value="<?= $openRosterSpots ?>">
                        <input type="hidden" name="healthyrosterslots" value="<?= $healthyOpenRosterSpots ?>">
                        <button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--block" onclick="this.disabled=true;this.textContent='Submitting...'; this.form.submit();">
                            Click to <?= $actionEscaped ?> player(s) to/from Waiver Pool
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php
        $output = ob_get_clean();
        if ($output !== false) {
            echo $output;
        }
    }

    private function renderResultBanner(?string $result, ?string $error): string
    {
        if ($error !== null) {
            /** @var string $errorEscaped */
            $errorEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($error);
            return '<div class="ibl-alert ibl-alert--error">' . $errorEscaped . '</div>';
        }

        if ($result === null) {
            return '';
        }

        $banners = [
            'player_added'   => ['class' => 'ibl-alert--success', 'message' => 'Player successfully signed from waivers.'],
            'player_dropped' => ['class' => 'ibl-alert--success', 'message' => 'Player successfully dropped to waivers.'],
        ];

        if (!isset($banners[$result])) {
            return '';
        }

        $banner = $banners[$result];
        /** @var string $messageEscaped */
        $messageEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($banner['message']);
        return '<div class="ibl-alert ' . $banner['class'] . '">' . $messageEscaped . '</div>';
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
        /** @var string $playerNameEscaped */
        $playerNameEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($playerName);
        /** @var string $contractEscaped */
        $contractEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($contract);
        /** @var string $waitTimeEscaped */
        $waitTimeEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($waitTime);

        $displayText = "{$playerNameEscaped} {$contractEscaped}";
        if ($waitTime !== '') {
            $displayText .= " {$waitTimeEscaped}";
        }

        return "<option value=\"{$playerID}\">{$displayText}</option>";
    }

    /**
     * @see WaiversViewInterface::renderWaiversClosed()
     */
    public function renderWaiversClosed(): void
    {
        \Nuke\Header::header();
        echo "Sorry, but players may not be added from or dropped to waivers at the present time.";
        \Nuke\Footer::footer();
    }
}
