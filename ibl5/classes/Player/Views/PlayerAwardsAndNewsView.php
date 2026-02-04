<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerRepository;
use Player\Contracts\PlayerAwardsAndNewsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * PlayerAwardsAndNewsView - Renders awards and news articles table
 * 
 * Shows player awards and articles mentioning the player.
 * Uses PlayerRepository for all database access.
 * 
 * @see PlayerAwardsAndNewsViewInterface
 */
class PlayerAwardsAndNewsView implements PlayerAwardsAndNewsViewInterface
{
    private PlayerRepository $repository;

    public function __construct(PlayerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        return '';
    }

    /**
     * @see PlayerAwardsAndNewsViewInterface::renderAwardsAndNews()
     */
    public function renderAwardsAndNews(string $playerName): string
    {
        $awards = $this->repository->getAwards($playerName);
        $articles = $this->repository->getPlayerNews($playerName);

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td class="player-table-header">AWARDS</td>
    </tr>
        <?php
        /** @var list<array{year: int, name: string, Award: string, prim: int}> $awards */
        foreach ($awards as $award) {
            /** @var string $year */
            $year = HtmlSanitizer::safeHtmlOutput((string) $award['year']);
            /** @var string $type */
            $type = HtmlSanitizer::safeHtmlOutput($award['Award']);
            ?>
    <tr>
        <td><?= $year ?> <?= $type ?></td>
    </tr>
            <?php
        }
        ?>
    <tr>
        <td class="player-table-header">ARTICLES MENTIONING THIS PLAYER</td>
    </tr>
    <tr>
        <td>
            <small>
            <?php
            /** @var list<array{sid: int, title: string, time: string}> $articles */
            foreach ($articles as $article): ?>
                <?php
                $sid = $article['sid'];
                /** @var string $title */
                $title = HtmlSanitizer::safeHtmlOutput($article['title']);
                /** @var string $time */
                $time = HtmlSanitizer::safeHtmlOutput($article['time']);
                ?>
                * <a href="modules.php?name=News&file=article&sid=<?= $sid ?>&mode=&order=0&thold=0"><?= $title ?></a> (<?= $time ?>)<br>
            <?php endforeach; ?>
            </small>
        </td>
    </tr>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
