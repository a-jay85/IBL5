<?php

declare(strict_types=1);

class TrustedSafeFixture
{
    public function render(int $count): void
    {
        HtmlSanitizer::trusted('<b>literal html</b>');
        HtmlSanitizer::trusted((int) $count);
        HtmlSanitizer::trusted((float) $count);
        HtmlSanitizer::trusted((bool) $count);
        HtmlSanitizer::trusted($this->renderRow());
    }

    private function renderRow(): string
    {
        return '<td></td>';
    }
}
