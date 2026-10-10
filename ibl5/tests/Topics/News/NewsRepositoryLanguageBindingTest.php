<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\TestCase;
use Topics\News\NewsRepository;
use Topics\News\NewsService;
use Tests\WideUnit\Mocks\MockDatabase;

class NewsRepositoryLanguageBindingTest extends TestCase
{
    private NewsRepository $repository;
    private MockDatabase $mockDb;
    private string $savedTimezone;

    protected function setUp(): void
    {
        $this->savedTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->mockDb = new MockDatabase();
        $this->repository = new NewsRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->savedTimezone);
    }

    public function testGetStoriesByCategoryBindsLanguageAsParameter(): void
    {
        $this->repository->getStoriesByCategory(15, 10, 'english');

        $template = $this->storiesTemplate();
        $this->assertStringContainsString("AND (alanguage = ? OR alanguage = '')", $template);
        $this->assertStringNotContainsString('english', $template);

        $executed = $this->storiesExecuted();
        $this->assertStringContainsString("catid = 15 AND (alanguage = 'english' OR alanguage = '')", $executed);
        $this->assertStringEndsWith('LIMIT 10', rtrim($executed));
    }

    public function testGetStoriesByCategoryKeepsQuoteBearingLanguageOutOfTemplate(): void
    {
        $this->repository->getStoriesByCategory(15, 10, "x' OR '1'='1");

        $this->assertStringNotContainsString("OR '1'", $this->storiesTemplate());
        $this->assertStringContainsString("alanguage = 'x\\' OR \\'1\\'=\\'1'", $this->storiesExecuted());
    }

    public function testGetStoriesByCategoryNullLanguageOmitsClause(): void
    {
        $this->repository->getStoriesByCategory(15, 10, null);
        $explicitNull = $this->storiesTemplate();

        $this->mockDb = new MockDatabase();
        $this->repository = new NewsRepository($this->mockDb);
        $this->repository->getStoriesByCategory(15, 10);
        $defaulted = $this->storiesTemplate();

        foreach ([$explicitNull, $defaulted] as $template) {
            $this->assertStringContainsString('WHERE catid = ?', $template);
            $this->assertStringNotContainsString('alanguage', $template);
        }
    }

    public function testGetStoriesByCategoryEmptyStringLanguageStillBinds(): void
    {
        $this->repository->getStoriesByCategory(15, 10, '');

        $this->assertStringContainsString('alanguage = ?', $this->storiesTemplate());
        $this->assertStringContainsString("alanguage = '' OR alanguage = ''", $this->storiesExecuted());
    }

    public function testServiceGetCategoryPageStoriesPassesLanguageThrough(): void
    {
        (new NewsService($this->mockDb))->getCategoryPageStories(15, 10, 'english');
        $this->assertStringContainsString('alanguage = ?', $this->storiesTemplate());

        $this->mockDb = new MockDatabase();
        (new NewsService($this->mockDb))->getCategoryPageStories(15, 10, null);
        $this->assertStringNotContainsString('alanguage', $this->storiesTemplate());
    }

    private function storiesTemplate(): string
    {
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            if (str_contains($query, 'nuke_stories')) {
                return $query;
            }
        }
        $this->fail('No prepared query referencing nuke_stories');
    }

    private function storiesExecuted(): string
    {
        foreach ($this->mockDb->getExecutedQueries() as $query) {
            if (str_contains($query, 'nuke_stories')) {
                return $query;
            }
        }
        $this->fail('No executed query referencing nuke_stories');
    }
}
