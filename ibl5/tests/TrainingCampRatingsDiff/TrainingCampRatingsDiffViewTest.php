<?php

declare(strict_types=1);

namespace Tests\TrainingCampRatingsDiff;

use PHPUnit\Framework\TestCase;
use TrainingCampRatingsDiff\Contracts\TrainingCampRatingsDiffViewInterface;
use TrainingCampRatingsDiff\RatingDelta;
use TrainingCampRatingsDiff\RatingRow;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffService;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffView;

class TrainingCampRatingsDiffViewTest extends TestCase
{
    private TrainingCampRatingsDiffView $view;

    protected function setUp(): void
    {
        $this->view = new TrainingCampRatingsDiffView();
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Builds a RatingRow with all deltas set to a constant value.
     * When $isNew is true, before/delta are null (new-player pattern).
     */
    private function buildRatingRow(
        int $pid,
        string $name,
        int $maxAbs = 0,
        bool $isNew = false,
        string $pos = 'PG',
        int $teamid = 1,
        ?string $teamName = 'Test Team',
    ): RatingRow {
        /** @var array<string, RatingDelta> $deltas */
        $deltas = [];

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if ($isNew) {
                $deltas[$field] = new RatingDelta($field, null, 50, null);
            } else {
                $after  = 50 + $maxAbs;
                $before = 50;
                $delta  = $maxAbs;
                $deltas[$field] = new RatingDelta($field, $before, $after, $delta);
            }
        }

        $sumAbs = $isNew ? 0 : ($maxAbs * count(TrainingCampRatingsDiffService::RATED_FIELDS));

        return new RatingRow(
            pid: $pid,
            name: $name,
            pos: $pos,
            age: 25,
            teamid: $teamid,
            teamName: $teamName,
            teamColor1: 'FF0000',
            teamColor2: 'FFFFFF',
            deltas: $deltas,
            maxAbsDelta: $isNew ? 0 : $maxAbs,
            sumAbsDelta: $sumAbs,
            isNewPlayer: $isNew,
        );
    }

    /**
     * Builds a RatingRow with a specific delta for the 'oo' field and zero for all others.
     */
    private function buildRatingRowWithOoDelta(
        int $pid,
        string $name,
        int $ooDelta,
    ): RatingRow {
        /** @var array<string, RatingDelta> $deltas */
        $deltas = [];

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            if ($field === 'oo') {
                $deltas[$field] = new RatingDelta($field, 50, 50 + $ooDelta, $ooDelta);
            } else {
                $deltas[$field] = new RatingDelta($field, 50, 50, 0);
            }
        }

        $maxAbs = abs($ooDelta);

