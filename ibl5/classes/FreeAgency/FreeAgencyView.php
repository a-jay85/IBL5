<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyTableRendererInterface;
use FreeAgency\Contracts\FreeAgencyUnderContractSectionViewInterface;
use FreeAgency\Contracts\FreeAgencyContractOffersSectionViewInterface;
use FreeAgency\Contracts\FreeAgencyTeamFreeAgentsSectionViewInterface;
use FreeAgency\Contracts\FreeAgencyOtherFreeAgentsSectionViewInterface;
use Player\Player;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;
use Repositories\Contracts\TeamIdentityRepositoryInterface;

/**
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 * @phpstan-type CapMetrics array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}
 */
class FreeAgencyView
{
    private FreeAgencyTableRendererInterface $tableRenderer;
    private FreeAgencyUnderContractSectionViewInterface $underContractSection;
    private FreeAgencyContractOffersSectionViewInterface $offersSection;
    private FreeAgencyTeamFreeAgentsSectionViewInterface $teamFaSection;
    private FreeAgencyOtherFreeAgentsSectionViewInterface $otherFaSection;

    public function __construct(
        TeamIdentityRepositoryInterface $commonRepo,
        ?FreeAgencyTableRendererInterface $tableRenderer = null,
        ?FreeAgencyUnderContractSectionViewInterface $underContractSection = null,
        ?FreeAgencyContractOffersSectionViewInterface $offersSection = null,
        ?FreeAgencyTeamFreeAgentsSectionViewInterface $teamFaSection = null,
        ?FreeAgencyOtherFreeAgentsSectionViewInterface $otherFaSection = null
    ) {
        $this->tableRenderer = $tableRenderer ?? new FreeAgencyTableRendererView($commonRepo);
        $this->underContractSection = $underContractSection ?? new FreeAgencyUnderContractSectionView($this->tableRenderer);
        $this->offersSection = $offersSection ?? new FreeAgencyContractOffersSectionView($this->tableRenderer);
        $this->teamFaSection = $teamFaSection ?? new FreeAgencyTeamFreeAgentsSectionView($this->tableRenderer);
        $this->otherFaSection = $otherFaSection ?? new FreeAgencyOtherFreeAgentsSectionView($this->tableRenderer);
    }

    /**
     * @param array{team: Team, season: Season, capMetrics: CapMetrics, allOtherPlayers: list<Player>, teamColorsByTeamId: array<int, array{color1: string, color2: string}>, playersUnderContract: list<array{player: Player, contractAction: 'rookie_option'|'extension'|null}>, unsignedFreeAgents: list<Player>, offerPlayers: list<array{player: Player, offer: array<string, int>}>, cashPlayers: list<array{player: Player, label: string}>} $mainPageData
     */
    public function render(array $mainPageData, ?string $result = null): string
    {
        $team = $mainPageData['team'];
        $season = $mainPageData['season'];
        $capMetrics = $mainPageData['capMetrics'];
        $allOtherPlayers = $mainPageData['allOtherPlayers'];
        $teamColorsByTeamId = $mainPageData['teamColorsByTeamId'];
        $playersUnderContract = $mainPageData['playersUnderContract'];
        $unsignedFreeAgents = $mainPageData['unsignedFreeAgents'];
        $offerPlayers = $mainPageData['offerPlayers'];
        $cashPlayers = $mainPageData['cashPlayers'];

        ob_start();
        echo \UI\AlertRenderer::fromCode($result, [
            'offer_success' => ['class' => 'ibl-alert--success', 'message' => 'Your offer is legal and has been saved.'],
            'deleted' => ['class' => 'ibl-alert--info', 'message' => 'Your offer has been deleted.'],
            'already_signed' => ['class' => 'ibl-alert--warning', 'message' => 'This player was previously signed to a team this Free Agency period.'],
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
            'csrf_error' => ['class' => 'ibl-alert--error', 'message' => 'Your session expired or the form submission was invalid. Please try again.'],
            'error' => ['class' => 'ibl-alert--error', 'message' => 'An unexpected error occurred. Please try again.'],
        ]);
        ?>
<h1 class="ibl-title">Free Agency</h1>
<img src="images/logo/<?= HtmlSanitizer::e($team->teamid) ?>.jpg" alt="Team Logo" class="team-logo-banner">
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->underContractSection->render($team, $season, $capMetrics, $playersUnderContract, $cashPlayers)) ?>
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->offersSection->render($team, $season, $capMetrics, $offerPlayers)) ?>
<div class="mt-6"></div>
<?= HtmlSanitizer::trusted($this->teamFaSection->render($team, $season, $capMetrics, $unsignedFreeAgents)) ?>
<?= HtmlSanitizer::trusted($this->otherFaSection->render($team, $season, $allOtherPlayers, $teamColorsByTeamId)) ?>
        <?php
        return (string) ob_get_clean();
    }
}
