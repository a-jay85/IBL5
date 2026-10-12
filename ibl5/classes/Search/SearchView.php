<?php

declare(strict_types=1);

namespace Search;

use Search\Contracts\SearchViewInterface;
use Security\HtmlSanitizer;

// PHP-Nuke Search language constants used by this view (defined here if not already defined)
if (!defined('_ALLTOPICS')) {
    define('_ALLTOPICS', 'All Topics');
}
if (!defined('_NOCOMMENTS')) {
    define('_NOCOMMENTS', 'No Comments');
}
if (!defined('_UCOMMENT')) {
    define('_UCOMMENT', 'Comment');
}
if (!defined('_SEARCHUSERS')) {
    define('_SEARCHUSERS', "Search in User's Database");
}
if (!defined('_SEARCHIN')) {
    define('_SEARCHIN', 'Search in');
}
if (!defined('_ARTICLES')) {
    define('_ARTICLES', 'Articles');
}
if (!defined('_ALLAUTHORS')) {
    define('_ALLAUTHORS', 'All Authors');
}
if (!defined('_ALL')) {
    define('_ALL', 'All');
}
if (!defined('_WEEK')) {
    define('_WEEK', 'week');
}
if (!defined('_WEEKS')) {
    define('_WEEKS', 'weeks');
}
if (!defined('_MONTH')) {
    define('_MONTH', 'month');
}
if (!defined('_MONTHS')) {
    define('_MONTHS', 'months');
}
if (!defined('_SEARCHON')) {
    define('_SEARCHON', 'Search on:');
}
if (!defined('_SSTORIES')) {
    define('_SSTORIES', 'Stories');
}
if (!defined('_SUSERS')) {
    define('_SUSERS', 'Users');
}
if (!defined('_NOMATCHES')) {
    define('_NOMATCHES', 'No matches found to your query');
}
if (!defined('_PREVMATCHES')) {
    define('_PREVMATCHES', 'previous matches');
}
if (!defined('_NEXTMATCHES')) {
    define('_NEXTMATCHES', 'next matches');
}
if (!defined('_NONAME')) {
    define('_NONAME', 'No name entered');
}
if (!defined('_SCOMMENTS')) {
    define('_SCOMMENTS', 'Comments');
}
if (!defined('_SEARCHRESULTS')) {
    define('_SEARCHRESULTS', 'Search Results');
}
if (!defined('_CONTRIBUTEDBY')) {
    define('_CONTRIBUTEDBY', 'Contributed by');
}
if (!defined('_UCOMMENTS')) {
    define('_UCOMMENTS', 'Comments');
}
if (!defined('_SREPLY')) {
    define('_SREPLY', 'Reply');
}
if (!defined('_SREPLIES')) {
    define('_SREPLIES', 'Replies');
}
if (!defined('_ATTACHART')) {
    define('_ATTACHART', 'Attached to Article');
}

/**
 * View class for rendering the Search page.
 *
 * Renders the search form with filter dropdowns, search results for
 * stories/comments/users, pagination, and external search links.
 *
 * @phpstan-import-type SearchPageData from Contracts\SearchViewInterface
 * @phpstan-import-type StoryResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type CommentResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type UserResult from Contracts\SearchRepositoryInterface
 * @phpstan-import-type TopicRow from Contracts\SearchRepositoryInterface
 * @phpstan-import-type CategoryRow from Contracts\SearchRepositoryInterface
 *
 * @see SearchViewInterface
 */
class SearchView implements SearchViewInterface
{
    private \Utilities\NukeCompat $nukeCompat;

    public function __construct(?\Utilities\NukeCompat $nukeCompat = null)
    {
        $this->nukeCompat = $nukeCompat ?? new \Utilities\NukeCompat();
    }

