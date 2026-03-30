<?php

declare(strict_types=1);

namespace FreeAgency;

use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;
use Team\Team;

/**
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 * @phpstan-type NegotiationData array{player: Player, capMetrics: CapMetrics, demands: array<string, int>, existingOffer: array<string, int>, amendedCapSpace: int, hasExistingOffer: bool, veteranMinimum: int, maxContract: int, team: Team}
 */
class FreeAgencyNegotiationView
{
    private FreeAgencyFormComponents $formComponents;

    public function __construct(FreeAgencyFormComponents $formComponents)
    {
        $this->formComponents = $formComponents;
    }

    /**
     * @param NegotiationData $negotiationData
     */
    public function render(array $negotiationData, ?string $error = null): string
    {
        $player = $negotiationData['player'];
        $capMetrics = $negotiationData['capMetrics'];
        $demands = $negotiationData['demands'];
        $existingOffer = $negotiationData['existingOffer'];
        $amendedCapSpace = $negotiationData['amendedCapSpace'];
        $hasExistingOffer = $negotiationData['hasExistingOffer'];
        $veteranMinimum = $negotiationData['veteranMinimum'];
        $maxContract = $negotiationData['maxContract'];
        $team = $negotiationData['team'];

        // Generate a single CSRF token for all forms on this page.
        // The negotiate page has 16+ forms (custom, delete, quick-offer buttons).
        // CsrfGuard's MAX_TOKENS=10 would evict the custom form's token if each
        // form generated its own. One shared token avoids this.
        $csrfToken = \Utilities\CsrfGuard::generateRawToken('free_agency');
        $csrfHtml = '<input type="hidden" name="_csrf_token" value="' . $csrfToken . '">';
        $this->formComponents->setCsrfHtml($csrfHtml);

        ob_start();

        echo '<h2 class="ibl-title">Free Agency</h2>';

        // Error banner from PRG redirect
        if ($error !== null) {
            $sanitizedError = \Utilities\HtmlSanitizer::safeHtmlOutput($error);
            ?>
<div class="ibl-alert ibl-alert--error"><?= $sanitizedError ?></div>
            <?php
        }

        // No roster spots warning
        if ($capMetrics['rosterSpots'][0] < 1 && !$hasExistingOffer) {
            ?>
<div class="ibl-alert ibl-alert--warning">Sorry, you have no roster spots remaining and cannot offer a contract to this player.</div>
            <?php
            return (string) ob_get_clean();
        }

        // Card 1: Player Info
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title"><?= HtmlSanitizer::e($player->position ?? '') ?> <?= HtmlSanitizer::e($player->name ?? '') ?> - Contract Negotiation</h2>
    </div>
    <div class="ibl-card__body">
        <div class="offer-player-info">
            <img src="<?= HtmlSanitizer::e(PlayerImageHelper::getImageUrl($player->playerID)) ?>" alt="<?= HtmlSanitizer::e($player->name ?? '') ?>" class="offer-player-img">
            <?= $this->formComponents->renderPlayerRatings() ?>
        </div>
    </div>
</div>

<?php // Card 2: Demands + Custom Offer Form ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Contract Offer</h2>
    </div>
    <div class="ibl-card__body">
        <div class="ibl-field-group">
            <span class="ibl-label">Player Demands (base, before team modifiers):</span>
            <div class="ibl-field-group__content">
                <?= $this->formComponents->renderDemandDisplay($demands) ?>
            </div>
        </div>

        <form name="FAOffer" method="post" action="modules.php?name=FreeAgency&pa=processoffer">
            <?= $csrfHtml ?>
            <div class="ibl-field-group">
                <span class="ibl-label">Your Custom Offer:</span>
                <div class="ibl-field-group__content">
                    <?= $this->formComponents->renderOfferInputs($existingOffer) ?>
                </div>
            </div>

            <input type="hidden" name="teamname" value="<?= HtmlSanitizer::e($team->name) ?>">
            <input type="hidden" name="playerID" value="<?= (int) $player->playerID ?>">
            <input type="hidden" name="offerType" value="0">

            <button type="submit" class="ibl-btn ibl-btn--primary">Offer / Amend Free Agent Contract</button>
        </form>
    </div>
</div>

<?php // Card 3: Quick Offer Presets ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Quick Offer Presets</h2>
    </div>
    <div class="ibl-card__body">
        <?= $this->renderOfferButtons($player) ?>
    </div>
</div>

<?php // Card 4: Notes & Reminders ?>
<?= $this->renderNotesReminders($maxContract, $veteranMinimum, $amendedCapSpace, $capMetrics, $player->birdYears ?? 0) ?>

<?php // Delete Offer (conditional) ?>
<?php if ($hasExistingOffer): ?>
<div class="offer-delete-section">
    <form method="post" action="modules.php?name=FreeAgency&pa=deleteoffer">
        <?= $csrfHtml ?>
        <input type="hidden" name="teamname" value="<?= HtmlSanitizer::e($team->name) ?>">
        <input type="hidden" name="playerID" value="<?= (int) $player->playerID ?>">
        <button type="submit" class="ibl-btn ibl-btn--danger">Delete This Offer</button>
    </form>
</div>
<?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render all offer button sections (Max Contract, MLE, LLE, Vet Min)
     *
     * @param Player $player
     * @return string HTML content
     */
    private function renderOfferButtons(Player $player): string
    {
        // Calculate max contract salary and raises based on bird years
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);
        $raisePercentage = \ContractRules::getMaxRaisePercentage($player->birdYears ?? 0);
        $maxRaise = (int) round($maxContract * $raisePercentage);

        $maxSalaries = [
            0 => $maxContract,
            1 => $maxContract + $maxRaise,
            2 => $maxContract + ($maxRaise * 2),
            3 => $maxContract + ($maxRaise * 3),
            4 => $maxContract + ($maxRaise * 4),
            5 => $maxContract + ($maxRaise * 5),
        ];

        ob_start();
        echo $this->formComponents->renderMaxContractButtons($maxSalaries, $player->birdYears ?? 0);
        echo $this->formComponents->renderExceptionButtons('MLE');
        echo $this->formComponents->renderExceptionButtons('LLE');
        echo $this->formComponents->renderExceptionButtons('VET');
        return (string) ob_get_clean();
    }