        return new RatingRow(
            pid: $pid,
            name: $name,
            pos: 'PG',
            age: 25,
            teamid: 1,
            teamName: 'Test Team',
            teamColor1: 'FF0000',
            teamColor2: 'FFFFFF',
            deltas: $deltas,
            maxAbsDelta: $maxAbs,
            sumAbsDelta: $maxAbs,
            isNewPlayer: false,
        );
    }

    // ---------------------------------------------------------------------------
    // Interface implementation
    // ---------------------------------------------------------------------------

    public function test_view_implements_interface(): void
    {
        self::assertInstanceOf(TrainingCampRatingsDiffViewInterface::class, $this->view);
    }

    // ---------------------------------------------------------------------------
    // Empty-state rendering
    // ---------------------------------------------------------------------------

    public function test_it_renders_empty_state_block_when_baseline_year_is_null(): void
    {
        $html = $this->view->render(null, []);

        self::assertStringContainsString('ibl-card', $html);
        self::assertStringContainsString('No prior-season baseline found', $html);
    }

    public function test_it_renders_empty_state_block_when_rows_is_empty(): void
    {
        $html = $this->view->render(2025, []);

        self::assertStringContainsString('ibl-card', $html);
        self::assertStringContainsString('No prior-season baseline found', $html);
    }

    // ---------------------------------------------------------------------------
    // Main table rendering — structure and content
    // ---------------------------------------------------------------------------

    public function test_it_renders_h2_title_and_intro_paragraph_including_the_baseline_year(): void
    {
        $row  = $this->buildRatingRow(1, 'Player A', 5);
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('<h2', $html);
        self::assertStringContainsString('Training Camp Ratings Diff', $html);
        self::assertStringContainsString('<p', $html);
        self::assertStringContainsString('2025', $html);
    }

    public function test_it_renders_the_sortable_ibl_data_table_sticky_table_ratings_diff_table_classes(): void
    {
        $row  = $this->buildRatingRow(1, 'Player A', 5);
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('sortable', $html);
        self::assertStringContainsString('ibl-data-table', $html);
        self::assertStringContainsString('sticky-table', $html);
        self::assertStringContainsString('ratings-diff-table', $html);
        self::assertStringContainsString('sticky-scroll-wrapper page-sticky', $html);
        self::assertStringContainsString('sticky-corner', $html);
    }

    // ---------------------------------------------------------------------------
    // XSS protection
    // ---------------------------------------------------------------------------

    public function test_it_wraps_dynamic_text_in_escaped_output(): void
    {
        $row  = $this->buildRatingRow(1, '<script>alert(1)</script>', 5);
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // ---------------------------------------------------------------------------
    // Delta span classes
    // ---------------------------------------------------------------------------

    public function test_it_renders_a_delta_up_span_for_positive_deltas(): void
    {
        $row  = $this->buildRatingRowWithOoDelta(1, 'Player A', 5); // delta=+5
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('delta-up', $html);
        self::assertStringContainsString('(+5)', $html);
    }

    public function test_it_renders_a_delta_down_span_for_negative_deltas(): void
    {
        $row  = $this->buildRatingRowWithOoDelta(1, 'Player A', -8); // delta=-8
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('delta-down', $html);
        self::assertStringContainsString('(-8)', $html);
    }

    public function test_it_renders_no_delta_span_for_zero_deltas(): void
    {
        $row  = $this->buildRatingRow(1, 'Player A', 0);
        $html = $this->view->render(2025, [$row]);

        self::assertStringNotContainsString('delta-zero', $html);
        self::assertStringNotContainsString('delta-up', $html);
        self::assertStringNotContainsString('delta-down', $html);
    }

    // ---------------------------------------------------------------------------
    // New-player (rookie) rows
    // ---------------------------------------------------------------------------

    public function test_it_renders_new_badge_for_rookie_rows(): void
    {
        $rookie = $this->buildRatingRow(99, 'Rookie Player', 0, true);
        $html   = $this->view->render(2025, [$rookie]);

        self::assertStringContainsString('badge-new', $html);
        self::assertStringContainsString('NEW', $html);
    }

    // ---------------------------------------------------------------------------
    // Separator row between real rows and new rows
    // ---------------------------------------------------------------------------

    public function test_it_renders_the_ratings_separator_row_when_both_real_and_new_rows_are_present(): void
    {
        $real   = $this->buildRatingRow(1, 'Veteran',       5, false);
        $rookie = $this->buildRatingRow(2, 'Rookie Player', 0, true);

        // Service would have already sorted: real rows first, then new rows
        $html = $this->view->render(2025, [$real, $rookie]);

        self::assertStringContainsString('ratings-separator', $html);
    }

    public function test_it_does_not_render_separator_when_only_real_rows_are_present(): void
    {
        $row  = $this->buildRatingRow(1, 'Veteran', 5, false);
        $html = $this->view->render(2025, [$row]);

        self::assertStringNotContainsString('ratings-separator', $html);
    }

    public function test_it_does_not_render_separator_when_only_new_rows_are_present(): void
    {
        $rookie = $this->buildRatingRow(1, 'Rookie', 0, true);
        $html   = $this->view->render(2025, [$rookie]);

        self::assertStringNotContainsString('ratings-separator', $html);
    }

    // ---------------------------------------------------------------------------
    // sorttable_customkey attributes
    // ---------------------------------------------------------------------------

    public function test_it_renders_sorttable_customkey_attributes_on_rating_cells(): void
    {
        $row  = $this->buildRatingRowWithOoDelta(1, 'Player A', 3);
        $html = $this->view->render(2025, [$row]);

        self::assertStringContainsString('sorttable_customkey=', $html);
    }

    // ---------------------------------------------------------------------------
    // No forbidden inline styles
    // ---------------------------------------------------------------------------

    public function test_it_does_not_contain_inline_style_attributes_except_allowed_patterns(): void
    {
        $row  = $this->buildRatingRow(1, 'Player A', 5);
        $html = $this->view->render(2025, [$row]);

        // Allow: CSS custom properties (--), team cell colors (background-color/color from TeamCellHelper)
        $forbidden = (bool) preg_match('/style="(?!--|background-color: #|color: #)/', $html);
        self::assertFalse($forbidden, 'Output contains a forbidden inline style="..." attribute');
    }
}
