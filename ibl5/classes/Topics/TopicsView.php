<?php

declare(strict_types=1);

namespace Topics;

use Topics\Contracts\TopicsViewInterface;
use Utilities\HtmlSanitizer;

// PHP-Nuke language constants - defined at runtime by the CMS
if (!defined('_ACTIVETOPICS')) {
    define('_ACTIVETOPICS', 'Active Topics');
}
if (!defined('_CLICK2LIST')) {
    define('_CLICK2LIST', 'Click on a topic to list associated articles');
}
if (!defined('_SEARCH')) {
    define('_SEARCH', 'Search');
}
if (!defined('_TOTNEWS')) {
    define('_TOTNEWS', 'articles');
}
if (!defined('_TOTREADS')) {
    define('_TOTREADS', 'reads');
}
if (!defined('_MORE')) {
    define('_MORE', 'More');
}
if (!defined('_NONEWSYET')) {
    define('_NONEWSYET', 'No news yet');
}

/**
 * View class for rendering the Topics listing page.
 *
 * Renders a responsive grid of topic cards, each showing the topic image,
 * article count, total reads, and a list of recent articles.
 *
 * @phpstan-type TopicData array{topicId: int, topicName: string, topicImage: string, topicText: string, storyCount: int, totalReads: int, recentArticles: array<int, array{sid: int, title: string, catId: int, catTitle: string}>}
 *
 * @see TopicsViewInterface
 */
class TopicsView implements TopicsViewInterface
{
    /**
     * @see TopicsViewInterface::render()
     *
     * @param array<int, TopicData> $topics
     */
    public function render(array $topics, string $themePath): string
    {
        if ($topics === []) {
            return $this->renderEmptyState();
        }

        $output = $this->renderPageHeader();
        $output .= $this->renderSearchForm();
        $output .= $this->renderTopicsGrid($topics, $themePath);

        return $output;
    }

    /**
     * Render the page header.
     */
    private function renderPageHeader(): string
    {
        $activeTopics = HtmlSanitizer::safeHtmlOutput(_ACTIVETOPICS);
        $click2list = HtmlSanitizer::safeHtmlOutput(_CLICK2LIST);

        return '<div class="topics-page">
    <h2 class="ibl-title">' . $activeTopics . '</h2>
    <p class="topics-page__subtitle">' . $click2list . '</p>';
    }

    /**
     * Render the search form.
     */
    private function renderSearchForm(): string
    {
        $search = HtmlSanitizer::safeHtmlOutput(_SEARCH);

        return '<form action="modules.php?name=Search" method="post" class="topics-search">
        <div class="ibl-search">
            <svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="query" class="ibl-search__input" placeholder="Search articles...">
            <button type="submit" class="ibl-search__btn">' . $search . '</button>
        </div>
    </form>';
    }

    /**
     * Render the topics grid container and all topic cards.
     *
     * @param array<int, TopicData> $topics Topic data
     * @param string $themePath Theme image path prefix
     */
    private function renderTopicsGrid(array $topics, string $themePath): string
    {
        $output = '<div class="topics-grid">';

        foreach ($topics as $index => $topic) {
            $output .= $this->renderTopicCard($topic, $themePath, $index);
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Render a single topic card.
     *
     * @param TopicData $topic Topic data
     * @param string $themePath Theme image path prefix
     * @param int $index Card index for stagger animation
     */
    private function renderTopicCard(array $topic, string $themePath, int $index): string
    {
        $topicId = $topic['topicId'];
        $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
        $storyCount = $topic['storyCount'];
        $totalReads = $topic['totalReads'];
        $imagePath = HtmlSanitizer::safeHtmlOutput($themePath . $topic['topicImage']);
        $delay = min($index * 30, 600);

        $totNews = HtmlSanitizer::safeHtmlOutput(_TOTNEWS);
        $totReads = HtmlSanitizer::safeHtmlOutput(_TOTREADS);

        $output = '<div class="topic-card" style="animation-delay: ' . $delay . 'ms">';
        $output .= '<div class="topic-card__header">';
        $output .= '<a href="modules.php?name=News&amp;topic=' . $topicId . '" class="topic-card__image-link">';
        $output .= '<img src="' . $imagePath . '" alt="" class="topic-card__image" loading="lazy">';
        $output .= '</a>';
        $output .= '<div class="topic-card__meta">';
        $output .= '<h3 class="topic-card__title"><a href="modules.php?name=News&amp;topic=' . $topicId . '">' . $topicText . '</a></h3>';
        $output .= '<div class="topic-card__stats">';
        $output .= '<span class="topic-card__stat"><strong>' . number_format($storyCount) . '</strong> ' . $totNews . '</span>';
        $output .= '<span class="topic-card__stat-separator"></span>';
        $output .= '<span class="topic-card__stat"><strong>' . number_format($totalReads) . '</strong> ' . $totReads . '</span>';
        $output .= '</div></div></div>';

        $output .= '<div class="topic-card__body">';

        if ($storyCount > 0) {
            $output .= $this->renderArticleList($topic['recentArticles']);

            if ($storyCount > 10) {
                $more = HtmlSanitizer::safeHtmlOutput(_MORE);
                $output .= '<div class="topic-card__more">';
                $output .= '<a href="modules.php?name=News&amp;new_topic=' . $topicId . '">' . $more . ' &rarr;</a>';
                $output .= '</div>';
            }
        } else {
            $noNewsYet = HtmlSanitizer::safeHtmlOutput(_NONEWSYET);
            $output .= '<p class="topic-card__empty">' . $noNewsYet . '</p>';
        }

        $output .= '</div></div>';

        return $output;
    }

    /**
     * Render the list of recent articles within a topic card.
     *
     * @param array<int, array{sid: int, title: string, catId: int, catTitle: string}> $articles
     */
    private function renderArticleList(array $articles): string
    {
        $output = '<ul class="topic-card__articles">';

        foreach ($articles as $article) {
            $sid = $article['sid'];
            $title = HtmlSanitizer::safeHtmlOutput($article['title']);
            $catId = $article['catId'];
            $catTitle = HtmlSanitizer::safeHtmlOutput($article['catTitle']);

            $output .= '<li class="topic-card__article">';

            if ($catId > 0 && $catTitle !== '') {
                $output .= '<a href="modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=' . $catId . '" class="topic-card__cat">' . $catTitle . '</a>';
            }

            $output .= '<a href="modules.php?name=News&amp;file=article&amp;sid=' . $sid . '" class="topic-card__article-link">' . $title . '</a>';
            $output .= '</li>';
        }

        $output .= '</ul>';

        return $output;
    }

    /**
     * Render the empty state when no topics exist.
     */
    private function renderEmptyState(): string
    {
        $noNewsYet = HtmlSanitizer::safeHtmlOutput(_NONEWSYET);

        return '<div class="ibl-empty-state">
            <svg class="ibl-empty-state__icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V9a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2h-2z"/></svg>
            <p class="ibl-empty-state__text">' . $noNewsYet . '</p>
        </div>';
    }
}
