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
if (!defined('_ALLTOPICS')) {
    define('_ALLTOPICS', 'All Topics');
}
if (!defined('_ARTICLES')) {
    define('_ARTICLES', 'All Categories');
}
if (!defined('_ALLAUTHORS')) {
    define('_ALLAUTHORS', 'All Authors');
}
if (!defined('_ALL')) {
    define('_ALL', 'Any Date');
}
if (!defined('_WEEK')) {
    define('_WEEK', 'Week');
}
if (!defined('_WEEKS')) {
    define('_WEEKS', 'Weeks');
}
if (!defined('_MONTH')) {
    define('_MONTH', 'Month');
}
if (!defined('_MONTHS')) {
    define('_MONTHS', 'Months');
}
if (!defined('_SEARCHON')) {
    define('_SEARCHON', 'Search on:');
}
if (!defined('_SSTORIES')) {
    define('_SSTORIES', 'Stories');
}
if (!defined('_SCOMMENTS')) {
    define('_SCOMMENTS', 'Comments');
}
if (!defined('_SUSERS')) {
    define('_SUSERS', 'Users');
}

/**
 * View class for rendering the Topics listing page.
 *
 * Renders a responsive grid of topic cards, each showing the topic image,
 * article count, total reads, and a list of recent articles.
 *
 * @phpstan-type TopicData array{topicId: int, topicName: string, topicImage: string, topicText: string, storyCount: int, totalReads: int, recentArticles: array<int, array{sid: int, title: string, catId: int, catTitle: string}>}
 * @phpstan-import-type SearchFilterData from Contracts\TopicsViewInterface
 *
 * @see TopicsViewInterface
 */
class TopicsView implements TopicsViewInterface
{
    /**
     * @see TopicsViewInterface::render()
     *
     * @param array<int, TopicData> $topics
     * @param SearchFilterData $searchFilters
     */
    public function render(array $topics, string $themePath, array $searchFilters): string
    {
        if ($topics === []) {
            return $this->renderEmptyState();
        }

        $output = $this->renderPageHeader();
        $output .= $this->renderSearchForm($searchFilters);
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
     * Render the full search form with filter dropdowns and type radio buttons.
     *
     * @param SearchFilterData $searchFilters
     */
    private function renderSearchForm(array $searchFilters): string
    {
        $output = '<form action="modules.php?name=Search" method="post" class="topics-search search-form">';

        // Search input row
        $output .= '<div class="search-form__input-row">';
        $output .= '<div class="ibl-search search-form__search-bar">';
        $output .= '<svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
        $output .= '<input type="text" name="query" class="ibl-search__input" placeholder="Search articles...">';
        $safeSearch = HtmlSanitizer::safeHtmlOutput(_SEARCH);
        $output .= '<button type="submit" class="ibl-search__btn">' . $safeSearch . '</button>';
        $output .= '</div></div>';

        // Filter row
        $output .= '<div class="search-form__filters">';
        $output .= $this->renderTopicSelect($searchFilters['topics']);
        $output .= $this->renderCategorySelect($searchFilters['categories']);
        $output .= $this->renderAuthorSelect($searchFilters['authors']);
        $output .= $this->renderDaysSelect();
        $output .= '</div>';

        // Search type radio buttons
        $output .= '<div class="search-form__types">';
        $safeSearchOn = HtmlSanitizer::safeHtmlOutput(_SEARCHON);
        $output .= '<span class="search-form__types-label">' . $safeSearchOn . '</span>';
        /** @var string $storiesLabel */
        $storiesLabel = _SSTORIES;
        $output .= $this->renderTypeRadio('stories', $storiesLabel, 'stories');

        if ($searchFilters['articleComm']) {
            /** @var string $commentsLabel */
            $commentsLabel = _SCOMMENTS;
            $output .= $this->renderTypeRadio('comments', $commentsLabel, 'stories');
        }

        /** @var string $usersLabel */
        $usersLabel = _SUSERS;
        $output .= $this->renderTypeRadio('users', $usersLabel, 'stories');
        $output .= '</div>';

        $output .= '</form>';

        return $output;
    }

    /**
     * Render the topic filter dropdown.
     *
     * @param list<array{topicId: int, topicText: string}> $topics
     */
    private function renderTopicSelect(array $topics): string
    {
        $output = '<select name="topic" class="search-form__select">';
        $safeAllTopics = HtmlSanitizer::safeHtmlOutput(_ALLTOPICS);
        $output .= '<option value="">' . $safeAllTopics . '</option>';

        foreach ($topics as $topic) {
            $topicId = $topic['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
            $output .= '<option value="' . $topicId . '">' . $topicText . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the category filter dropdown.
     *
     * @param list<array{catId: int, title: string}> $categories
     */
    private function renderCategorySelect(array $categories): string
    {
        $output = '<select name="category" class="search-form__select">';
        $safeArticles = HtmlSanitizer::safeHtmlOutput(_ARTICLES);
        $output .= '<option value="0">' . $safeArticles . '</option>';

        foreach ($categories as $cat) {
            $catId = $cat['catId'];
            $title = HtmlSanitizer::safeHtmlOutput($cat['title']);
            $output .= '<option value="' . $catId . '">' . $title . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the author filter dropdown.
     *
     * @param list<string> $authors
     */
    private function renderAuthorSelect(array $authors): string
    {
        $output = '<select name="author" class="search-form__select">';
        $safeAllAuthors = HtmlSanitizer::safeHtmlOutput(_ALLAUTHORS);
        $output .= '<option value="">' . $safeAllAuthors . '</option>';

        foreach ($authors as $authorName) {
            $safe = HtmlSanitizer::safeHtmlOutput($authorName);
            $output .= '<option value="' . $safe . '">' . $safe . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the date range filter dropdown.
     */
    private function renderDaysSelect(): string
    {
        /** @var string $allLabel */
        $allLabel = _ALL;
        /** @var string $weekLabel */
        $weekLabel = _WEEK;
        /** @var string $weeksLabel */
        $weeksLabel = _WEEKS;
        /** @var string $monthLabel */
        $monthLabel = _MONTH;
        /** @var string $monthsLabel */
        $monthsLabel = _MONTHS;

        /** @var array<int, string> $options */
        $options = [
            0 => $allLabel,
            7 => '1 ' . $weekLabel,
            14 => '2 ' . $weeksLabel,
            30 => '1 ' . $monthLabel,
            60 => '2 ' . $monthsLabel,
            90 => '3 ' . $monthsLabel,
        ];

        $output = '<select name="days" class="search-form__select">';

        foreach ($options as $value => $label) {
            $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
            $output .= '<option value="' . $value . '">' . $safeLabel . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render a search type radio button.
     */
    private function renderTypeRadio(string $value, string $label, string $selectedType): string
    {
        $checked = ($value === $selectedType) ? ' checked' : '';
        $id = 'search-type-' . $value;

        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        return '<label class="search-form__type" for="' . $id . '">
            <input type="radio" name="type" value="' . $value . '" id="' . $id . '"' . $checked . '>
            <span class="search-form__type-label">' . $safeLabel . '</span>
        </label>';
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
        $output .= '<span class="topic-card__stat"><strong>' . \BasketballStats\StatsFormatter::formatTotal($storyCount) . '</strong> ' . $totNews . '</span>';
        $output .= '<span class="topic-card__stat-separator"></span>';
        $output .= '<span class="topic-card__stat"><strong>' . \BasketballStats\StatsFormatter::formatTotal($totalReads) . '</strong> ' . $totReads . '</span>';
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
