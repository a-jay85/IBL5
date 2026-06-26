<?php

declare(strict_types=1);

namespace Search;

use Search\Contracts\SearchViewInterface;
use Security\HtmlSanitizer;

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

        $safeTitle = HtmlSanitizer::safeHtmlOutput($title);
        ob_start();
        ?><h1 class="ibl-title"><?= HtmlSanitizer::trusted($safeTitle) ?></h1><?php
        return (string) ob_get_clean();
    }

    /**
     * Render the search form with filter dropdowns.
     *
     * @param SearchPageData $data
     */
    private function renderSearchForm(array $data): string
    {
        $query = HtmlSanitizer::safeHtmlOutput($data['query']);
        $type = $data['type'];
        $safeSearch = HtmlSanitizer::safeHtmlOutput(_SEARCH);
        $safeSearchOn = HtmlSanitizer::safeHtmlOutput(_SEARCHON);
        /** @var string $storiesLabel */
        $storiesLabel = _SSTORIES;
        /** @var string $usersLabel */
        $usersLabel = _SUSERS;

        ob_start();
        ?><form action="modules.php?name=Search" method="post" class="search-form"><div class="search-form__input-row"><div class="ibl-search search-form__search-bar"><svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="query" class="ibl-search__input" value="<?= HtmlSanitizer::trusted($query) ?>" placeholder="Search..."><button type="submit" class="ibl-search__btn"><?= HtmlSanitizer::trusted($safeSearch) ?></button></div></div><div class="search-form__filters"><?= HtmlSanitizer::trusted($this->renderTopicSelect($data['topics'], $data['topic'])) ?><?= HtmlSanitizer::trusted($this->renderCategorySelect($data['categories'], $data['category'])) ?><?= HtmlSanitizer::trusted($this->renderAuthorSelect($data['authors'], $data['author'])) ?><?= HtmlSanitizer::trusted($this->renderDaysSelect($data['days'])) ?></div><div class="search-form__types"><span class="search-form__types-label"><?= HtmlSanitizer::trusted($safeSearchOn) ?></span><?= HtmlSanitizer::trusted($this->renderTypeRadio('stories', $storiesLabel, $type)) ?><?php
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
        $safeAllTopics = HtmlSanitizer::safeHtmlOutput(_ALLTOPICS);
        ob_start();
        ?><select name="topic" aria-label="Topic" class="search-form__select"><option value=""><?= HtmlSanitizer::trusted($safeAllTopics) ?></option><?php
        foreach ($topics as $topic) {
            $topicId = $topic['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
            $selected = ($topicId === $selectedTopic) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::trusted((string) $topicId) ?>"<?= HtmlSanitizer::trusted($selected) ?>><?= HtmlSanitizer::trusted($topicText) ?></option><?php
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
        $safeArticles = HtmlSanitizer::safeHtmlOutput(_ARTICLES);
        ob_start();
        ?><select name="category" aria-label="Category" class="search-form__select"><option value="0"><?= HtmlSanitizer::trusted($safeArticles) ?></option><?php
        foreach ($categories as $cat) {
            $catId = $cat['catId'];
            $title = HtmlSanitizer::safeHtmlOutput($cat['title']);
            $selected = ($catId === $selectedCategory) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::trusted((string) $catId) ?>"<?= HtmlSanitizer::trusted($selected) ?>><?= HtmlSanitizer::trusted($title) ?></option><?php
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
        $safeAllAuthors = HtmlSanitizer::safeHtmlOutput(_ALLAUTHORS);
        ob_start();
        ?><select name="author" aria-label="Author" class="search-form__select"><option value=""><?= HtmlSanitizer::trusted($safeAllAuthors) ?></option><?php
        foreach ($authors as $authorName) {
            $safe = HtmlSanitizer::safeHtmlOutput($authorName);
            $selected = ($authorName === $selectedAuthor) ? ' selected' : '';
            ?><option value="<?= HtmlSanitizer::trusted($safe) ?>"<?= HtmlSanitizer::trusted($selected) ?>><?= HtmlSanitizer::trusted($safe) ?></option><?php
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
            $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
            ?><option value="<?= HtmlSanitizer::trusted((string) $value) ?>"<?= HtmlSanitizer::trusted($selected) ?>><?= HtmlSanitizer::trusted($safeLabel) ?></option><?php
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

        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        ob_start();
        ?><label class="search-form__type" for="<?= HtmlSanitizer::trusted($id) ?>">
            <input type="radio" name="type" value="<?= HtmlSanitizer::trusted($value) ?>" id="<?= HtmlSanitizer::trusted($id) ?>"<?= HtmlSanitizer::trusted($checked) ?>>
            <span class="search-form__type-label"><?= HtmlSanitizer::trusted($safeLabel) ?></span>
        </label><?php
        return (string) ob_get_clean();
    }

    /**
     * Render an error message.
     */
    private function renderError(string $error): string
    {
        $safeError = HtmlSanitizer::safeHtmlOutput($error);
        ob_start();
        ?><div class="ibl-alert ibl-alert--error search-error"><?= HtmlSanitizer::trusted($safeError) ?></div><?php
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

        if ($results === null || $data['query'] === '') {
            return '';
        }

        $safeSearchResults = HtmlSanitizer::safeHtmlOutput(_SEARCHRESULTS);
        ob_start();
        ?><div class="search-results"><h3 class="search-results__heading"><?= HtmlSanitizer::trusted($safeSearchResults) ?></h3><?php
        if (count($results) === 0) {
            $safeNoMatches = HtmlSanitizer::safeHtmlOutput(_NOMATCHES);
            ?><div class="ibl-empty-state"><p class="ibl-empty-state__text"><?= HtmlSanitizer::trusted($safeNoMatches) ?></p></div></div><?php
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
        ?><div class="search-result<?= HtmlSanitizer::trusted($itemModifier) ?>" style="--anim-delay: <?= (int) $delay ?>ms"><?= HtmlSanitizer::trusted($itemBody) ?></div><?php } ?></div><?php
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
            $title = HtmlSanitizer::safeHtmlOutput($result['title']);
            $aid = HtmlSanitizer::safeHtmlOutput($result['aid']);
            $informant = HtmlSanitizer::safeHtmlOutput($result['informant']);
            $time = $this->nukeCompat->formatLocalTime($result['time']);
            $comments = $result['comments'];
            $topicId = $result['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($result['topicText']);

            ob_start();
            ?><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;sid=<?= (int) $sid ?>" class="search-result__title"><?= HtmlSanitizer::trusted($title) ?></a></div><div class="search-result__meta"><?php
            if ($informant !== '') {
                $safeContributedBy = HtmlSanitizer::safeHtmlOutput(_CONTRIBUTEDBY);
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::trusted($safeContributedBy) ?> <?= HtmlSanitizer::trusted($informant) ?></span><?php
            }
            $safePostedBy = HtmlSanitizer::safeHtmlOutput(_POSTEDBY);
            $safeOn = HtmlSanitizer::safeHtmlOutput(_ON);
            ?><span class="search-result__meta-item"><?= HtmlSanitizer::trusted($safePostedBy) ?> <?= HtmlSanitizer::trusted($aid) ?> <?= HtmlSanitizer::trusted($safeOn) ?> <?= HtmlSanitizer::trusted($time) ?></span><?php
            if ($topicText !== '') {
                $safeTopic = HtmlSanitizer::safeHtmlOutput(_TOPIC);
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::trusted($safeTopic) ?>: <a href="modules.php?name=Search&amp;query=&amp;topic=<?= (int) $topicId ?>"><?= HtmlSanitizer::trusted($topicText) ?></a></span><?php
            }
            ?><span class="search-result__meta-item"><?php
            if ($comments === 0) {
                $safeNoComments = HtmlSanitizer::safeHtmlOutput(_NOCOMMENTS);
                ?><?= HtmlSanitizer::trusted($safeNoComments) ?><?php
            } elseif ($comments === 1) {
                $safeUComment = HtmlSanitizer::safeHtmlOutput(_UCOMMENT);
                ?><?= HtmlSanitizer::trusted((string) $comments) ?> <?= HtmlSanitizer::trusted($safeUComment) ?><?php
            } else {
                $safeUComments = HtmlSanitizer::safeHtmlOutput(_UCOMMENTS);
                ?><?= HtmlSanitizer::trusted((string) $comments) ?> <?= HtmlSanitizer::trusted($safeUComments) ?><?php
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
            $subject = HtmlSanitizer::safeHtmlOutput($result['subject']);
            $date = $this->nukeCompat->formatLocalTime($result['date']);
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);
            $articleTitle = HtmlSanitizer::safeHtmlOutput($result['articleTitle']);
            $replyCount = $result['replyCount'];

            ob_start();
            ?><div class="search-result__header"><a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=<?= (int) $sid ?>#<?= (int) $teamid ?>" class="search-result__title"><?= HtmlSanitizer::trusted($subject) ?></a></div><div class="search-result__meta"><?php
            if ($name !== '') {
                $safePostedBy = HtmlSanitizer::safeHtmlOutput(_POSTEDBY);
                $safeOn = HtmlSanitizer::safeHtmlOutput(_ON);
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::trusted($safePostedBy) ?> <?= HtmlSanitizer::trusted($name) ?> <?= HtmlSanitizer::trusted($safeOn) ?> <?= HtmlSanitizer::trusted($date) ?></span><?php
            }
            if ($articleTitle !== '') {
                $safeAttachArt = HtmlSanitizer::safeHtmlOutput(_ATTACHART);
                ?><span class="search-result__meta-item"><?= HtmlSanitizer::trusted($safeAttachArt) ?>: <?= HtmlSanitizer::trusted($articleTitle) ?></span><?php
            }
            /** @var string $replyLabel */
            $replyLabel = ($replyCount === 1) ? _SREPLY : _SREPLIES;
            $safeReplyLabel = HtmlSanitizer::safeHtmlOutput($replyLabel);
            ?><span class="search-result__meta-item"><?= (int) $replyCount ?> <?= HtmlSanitizer::trusted($safeReplyLabel) ?></span></div><?php
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
            $username = HtmlSanitizer::safeHtmlOutput($result['username']);
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);

            $safeNoName = HtmlSanitizer::safeHtmlOutput(_NONAME);
            $displayName = ($name !== '') ? $name : $safeNoName;

            ob_start();
            ?><div class="search-result__header"><span class="search-result__title"><?= HtmlSanitizer::trusted($username) ?></span><span class="search-result__subtitle"><?= HtmlSanitizer::trusted($displayName) ?></span></div><?php
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
            $safePrevMatches = HtmlSanitizer::safeHtmlOutput(_PREVMATCHES);
            ?><a href="modules.php?name=Search&amp;author=<?= HtmlSanitizer::trusted($author) ?>&amp;topic=<?= HtmlSanitizer::trusted((string) $topic) ?>&amp;min=<?= HtmlSanitizer::trusted((string) $prev) ?>&amp;query=<?= HtmlSanitizer::trusted($query) ?>&amp;type=<?= HtmlSanitizer::trusted($type) ?>&amp;category=<?= HtmlSanitizer::trusted((string) $category) ?>" class="search-pagination__link search-pagination__link--prev">&larr; <?= HtmlSanitizer::trusted($safePrevMatches) ?></a><?php
        }
        if ($hasMore) {
            $next = $min + $offset;
            $safeNextMatches = HtmlSanitizer::safeHtmlOutput(_NEXTMATCHES);
            ?><a href="modules.php?name=Search&amp;author=<?= HtmlSanitizer::trusted($author) ?>&amp;topic=<?= HtmlSanitizer::trusted((string) $topic) ?>&amp;min=<?= HtmlSanitizer::trusted((string) $next) ?>&amp;query=<?= HtmlSanitizer::trusted($query) ?>&amp;type=<?= HtmlSanitizer::trusted($type) ?>&amp;category=<?= HtmlSanitizer::trusted((string) $category) ?>" class="search-pagination__link search-pagination__link--next"><?= HtmlSanitizer::trusted($safeNextMatches) ?> &rarr;</a><?php
        }
        ?></div><?php
        return (string) ob_get_clean();
    }

}