    /**
     * @see SearchViewInterface::render()
     * @param SearchPageData $data
     */
    public function render(array $data): string
    {
        ob_start();
        ?><div class="search-page"><?= HtmlSanitizer::trusted($this->renderPageHeader($data['topicText'], $data['type'])) ?><?= HtmlSanitizer::trusted($this->renderSearchForm($data)) ?><?php
        if ($data['error'] !== '') {
            ?><?= HtmlSanitizer::trusted($this->renderError($data['error'])) ?><?php
        }
        if ($data['results'] !== null) {
            ?><?= HtmlSanitizer::trusted($this->renderResults($data)) ?><?php
        }
        ?></div><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the page header with contextual title.
     */
    private function renderPageHeader(string $topicText, string $type): string
    {
        if ($type === 'users') {
            /** @var string $title */
            $title = _SEARCHUSERS;
        } else {
            /** @var string $searchIn */
            $searchIn = _SEARCHIN;
            $title = $searchIn . ' ' . $topicText;
        }

        ob_start();
        ?><h1 class="ibl-title"><?= HtmlSanitizer::safeHtmlOutput($title) ?></h1><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the search form with filter dropdowns.
     *
     * @param SearchPageData $data
     */
    private function renderSearchForm(array $data): string
    {
        $type = $data['type'];
        /** @var string $storiesLabel */
        $storiesLabel = _SSTORIES;
        /** @var string $usersLabel */
        $usersLabel = _SUSERS;

        ob_start();
        ?><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="<?= HtmlSanitizer::safeHtmlOutput($data['query']) ?>" placeholder="Search..."><button type="submit" class="ibl-search__btn"><?= HtmlSanitizer::safeHtmlOutput(_SEARCH) ?></button></div></div><div class="search-form__filters"><?= HtmlSanitizer::trusted($this->renderTopicSelect($data['topics'], $data['topic'])) ?><?= HtmlSanitizer::trusted($this->renderCategorySelect($data['categories'], $data['category'])) ?><?= HtmlSanitizer::trusted($this->renderAuthorSelect($data['authors'], $data['author'])) ?><?= HtmlSanitizer::trusted($this->renderDaysSelect($data['days'])) ?><?= HtmlSanitizer::trusted($this->renderPresetSelect($data['preset'])) ?></div><div class="search-form__types"><span class="search-form__types-label"><?= HtmlSanitizer::safeHtmlOutput(_SEARCHON) ?></span><?= HtmlSanitizer::trusted($this->renderTypeRadio('stories', $storiesLabel, $type)) ?><?php
        if ($data['articleComm']) {
            /** @var string $commentsLabel */
            $commentsLabel = _SCOMMENTS;
            ?><?= HtmlSanitizer::trusted($this->renderTypeRadio('comments', $commentsLabel, $type)) ?><?php
        }
        ?><?= HtmlSanitizer::trusted($this->renderTypeRadio('users', $usersLabel, $type)) ?></div></form><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the topic filter dropdown.
     *
     * @param list<TopicRow> $topics
     */
    private function renderTopicSelect(array $topics, int $selectedTopic): string
    {
        ob_start();
        ?><select name="topic" aria-label="Topic" class="search-form__select"><option value=""><?= HtmlSanitizer::safeHtmlOutput(_ALLTOPICS) ?></option><?php
        foreach ($topics as $topic) {
            $topicId = $topic['topicId'];
            $selected = ($topicId === $selectedTopic) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::safeHtmlOutput($topicId) ?>"<?= HtmlSanitizer::safeHtmlOutput($selected) ?>><?= HtmlSanitizer::safeHtmlOutput($topic['topicText']) ?></option><?php
        }
        ?></select><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the category filter dropdown.
     *
     * @param list<CategoryRow> $categories
     */
    private function renderCategorySelect(array $categories, int $selectedCategory): string
    {
        ob_start();
        ?><select name="category" aria-label="Category" class="search-form__select"><option value="0"><?= HtmlSanitizer::safeHtmlOutput(_ARTICLES) ?></option><?php
        foreach ($categories as $cat) {
            $catId = $cat['catId'];
            $selected = ($catId === $selectedCategory) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::safeHtmlOutput($catId) ?>"<?= HtmlSanitizer::safeHtmlOutput($selected) ?>><?= HtmlSanitizer::safeHtmlOutput($cat['title']) ?></option><?php
        }
        ?></select><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the author filter dropdown.
     *
     * @param list<string> $authors
     */
    private function renderAuthorSelect(array $authors, string $selectedAuthor): string
    {
        ob_start();
        ?><select name="author" aria-label="Author" class="search-form__select"><option value=""><?= HtmlSanitizer::safeHtmlOutput(_ALLAUTHORS) ?></option><?php
        foreach ($authors as $authorName) {
            $selected = ($authorName === $selectedAuthor) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::safeHtmlOutput($authorName) ?>"<?= HtmlSanitizer::safeHtmlOutput($selected) ?>><?= HtmlSanitizer::safeHtmlOutput($authorName) ?></option><?php
        }
        ?></select><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the date range filter dropdown.
     */
    private function renderDaysSelect(int $selectedDays): string
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

        ob_start();
        ?><select name="days" aria-label="Date range" class="search-form__select"><?php
        foreach ($options as $value => $label) {
            $selected = ($value === $selectedDays) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::safeHtmlOutput($value) ?>"<?= HtmlSanitizer::safeHtmlOutput($selected) ?>><?= HtmlSanitizer::safeHtmlOutput($label) ?></option><?php
        }
        ?></select><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the preset filter select.
     *
     * Options come from the PRESET_OPTIONS constant, never from request input;
     * $selectedPreset is only compared, never echoed.
     */
    private function renderPresetSelect(string $selectedPreset): string
    {
        ob_start();
        ?><select name="preset" aria-label="Preset" class="search-form__select"><?php
        foreach (SearchViewInterface::PRESET_OPTIONS as $value => $label) {
            $selected = ($value === $selectedPreset) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::safeHtmlOutput($value) ?>"<?= HtmlSanitizer::safeHtmlOutput($selected) ?>><?= HtmlSanitizer::safeHtmlOutput($label) ?></option><?php
        }
        ?></select><?php
        return (string) ob_get_clean();
    }

    /**
     * Render a search type radio button.
     */
    private function renderTypeRadio(string $value, string $label, string $selectedType): string
    {
        $checked = ($value === $selectedType || ($selectedType === '' && $value === 'stories')) ? ' checked' : '';
        $id = 'search-type-' . $value;

        ob_start();
        ?><label class="search-form__type" for="<?= HtmlSanitizer::safeHtmlOutput($id) ?>">
            <input type="radio" name="type" value="<?= HtmlSanitizer::safeHtmlOutput($value) ?>" id="<?= HtmlSanitizer::safeHtmlOutput($id) ?>"<?= HtmlSanitizer::safeHtmlOutput($checked) ?>>
            <span class="search-form__type-label"><?= HtmlSanitizer::safeHtmlOutput($label) ?></span>
        </label><?php
        return (string) ob_get_clean();
    }

    /**
     * Render an error message.
     */
    private function renderError(string $error): string
    {
        ob_start();
        ?><div class="ibl-alert ibl-alert--error search-error"><?= HtmlSanitizer::safeHtmlOutput($error) ?></div><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the results section based on search type.
     *
     * @param SearchPageData $data
     */
    private function renderResults(array $data): string
    {
        $results = $data['results'];
        $type = $data['type'];

        if ($results === null || ($data['query'] === '' && $data['preset'] === '')) {
            return '';
        }

        ob_start();
        ?><div class="search-results"><h3 class="search-results__heading"><?= HtmlSanitizer::safeHtmlOutput(_SEARCHRESULTS) ?></h3><?php
        if (count($results) === 0) {
            ?><div class="ibl-empty-state"><p class="ibl-empty-state__text"><?= HtmlSanitizer::safeHtmlOutput(_NOMATCHES) ?></p></div></div><?php
            return (string) ob_get_clean();
        }
        if ($type === 'comments') {
            /** @var list<CommentResult> $commentResults */
            $commentResults = $results;
            ?><?= HtmlSanitizer::trusted($this->renderCommentResults($commentResults)) ?><?php
        } elseif ($type === 'users') {
            /** @var list<UserResult> $userResults */
            $userResults = $results;
            ?><?= HtmlSanitizer::trusted($this->renderUserResults($userResults)) ?><?php
        } else {
            /** @var list<StoryResult> $storyResults */
            $storyResults = $results;
            ?><?= HtmlSanitizer::trusted($this->renderStoryResults($storyResults)) ?><?php
        }
        ?><?= HtmlSanitizer::trusted($this->renderPagination($data)) ?></div><?php
        return (string) ob_get_clean();
    }

    /**
     * Wrap pre-built per-item card bodies in the shared results-list scaffold.
     *
     * @param list<string> $itemBodies Already-escaped inner HTML for each card body
     * @param string       $itemModifier Extra class appended to "search-result"
     *                                    ('' for stories/comments, ' search-result--compact' for users)
     */
    private function renderResultList(array $itemBodies, string $itemModifier = ''): string
    {
        ob_start();
        ?><div class="search-results__list"><?php foreach ($itemBodies as $index => $itemBody) {
            $delay = min($index * 40, 400);
        ?><div class="search-result<?= HtmlSanitizer::safeHtmlOutput($itemModifier) ?>" style="--anim-delay: <?= (int) $delay ?>ms"><?= HtmlSanitizer::trusted($itemBody) ?></div><?php } ?></div><?php
        return (string) ob_get_clean();
    }

    /**
     * Render story search results.
     *
     * @param list<StoryResult> $results Story results
     */
    private function renderStoryResults(array $results): string
    {
        $itemBodies = [];
        foreach ($results as $result) {
            $sid = $result['sid'];
            $informant = $result['informant'];
            $time = $this->nukeCompat->formatLocalTime($result['time']);
            $comments = $result['comments'];
            $topicId = $result['topicId'];
            $topicText = $result['topicText'];

            ob_start();
            ?><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;sid=<?= (int) $sid ?>" class="search-result__title"><?= HtmlSanitizer::safeHtmlOutput($result['title']) ?></a></div><div class="search-result__meta"><?php
            if ($informant !== '') {
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::safeHtmlOutput(_CONTRIBUTEDBY) ?> <?= HtmlSanitizer::safeHtmlOutput($informant) ?></span><?php
            }
            ?><span class="search-result__meta-item"><?= HtmlSanitizer::safeHtmlOutput(_POSTEDBY) ?> <?= HtmlSanitizer::safeHtmlOutput($result['aid']) ?> <?= HtmlSanitizer::safeHtmlOutput(_ON) ?> <?= HtmlSanitizer::trusted($time) ?></span><?php
            if ($topicText !== '') {
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::safeHtmlOutput(_TOPIC) ?>: <a href="modules.php?name=Search&amp;query=&amp;topic=<?= (int) $topicId ?>"><?= HtmlSanitizer::safeHtmlOutput($topicText) ?></a></span><?php
            }
            ?><span class="search-result__meta-item"><?php
            if ($comments === 0) {
                ?><?= HtmlSanitizer::safeHtmlOutput(_NOCOMMENTS) ?><?php
            } elseif ($comments === 1) {
                ?><?= HtmlSanitizer::safeHtmlOutput($comments) ?> <?= HtmlSanitizer::safeHtmlOutput(_UCOMMENT) ?><?php
            } else {
                ?><?= HtmlSanitizer::safeHtmlOutput($comments) ?> <?= HtmlSanitizer::safeHtmlOutput(_UCOMMENTS) ?><?php
            }
            ?></span></div><?php
            $itemBodies[] = (string) ob_get_clean();
        }
        return $this->renderResultList($itemBodies);
    }

    /**
     * Render comment search results.
     *
     * @param list<CommentResult> $results
     */
    private function renderCommentResults(array $results): string
    {
        $itemBodies = [];
        foreach ($results as $result) {
            $teamid = $result['teamid'];
            $sid = $result['sid'];
            $date = $this->nukeCompat->formatLocalTime($result['date']);
            $name = $result['name'];
            $articleTitle = $result['articleTitle'];
            $replyCount = $result['replyCount'];

            ob_start();
            ?><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=<?= (int) $sid ?>#<?= (int) $teamid ?>" class="search-result__title"><?= HtmlSanitizer::safeHtmlOutput($result['subject']) ?></a></div><div class="search-result__meta"><?php
            if ($name !== '') {
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::safeHtmlOutput(_POSTEDBY) ?> <?= HtmlSanitizer::safeHtmlOutput($name) ?> <?= HtmlSanitizer::safeHtmlOutput(_ON) ?> <?= HtmlSanitizer::trusted($date) ?></span><?php
            }
            if ($articleTitle !== '') {
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::safeHtmlOutput(_ATTACHART) ?>: <?= HtmlSanitizer::safeHtmlOutput($articleTitle) ?></span><?php
            }
            /** @var string $replyLabel */
            $replyLabel = ($replyCount === 1) ? _SREPLY : _SREPLIES;
            ?><span class="search-result__meta-item"><?= (int) $replyCount ?> <?= HtmlSanitizer::safeHtmlOutput($replyLabel) ?></span></div><?php
            $itemBodies[] = (string) ob_get_clean();
        }
        return $this->renderResultList($itemBodies);
    }

    /**
     * Render user search results.
     *
     * @param list<UserResult> $results
     */
    private function renderUserResults(array $results): string
    {
        $itemBodies = [];
        foreach ($results as $result) {
            $name = $result['name'];
            $displayName = ($name !== '') ? $name : _NONAME;

            ob_start();
            ?><div class="search-result__header"><span class="search-result__title"><?= HtmlSanitizer::safeHtmlOutput($result['username']) ?></span><span class="search-result__subtitle"><?= HtmlSanitizer::safeHtmlOutput($displayName) ?></span></div><?php
            $itemBodies[] = (string) ob_get_clean();
        }
        return $this->renderResultList($itemBodies, ' search-result--compact');
    }

    /**
     * Render pagination links.
     *
     * @param SearchPageData $data
     */
    private function renderPagination(array $data): string
    {
        $query = urlencode($data['query']);
        $type = urlencode($data['type']);
        $author = urlencode($data['author']);
        $preset = urlencode($data['preset']);
        $topic = $data['topic'];
        $category = $data['category'];
        $min = $data['min'];
        $offset = $data['offset'];
        $hasMore = $data['hasMore'];

        $hasPrev = $min > 0;

        if (!$hasPrev && !$hasMore) {
            return '';
        }

        ob_start();
        ?><div class="search-pagination"><?php
        if ($hasPrev) {
            $prev = $min - $offset;
            ?><a href="modules.php?name=Search&amp;author=<?= HtmlSanitizer::safeHtmlOutput($author) ?>&amp;topic=<?= HtmlSanitizer::safeHtmlOutput($topic) ?>&amp;min=<?= HtmlSanitizer::safeHtmlOutput($prev) ?>&amp;query=<?= HtmlSanitizer::safeHtmlOutput($query) ?>&amp;type=<?= HtmlSanitizer::safeHtmlOutput($type) ?>&amp;category=<?= HtmlSanitizer::safeHtmlOutput($category) ?>&amp;preset=<?= HtmlSanitizer::safeHtmlOutput($preset) ?>" class="search-pagination__link search-pagination__link--prev">&larr; <?= HtmlSanitizer::safeHtmlOutput(_PREVMATCHES) ?></a><?php
        }
        if ($hasMore) {
            $next = $min + $offset;
            ?><a href="modules.php?name=Search&amp;author=<?= HtmlSanitizer::safeHtmlOutput($author) ?>&amp;topic=<?= HtmlSanitizer::safeHtmlOutput($topic) ?>&amp;min=<?= HtmlSanitizer::safeHtmlOutput($next) ?>&amp;query=<?= HtmlSanitizer::safeHtmlOutput($query) ?>&amp;type=<?= HtmlSanitizer::safeHtmlOutput($type) ?>&amp;category=<?= HtmlSanitizer::safeHtmlOutput($category) ?>&amp;preset=<?= HtmlSanitizer::safeHtmlOutput($preset) ?>" class="search-pagination__link search-pagination__link--next"><?= HtmlSanitizer::safeHtmlOutput(_NEXTMATCHES) ?> &rarr;</a><?php
        }
        ?></div><?php
        return (string) ob_get_clean();
    }

}
