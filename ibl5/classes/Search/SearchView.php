<?php

declare(strict_types=1);

namespace Search;

use Search\Contracts\SearchViewInterface;
use Utilities\HtmlSanitizer;

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
    /**
     * @see SearchViewInterface::render()
     * @param SearchPageData $data
     */
    public function render(array $data): string
    {
        $output = '<div class="search-page">';
        $output .= $this->renderPageHeader($data['topicText'], $data['type']);
        $output .= $this->renderSearchForm($data);

        if ($data['error'] !== '') {
            $output .= $this->renderError($data['error']);
        }

        if ($data['results'] !== null) {
            $output .= $this->renderResults($data);
        }

        $output .= '</div>';

        return $output;
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
        return '<h2 class="ibl-title">' . $safeTitle . '</h2>';
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

        $output = '<form action="modules.php?name=Search" method="post" class="search-form">';

        // Search input row
        $output .= '<div class="search-form__input-row">';
        $output .= '<div class="ibl-search search-form__search-bar">';
        $output .= '<svg class="ibl-search__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
        $output .= '<input type="text" name="query" class="ibl-search__input" value="' . $query . '" placeholder="Search...">';
        $safeSearch = HtmlSanitizer::safeHtmlOutput(_SEARCH);
        $output .= '<button type="submit" class="ibl-search__btn">' . $safeSearch . '</button>';
        $output .= '</div></div>';

        // Filter row
        $output .= '<div class="search-form__filters">';
        $output .= $this->renderTopicSelect($data['topics'], $data['topic']);
        $output .= $this->renderCategorySelect($data['categories'], $data['category']);
        $output .= $this->renderAuthorSelect($data['authors'], $data['author']);
        $output .= $this->renderDaysSelect($data['days']);
        $output .= '</div>';

        // Search type radio buttons
        $output .= '<div class="search-form__types">';
        $safeSearchOn = HtmlSanitizer::safeHtmlOutput(_SEARCHON);
        $output .= '<span class="search-form__types-label">' . $safeSearchOn . '</span>';
        /** @var string $storiesLabel */
        $storiesLabel = _SSTORIES;
        $output .= $this->renderTypeRadio('stories', $storiesLabel, $type);

        if ($data['articleComm']) {
            /** @var string $commentsLabel */
            $commentsLabel = _SCOMMENTS;
            $output .= $this->renderTypeRadio('comments', $commentsLabel, $type);
        }

        /** @var string $usersLabel */
        $usersLabel = _SUSERS;
        $output .= $this->renderTypeRadio('users', $usersLabel, $type);
        $output .= '</div>';

        $output .= '</form>';

        return $output;
    }

    /**
     * Render the topic filter dropdown.
     *
     * @param list<TopicRow> $topics
     */
    private function renderTopicSelect(array $topics, int $selectedTopic): string
    {
        $output = '<select name="topic" class="search-form__select">';
        $safeAllTopics = HtmlSanitizer::safeHtmlOutput(_ALLTOPICS);
        $output .= '<option value="">' . $safeAllTopics . '</option>';

        foreach ($topics as $topic) {
            $topicId = $topic['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
            $selected = ($topicId === $selectedTopic) ? ' selected' : '';
            $output .= '<option value="' . $topicId . '"' . $selected . '>' . $topicText . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the category filter dropdown.
     *
     * @param list<CategoryRow> $categories
     */
    private function renderCategorySelect(array $categories, int $selectedCategory): string
    {
        $output = '<select name="category" class="search-form__select">';
        $safeArticles = HtmlSanitizer::safeHtmlOutput(_ARTICLES);
        $output .= '<option value="0">' . $safeArticles . '</option>';

        foreach ($categories as $cat) {
            $catId = $cat['catId'];
            $title = HtmlSanitizer::safeHtmlOutput($cat['title']);
            $selected = ($catId === $selectedCategory) ? ' selected' : '';
            $output .= '<option value="' . $catId . '"' . $selected . '>' . $title . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the author filter dropdown.
     *
     * @param list<string> $authors
     */
    private function renderAuthorSelect(array $authors, string $selectedAuthor): string
    {
        $output = '<select name="author" class="search-form__select">';
        $safeAllAuthors = HtmlSanitizer::safeHtmlOutput(_ALLAUTHORS);
        $output .= '<option value="">' . $safeAllAuthors . '</option>';

        foreach ($authors as $authorName) {
            $safe = HtmlSanitizer::safeHtmlOutput($authorName);
            $selected = ($authorName === $selectedAuthor) ? ' selected' : '';
            $output .= '<option value="' . $safe . '"' . $selected . '>' . $safe . '</option>';
        }

        $output .= '</select>';
        return $output;
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

        $output = '<select name="days" class="search-form__select">';

        foreach ($options as $value => $label) {
            $selected = ($value === $selectedDays) ? ' selected' : '';
            $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
            $output .= '<option value="' . $value . '"' . $selected . '>' . $safeLabel . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render a search type radio button.
     */
    private function renderTypeRadio(string $value, string $label, string $selectedType): string
    {
        $checked = ($value === $selectedType || ($selectedType === '' && $value === 'stories')) ? ' checked' : '';
        $id = 'search-type-' . $value;

        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        return '<label class="search-form__type" for="' . $id . '">
            <input type="radio" name="type" value="' . $value . '" id="' . $id . '"' . $checked . '>
            <span class="search-form__type-label">' . $safeLabel . '</span>
        </label>';
    }

    /**
     * Render an error message.
     */
    private function renderError(string $error): string
    {
        $safeError = HtmlSanitizer::safeHtmlOutput($error);
        return '<div class="ibl-alert ibl-alert--error search-error">' . $safeError . '</div>';
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

        $output = '<div class="search-results">';
        $safeSearchResults = HtmlSanitizer::safeHtmlOutput(_SEARCHRESULTS);
        $output .= '<h3 class="search-results__heading">' . $safeSearchResults . '</h3>';

        if (count($results) === 0) {
            $safeNoMatches = HtmlSanitizer::safeHtmlOutput(_NOMATCHES);
            $output .= '<div class="ibl-empty-state"><p class="ibl-empty-state__text">' . $safeNoMatches . '</p></div>';
            $output .= '</div>';
            return $output;
        }

        if ($type === 'comments') {
            /** @var list<CommentResult> $commentResults */
            $commentResults = $results;
            $output .= $this->renderCommentResults($commentResults);
        } elseif ($type === 'users') {
            /** @var list<UserResult> $userResults */
            $userResults = $results;
            $output .= $this->renderUserResults($userResults);
        } else {
            /** @var list<StoryResult> $storyResults */
            $storyResults = $results;
            $output .= $this->renderStoryResults($storyResults);
        }

        $output .= $this->renderPagination($data);
        $output .= '</div>';

        return $output;
    }

    /**
     * Render story search results.
     *
     * @param list<StoryResult> $results Story results
     */
    private function renderStoryResults(array $results): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $sid = $result['sid'];
            $title = HtmlSanitizer::safeHtmlOutput($result['title']);
            $aid = HtmlSanitizer::safeHtmlOutput($result['aid']);
            $informant = HtmlSanitizer::safeHtmlOutput($result['informant']);
            $time = HtmlSanitizer::safeHtmlOutput(formatTimestamp($result['time']));
            $comments = $result['comments'];
            $topicId = $result['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($result['topicText']);
            $delay = min($index * 40, 400);

            $output .= '<div class="search-result" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<a href="modules.php?name=News&amp;file=article&amp;sid=' . $sid . '" class="search-result__title">' . $title . '</a>';
            $output .= '</div>';

            $output .= '<div class="search-result__meta">';

            if ($informant !== '') {
                $safeContributedBy = HtmlSanitizer::safeHtmlOutput(_CONTRIBUTEDBY);
                $output .= '<span class="search-result__meta-item">' . $safeContributedBy . ' ' . $informant . '</span>';
            }

            $safePostedBy = HtmlSanitizer::safeHtmlOutput(_POSTEDBY);
            $safeOn = HtmlSanitizer::safeHtmlOutput(_ON);
            $output .= '<span class="search-result__meta-item">' . $safePostedBy . ' ' . $aid . ' ' . $safeOn . ' ' . $time . '</span>';

            if ($topicText !== '') {
                $safeTopic = HtmlSanitizer::safeHtmlOutput(_TOPIC);
                $output .= '<span class="search-result__meta-item">' . $safeTopic . ': <a href="modules.php?name=Search&amp;query=&amp;topic=' . $topicId . '">' . $topicText . '</a></span>';
            }

            $output .= '<span class="search-result__meta-item">';
            if ($comments === 0) {
                $safeNoComments = HtmlSanitizer::safeHtmlOutput(_NOCOMMENTS);
                $output .= $safeNoComments;
            } elseif ($comments === 1) {
                $safeUComment = HtmlSanitizer::safeHtmlOutput(_UCOMMENT);
                $output .= $comments . ' ' . $safeUComment;
            } else {
                $safeUComments = HtmlSanitizer::safeHtmlOutput(_UCOMMENTS);
                $output .= $comments . ' ' . $safeUComments;
            }
            $output .= '</span>';

            $output .= '</div></div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render comment search results.
     *
     * @param list<CommentResult> $results
     */
    private function renderCommentResults(array $results): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $tid = $result['tid'];
            $sid = $result['sid'];
            $subject = HtmlSanitizer::safeHtmlOutput($result['subject']);
            $date = HtmlSanitizer::safeHtmlOutput(formatTimestamp($result['date']));
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);
            $articleTitle = HtmlSanitizer::safeHtmlOutput($result['articleTitle']);
            $replyCount = $result['replyCount'];
            $delay = min($index * 40, 400);

            $output .= '<div class="search-result" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=' . $sid . '#' . $tid . '" class="search-result__title">' . $subject . '</a>';
            $output .= '</div>';

            $output .= '<div class="search-result__meta">';

            if ($name !== '') {
                $safePostedBy = HtmlSanitizer::safeHtmlOutput(_POSTEDBY);
                $safeOn = HtmlSanitizer::safeHtmlOutput(_ON);
                $output .= '<span class="search-result__meta-item">' . $safePostedBy . ' ' . $name . ' ' . $safeOn . ' ' . $date . '</span>';
            }

            if ($articleTitle !== '') {
                $safeAttachArt = HtmlSanitizer::safeHtmlOutput(_ATTACHART);
                $output .= '<span class="search-result__meta-item">' . $safeAttachArt . ': ' . $articleTitle . '</span>';
            }

            /** @var string $replyLabel */
            $replyLabel = ($replyCount === 1) ? _SREPLY : _SREPLIES;
            $safeReplyLabel = HtmlSanitizer::safeHtmlOutput($replyLabel);
            $output .= '<span class="search-result__meta-item">' . $replyCount . ' ' . $safeReplyLabel . '</span>';

            $output .= '</div></div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render user search results.
     *
     * @param list<UserResult> $results
     */
    private function renderUserResults(array $results): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $userId = $result['userId'];
            $username = HtmlSanitizer::safeHtmlOutput($result['username']);
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);
            $delay = min($index * 40, 400);

            $safeNoName = HtmlSanitizer::safeHtmlOutput(_NONAME);
            $displayName = ($name !== '') ? $name : $safeNoName;

            $output .= '<div class="search-result search-result--compact" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<span class="search-result__title">' . $username . '</span>';
            $output .= '<span class="search-result__subtitle">' . $displayName . '</span>';
            $output .= '</div>';

            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
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

        $output = '<div class="search-pagination">';

        if ($hasPrev) {
            $prev = $min - $offset;
            $output .= '<a href="modules.php?name=Search&amp;author=' . $author . '&amp;topic=' . $topic . '&amp;min=' . $prev . '&amp;query=' . $query . '&amp;type=' . $type . '&amp;category=' . $category . '" class="search-pagination__link search-pagination__link--prev">';
            $safePrevMatches = HtmlSanitizer::safeHtmlOutput(_PREVMATCHES);
            $output .= '&larr; ' . $safePrevMatches;
            $output .= '</a>';
        }

        if ($hasMore) {
            $next = $min + $offset;
            $output .= '<a href="modules.php?name=Search&amp;author=' . $author . '&amp;topic=' . $topic . '&amp;min=' . $next . '&amp;query=' . $query . '&amp;type=' . $type . '&amp;category=' . $category . '" class="search-pagination__link search-pagination__link--next">';
            $safeNextMatches = HtmlSanitizer::safeHtmlOutput(_NEXTMATCHES);
            $output .= $safeNextMatches . ' &rarr;';
            $output .= '</a>';
        }

        $output .= '</div>';
        return $output;
    }

}
