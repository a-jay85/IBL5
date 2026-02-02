<?php

declare(strict_types=1);

namespace Topics;

use Topics\Contracts\TopicsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering the Topics listing page.
 *
 * Renders a responsive grid of topic cards, each showing the topic image,
 * article count, total reads, and a list of recent articles.
 *
 * @see TopicsViewInterface
 */
class TopicsView implements TopicsViewInterface
{
    /**
     * @see TopicsViewInterface::render()
     */
    public function render(array $topics, string $themePath): string
    {
        if (count($topics) === 0) {
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
        return '<div class="topics-page">
    <h2 class="ibl-title">' . HtmlSanitizer::safeHtmlOutput(_ACTIVETOPICS) . '</h2>
    <p class="topics-page__subtitle">' . HtmlSanitizer::safeHtmlOutput(_CLICK2LIST) . '</p>';
    }

    /**
     * Render the search form.
     */
    private function renderSearchForm(): string
    {
        return '<form action="modules.php?name=Search" method="post" class="topics-search">
        <div class="ibl-search">
            <svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="query" class="ibl-search__input" placeholder="Search articles...">
            <button type="submit" class="ibl-search__btn">' . HtmlSanitizer::safeHtmlOutput(_SEARCH) . '</button>
        </div>
    </form>';
    }

    /**
     * Render the topics grid container and all topic cards.
     *
     * @param array<int, array> $topics Topic data
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
     * @param array{
     *     topicId: int,
     *     topicName: string,
     *     topicImage: string,
     *     topicText: string,
     *     storyCount: int,
     *     totalReads: int,
     *     recentArticles: array
     * } $topic Topic data
     * @param string $themePath Theme image path prefix
     * @param int $index Card index for stagger animation
     */
    private function renderTopicCard(array $topic, string $themePath, int $index): string
    {
        $topicId = (int) $topic['topicId'];
        $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
        $topicImage = HtmlSanitizer::safeHtmlOutput($topic['topicImage']);
        $storyCount = (int) $topic['storyCount'];
        $totalReads = (int) $topic['totalReads'];
        $imagePath = HtmlSanitizer::safeHtmlOutput($themePath . $topic['topicImage']);
        $delay = min($index * 30, 600);

        $output = '<div class="topic-card" style="animation-delay: ' . $delay . 'ms">';
        $output .= '<div class="topic-card__header">';
        $output .= '<a href="modules.php?name=News&amp;topic=' . $topicId . '" class="topic-card__image-link">';
        $output .= '<img src="' . $imagePath . '" alt="" class="topic-card__image" loading="lazy">';
        $output .= '</a>';
        $output .= '<div class="topic-card__meta">';
        $output .= '<h3 class="topic-card__title"><a href="modules.php?name=News&amp;topic=' . $topicId . '">' . $topicText . '</a></h3>';
        $output .= '<div class="topic-card__stats">';
        $output .= '<span class="topic-card__stat"><strong>' . number_format($storyCount) . '</strong> ' . HtmlSanitizer::safeHtmlOutput(_TOTNEWS) . '</span>';
        $output .= '<span class="topic-card__stat-separator"></span>';
        $output .= '<span class="topic-card__stat"><strong>' . number_format($totalReads) . '</strong> ' . HtmlSanitizer::safeHtmlOutput(_TOTREADS) . '</span>';
        $output .= '</div></div></div>';

        $output .= '<div class="topic-card__body">';

        if ($storyCount > 0) {
            $output .= $this->renderArticleList($topic['recentArticles']);

            if ($storyCount > 10) {
                $output .= '<div class="topic-card__more">';
                $output .= '<a href="modules.php?name=News&amp;new_topic=' . $topicId . '">' . HtmlSanitizer::safeHtmlOutput(_MORE) . ' &rarr;</a>';
                $output .= '</div>';
            }
        } else {
            $output .= '<p class="topic-card__empty">' . HtmlSanitizer::safeHtmlOutput(_NONEWSYET) . '</p>';
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
            $sid = (int) $article['sid'];
            $title = HtmlSanitizer::safeHtmlOutput($article['title']);
            $catId = (int) $article['catId'];
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
        return '<div class="ibl-empty-state">
            <svg class="ibl-empty-state__icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V9a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2h-2z"/></svg>
            <p class="ibl-empty-state__text">' . HtmlSanitizer::safeHtmlOutput(_NONEWSYET) . '</p>
        </div>';
    }
}
