<?php

declare(strict_types=1);

namespace UI;

/**
 * Shared closing markup for views that render a `<table>` with a `<tbody>`.
 */
trait RendersTableEnd
{
    /**
     * Render the end of a table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
