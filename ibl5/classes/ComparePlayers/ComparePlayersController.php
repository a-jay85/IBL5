<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersControllerInterface;
use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;
use ComparePlayers\Contracts\ComparePlayersServiceInterface;
use ComparePlayers\Contracts\ComparePlayersViewInterface;

class ComparePlayersController implements ComparePlayersControllerInterface
{
    public function __construct(
        private ComparePlayersRepositoryInterface $repository,
        private ComparePlayersServiceInterface $service,
        private ComparePlayersViewInterface $view,
        private \Utilities\NukeCompat $nukeCompat,
    ) {}

    public function main(mixed $user): void
    {
        if ($this->nukeCompat->isUser($user)) {
            $this->nukeCompat->cookieDecode($user);
        }
        $this->showPage();
    }

    private function showPage(): void
    {
        $responder = new \Api\Response\HtmlResponder();
        \PageLayout\PageLayout::header();
        $playerNames = $this->repository->getAllPlayerNames();

        if (!isset($_POST['Player1'])) {
            $responder->html($this->view->renderSearchForm($playerNames));
        } else {
            $rawPlayer1 = $_POST['Player1'] ?? null;
            $rawPlayer2 = $_POST['Player2'] ?? null;
            $player1Name = is_string($rawPlayer1) ? trim($rawPlayer1) : '';
            $player2Name = is_string($rawPlayer2) ? trim($rawPlayer2) : '';

            if (strlen($player1Name) > 100 || strlen($player2Name) > 100) {
                $responder->html('<div class="ibl-empty-state"><p class="ibl-empty-state__text">Player names must be 100 characters or less.</p></div>');
                $responder->html($this->view->renderSearchForm($playerNames));
                \PageLayout\PageLayout::footer();
                return;
            }

            $comparison = $this->service->comparePlayers($player1Name, $player2Name);
            if ($comparison !== null) {
                $responder->html($this->view->renderSearchForm($playerNames));
                $responder->html($this->view->renderComparisonResults($comparison));
            } else {
                $responder->html('<div class="ibl-empty-state"><p class="ibl-empty-state__text">One or both players not found. Please check the player names and try again.</p></div>');
                $responder->html($this->view->renderSearchForm($playerNames));
            }
        }

        \PageLayout\PageLayout::footer();
    }
}
