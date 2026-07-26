<?php

declare(strict_types=1);

class TrustedSafeCollaborator
{
    public function renderCell(): string
    {
        return '<td></td>';
    }
}

class TrustedSafeFixture
{
    private TrustedSafeCollaborator $collaborator;

    public function __construct()
    {
        $this->collaborator = new TrustedSafeCollaborator();
    }

    public function render(int $count): void
    {
        HtmlSanitizer::trusted('<b>literal html</b>');
        HtmlSanitizer::trusted((int) $count);
        HtmlSanitizer::trusted((float) $count);
        HtmlSanitizer::trusted((bool) $count);
        HtmlSanitizer::trusted($this->renderRow());
        HtmlSanitizer::trusted($this->collaborator->renderCell());
    }

    private function renderRow(): string
    {
        return '<td></td>';
    }
}
