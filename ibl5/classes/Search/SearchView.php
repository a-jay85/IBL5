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
 * @see SearchViewInterface
 */
class SearchView implements SearchViewInterface
{
    /**
     * @see SearchViewInterface::render()
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
            $title = _SEARCHUSERS;
        } else {
            $title = _SEARCHIN . ' ' . $topicText;
        }

        return '<h2 class="ibl-title">' . HtmlSanitizer::safeHtmlOutput($title) . '</h2>';
    }

    /**
     * Render the search form with filter dropdowns.
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
        $output .= '<button type="submit" class="ibl-search__btn">' . HtmlSanitizer::safeHtmlOutput(_SEARCH) . '</button>';
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
        $output .= '<span class="search-form__types-label">' . HtmlSanitizer::safeHtmlOutput(_SEARCHON) . '</span>';
        $output .= $this->renderTypeRadio('stories', _SSTORIES, $type);

        if ($data['articleComm']) {
            $output .= $this->renderTypeRadio('comments', _SCOMMENTS, $type);
        }

        $output .= $this->renderTypeRadio('users', _SUSERS, $type);
        $output .= '</div>';

        $output .= '</form>';

        return $output;
    }

    /**
     * Render the topic filter dropdown.
     */
    private function renderTopicSelect(array $topics, int $selectedTopic): string
    {
        $output = '<select name="topic" class="search-form__select">';
        $output .= '<option value="">' . HtmlSanitizer::safeHtmlOutput(_ALLTOPICS) . '</option>';

        foreach ($topics as $topic) {
            $topicId = (int) $topic['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($topic['topicText']);
            $selected = ($topicId === $selectedTopic) ? ' selected' : '';
            $output .= '<option value="' . $topicId . '"' . $selected . '>' . $topicText . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the category filter dropdown.
     */
    private function renderCategorySelect(array $categories, int $selectedCategory): string
    {
        $output = '<select name="category" class="search-form__select">';
        $output .= '<option value="0">' . HtmlSanitizer::safeHtmlOutput(_ARTICLES) . '</option>';

        foreach ($categories as $cat) {
            $catId = (int) $cat['catId'];
            $title = HtmlSanitizer::safeHtmlOutput($cat['title']);
            $selected = ($catId === $selectedCategory) ? ' selected' : '';
            $output .= '<option value="' . $catId . '"' . $selected . '>' . $title . '</option>';
        }

        $output .= '</select>';
        return $output;
    }

    /**
     * Render the author filter dropdown.
     */
    private function renderAuthorSelect(array $authors, string $selectedAuthor): string
    {
        $output = '<select name="author" class="search-form__select">';
        $output .= '<option value="">' . HtmlSanitizer::safeHtmlOutput(_ALLAUTHORS) . '</option>';

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
        $options = [
            0 => _ALL,
            7 => '1 ' . _WEEK,
            14 => '2 ' . _WEEKS,
            30 => '1 ' . _MONTH,
            60 => '2 ' . _MONTHS,
            90 => '3 ' . _MONTHS,
        ];

        $output = '<select name="days" class="search-form__select">';

        foreach ($options as $value => $label) {
            $selected = ($value === $selectedDays) ? ' selected' : '';
            $output .= '<option value="' . $value . '"' . $selected . '>' . HtmlSanitizer::safeHtmlOutput($label) . '</option>';
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

        return '<label class="search-form__type" for="' . $id . '">
            <input type="radio" name="type" value="' . $value . '" id="' . $id . '"' . $checked . '>
            <span class="search-form__type-label">' . HtmlSanitizer::safeHtmlOutput($label) . '</span>
        </label>';
    }

    /**
     * Render an error message.
     */
    private function renderError(string $error): string
    {
        return '<div class="ibl-alert ibl-alert--error search-error">' . HtmlSanitizer::safeHtmlOutput($error) . '</div>';
    }

    /**
     * Render the results section based on search type.
     */
    private function renderResults(array $data): string
    {
        $results = $data['results'];
        $type = $data['type'];

        if ($results === null || $data['query'] === '') {
            return '';
        }

        $output = '<div class="search-results">';
        $output .= '<h3 class="search-results__heading">' . HtmlSanitizer::safeHtmlOutput(_SEARCHRESULTS) . '</h3>';

        if (count($results) === 0) {
            $output .= '<div class="ibl-empty-state"><p class="ibl-empty-state__text">' . HtmlSanitizer::safeHtmlOutput(_NOMATCHES) . '</p></div>';
            $output .= '</div>';
            return $output;
        }

        if ($type === 'comments') {
            $output .= $this->renderCommentResults($results);
        } elseif ($type === 'users') {
            $output .= $this->renderUserResults($results, $data['isAdmin'], $data['adminFile']);
        } else {
            $output .= $this->renderStoryResults($results, $data['isAdmin'], $data['adminFile']);
        }

        $output .= $this->renderPagination($data);
        $output .= '</div>';

        return $output;
    }

    /**
     * Render story search results.
     *
     * @param array $results Story results
     * @param bool $isAdmin Whether the current user is an admin
     * @param string $adminFile Admin file path
     */
    private function renderStoryResults(array $results, bool $isAdmin, string $adminFile): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $sid = (int) $result['sid'];
            $title = HtmlSanitizer::safeHtmlOutput($result['title']);
            $aid = HtmlSanitizer::safeHtmlOutput($result['aid']);
            $informant = HtmlSanitizer::safeHtmlOutput($result['informant']);
            $time = HtmlSanitizer::safeHtmlOutput(formatTimestamp($result['time']));
            $comments = (int) $result['comments'];
            $topicId = (int) $result['topicId'];
            $topicText = HtmlSanitizer::safeHtmlOutput($result['topicText']);
            $delay = min($index * 40, 400);

            $output .= '<div class="search-result" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<a href="modules.php?name=News&amp;file=article&amp;sid=' . $sid . '" class="search-result__title">' . $title . '</a>';
            $output .= '</div>';

            $output .= '<div class="search-result__meta">';

            if ($informant !== '') {
                $output .= '<span class="search-result__meta-item">' . HtmlSanitizer::safeHtmlOutput(_CONTRIBUTEDBY) . ' <a href="modules.php?name=YourAccount&amp;op=userinfo&amp;username=' . $informant . '">' . $informant . '</a></span>';
            }

            $output .= '<span class="search-result__meta-item">' . HtmlSanitizer::safeHtmlOutput(_POSTEDBY) . ' ' . $aid . ' ' . HtmlSanitizer::safeHtmlOutput(_ON) . ' ' . $time . '</span>';

            if ($topicText !== '') {
                $output .= '<span class="search-result__meta-item">' . HtmlSanitizer::safeHtmlOutput(_TOPIC) . ': <a href="modules.php?name=Search&amp;query=&amp;topic=' . $topicId . '">' . $topicText . '</a></span>';
            }

            $output .= '<span class="search-result__meta-item">';
            if ($comments === 0) {
                $output .= HtmlSanitizer::safeHtmlOutput(_NOCOMMENTS);
            } elseif ($comments === 1) {
                $output .= $comments . ' ' . HtmlSanitizer::safeHtmlOutput(_UCOMMENT);
            } else {
                $output .= $comments . ' ' . HtmlSanitizer::safeHtmlOutput(_UCOMMENTS);
            }
            $output .= '</span>';

            if ($isAdmin) {
                $safeAdminFile = HtmlSanitizer::safeHtmlOutput($adminFile);
                $output .= '<span class="search-result__admin">';
                $output .= '<a href="' . $safeAdminFile . '.php?op=EditStory&amp;sid=' . $sid . '">' . HtmlSanitizer::safeHtmlOutput(_EDIT) . '</a>';
                $output .= '<a href="' . $safeAdminFile . '.php?op=RemoveStory&amp;sid=' . $sid . '">' . HtmlSanitizer::safeHtmlOutput(_DELETE) . '</a>';
                $output .= '</span>';
            }

            $output .= '</div></div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render comment search results.
     */
    private function renderCommentResults(array $results): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $tid = (int) $result['tid'];
            $sid = (int) $result['sid'];
            $subject = HtmlSanitizer::safeHtmlOutput($result['subject']);
            $date = HtmlSanitizer::safeHtmlOutput(formatTimestamp($result['date']));
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);
            $articleTitle = HtmlSanitizer::safeHtmlOutput($result['articleTitle']);
            $replyCount = (int) $result['replyCount'];
            $delay = min($index * 40, 400);

            $output .= '<div class="search-result" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<a href="modules.php?name=News&amp;file=article&amp;thold=-1&amp;mode=flat&amp;order=1&amp;sid=' . $sid . '#' . $tid . '" class="search-result__title">' . $subject . '</a>';
            $output .= '</div>';

            $output .= '<div class="search-result__meta">';

            if ($name !== '') {
                $output .= '<span class="search-result__meta-item">' . HtmlSanitizer::safeHtmlOutput(_POSTEDBY) . ' <a href="modules.php?name=YourAccount&amp;op=userinfo&amp;username=' . $name . '">' . $name . '</a> ' . HtmlSanitizer::safeHtmlOutput(_ON) . ' ' . $date . '</span>';
            }

            if ($articleTitle !== '') {
                $output .= '<span class="search-result__meta-item">' . HtmlSanitizer::safeHtmlOutput(_ATTACHART) . ': ' . $articleTitle . '</span>';
            }

            $replyLabel = ($replyCount === 1) ? _SREPLY : _SREPLIES;
            $output .= '<span class="search-result__meta-item">' . $replyCount . ' ' . HtmlSanitizer::safeHtmlOutput($replyLabel) . '</span>';

            $output .= '</div></div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render user search results.
     */
    private function renderUserResults(array $results, bool $isAdmin, string $adminFile): string
    {
        $output = '<div class="search-results__list">';

        foreach ($results as $index => $result) {
            $userId = (int) $result['userId'];
            $username = HtmlSanitizer::safeHtmlOutput($result['username']);
            $name = HtmlSanitizer::safeHtmlOutput($result['name']);
            $delay = min($index * 40, 400);

            $displayName = ($name !== '') ? $name : HtmlSanitizer::safeHtmlOutput(_NONAME);

            $output .= '<div class="search-result search-result--compact" style="animation-delay: ' . $delay . 'ms">';
            $output .= '<div class="search-result__header">';
            $output .= '<a href="modules.php?name=YourAccount&amp;op=userinfo&amp;username=' . $username . '" class="search-result__title">' . $username . '</a>';
            $output .= '<span class="search-result__subtitle">' . $displayName . '</span>';
            $output .= '</div>';

            if ($isAdmin) {
                $safeAdminFile = HtmlSanitizer::safeHtmlOutput($adminFile);
                $output .= '<div class="search-result__meta">';
                $output .= '<span class="search-result__admin">';
                $output .= '<a href="' . $safeAdminFile . '.php?chng_uid=' . $userId . '&amp;op=modifyUser">' . HtmlSanitizer::safeHtmlOutput(_EDIT) . '</a>';
                $output .= '<a href="' . $safeAdminFile . '.php?op=delUser&amp;chng_uid=' . $userId . '">' . HtmlSanitizer::safeHtmlOutput(_DELETE) . '</a>';
                $output .= '</span></div>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render pagination links.
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
            $output .= '&larr; ' . HtmlSanitizer::safeHtmlOutput(_PREVMATCHES);
            $output .= '</a>';
        }

        if ($hasMore) {
            $next = $min + $offset;
            $output .= '<a href="modules.php?name=Search&amp;author=' . $author . '&amp;topic=' . $topic . '&amp;min=' . $next . '&amp;query=' . $query . '&amp;type=' . $type . '&amp;category=' . $category . '" class="search-pagination__link search-pagination__link--next">';
            $output .= HtmlSanitizer::safeHtmlOutput(_NEXTMATCHES) . ' &rarr;';
            $output .= '</a>';
        }

        $output .= '</div>';
        return $output;
    }

}