    /**
     * Render Notes/Reminders card
     *
     * @param int $maxContract Maximum contract value
     * @param int $veteranMinimum Veteran minimum salary
     * @param int $amendedCapSpace Amended cap space for year 1
     * @param CapMetrics $capMetrics Cap space data
     * @param int $birdYears Bird rights years
     * @return string HTML card
     */
    private function renderNotesReminders(
        int $maxContract,
        int $veteranMinimum,
        int $amendedCapSpace,
        array $capMetrics,
        int $birdYears
    ): string {
        $softCapSpace = $capMetrics['softCapSpace'];
        $hardCapSpace = $capMetrics['hardCapSpace'];

        // Calculate raise percentage and example based on bird years (matching validator logic)
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $raisePercentageDisplay = (int)($raisePercentage * 100);
        $exampleSalary = 500;
        $exampleRaise = (int) round($exampleSalary * $raisePercentage);

        $hasBirdRights = \ContractRules::hasBirdRights($birdYears);
        if ($hasBirdRights) {
            $birdRightsText = "<strong>Bird Rights Player on Your Team:</strong> You may add no more than {$raisePercentageDisplay}% of the amount you offer in the first year as a raise between years (for instance, if you offer {$exampleSalary} in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        } else {
            $birdRightsText = "<strong>For Players who do not have Bird Rights with your team:</strong> You may add no more than {$raisePercentageDisplay}% of the amount you offer in the first year as a raise between years (for instance, if you offer {$exampleSalary} in Year 1, you cannot offer a raise of more than {$exampleRaise} between any two subsequent years.)";
        }

        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Notes / Reminders</h2>
    </div>
    <div class="ibl-card__body">
        <ul class="ibl-notes">
            <li>The maximum contract permitted for this player (based on years of service) starts at <?= $maxContract ?> in Year 1.</li>
            <li>You have <strong><?= $amendedCapSpace ?></strong> in <strong>soft cap</strong> space available; the amount you offer in year 1 cannot exceed this unless you are using one of the exceptions.</li>
            <?php for ($year = 1; $year < 6; $year++): ?>
            <li>You have <strong><?= $softCapSpace[$year] ?></strong> in <strong>soft cap</strong> space available; the amount you offer in year <?= $year + 1 ?> cannot exceed this unless you are using one of the exceptions.</li>
            <?php endfor; ?>
            <?php for ($year = 0; $year < 6; $year++): ?>
            <li>You have <strong><?= $hardCapSpace[$year] ?></strong> in <strong>hard cap</strong> space available; the amount you offer in year <?= $year + 1 ?> cannot exceed this.</li>
            <?php endfor; ?>
            <li>Enter "0" for years you do not want to offer a contract.</li>
            <li>The amounts offered each year must equal or exceed the previous year.</li>
            <li>The first year of the contract must be at least the veteran's minimum (<?= $veteranMinimum ?> for this player).</li>
            <li><?= $birdRightsText ?></li>
        </ul>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
