<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PlayerAwards\PlayerAwardsView;
use PlayerAwards\PlayerAwardsService;
use PlayerAwards\Contracts\PlayerAwardsValidatorInterface;
use PlayerAwards\Contracts\PlayerAwardsRepositoryInterface;

/**
 * Tests for PlayerAwardsView
 *
 * Verifies HTML rendering for player awards search interface.
 */
#[AllowMockObjectsWithoutExpectations]
final class PlayerAwardsViewTest extends TestCase
{
    private PlayerAwardsView $view;

    protected function setUp(): void
    {
        // Create real validator and mock repository for the service
        $mockValidator = $this->createMock(PlayerAwardsValidatorInterface::class);
        $mockRepository = $this->createMock(PlayerAwardsRepositoryInterface::class);
        
        $service = new PlayerAwardsService($mockValidator, $mockRepository);
        $this->view = new PlayerAwardsView($service);
    }

    // ==================== renderSearchForm Tests ====================

    public function testRenderSearchFormReturnsHtmlString(): void
    {
        $params = ['name' => null, 'award' => null, 'year' => null, 'sortby' => 3];
        
        $result = $this->view->renderSearchForm($params);

        $this->assertIsString($result);
        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('</form>', $result);
    }

    public function testRenderSearchFormIncludesFormFields(): void
    {
        $params = ['name' => null, 'award' => null, 'year' => null, 'sortby' => 3];
        
        $result = $this->view->renderSearchForm($params);

        $this->assertStringContainsString('aw_name', $result);
        $this->assertStringContainsString('aw_Award', $result);
        $this->assertStringContainsString('aw_year', $result);
        $this->assertStringContainsString('aw_sortby', $result);
    }

    public function testRenderSearchFormPrePopulatesValues(): void
    {
        $params = ['name' => 'Johnson', 'award' => 'MVP', 'year' => 2025, 'sortby' => 1];
        
        $result = $this->view->renderSearchForm($params);

        $this->assertStringContainsString('Johnson', $result);
        $this->assertStringContainsString('MVP', $result);
        $this->assertStringContainsString('2025', $result);
    }

    public function testRenderSearchFormIncludesSubmitButton(): void
    {
        $params = ['name' => null, 'award' => null, 'year' => null, 'sortby' => 3];
        
        $result = $this->view->renderSearchForm($params);

        $this->assertStringContainsString('type="submit"', $result);
        $this->assertStringContainsString('Search for Matches', $result);
    }

    public function testRenderSearchFormEscapesHtmlCharacters(): void
    {
        $params = ['name' => '<script>alert("XSS")</script>', 'award' => null, 'year' => null, 'sortby' => 3];
        
        $result = $this->view->renderSearchForm($params);

        // Should escape the HTML characters
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderSearchFormIncludesSortOptions(): void
    {
        $params = ['name' => null, 'award' => null, 'year' => null, 'sortby' => 3];
        
        $result = $this->view->renderSearchForm($params);

        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Award Name', $result);
        $this->assertStringContainsString('Year', $result);
    }

    // ==================== renderTableHeader Tests ====================

    public function testRenderTableHeaderReturnsHtmlString(): void
    {
        $result = $this->view->renderTableHeader();

        $this->assertIsString($result);
        $this->assertStringContainsString('<table', $result);
    }

    public function testRenderTableHeaderIncludesColumnHeaders(): void
    {
        $result = $this->view->renderTableHeader();

        $this->assertStringContainsString('Year', $result);
        $this->assertStringContainsString('Player', $result);
        $this->assertStringContainsString('Award', $result);
    }

    public function testRenderTableHeaderIncludesResultsTitle(): void
    {
        $result = $this->view->renderTableHeader();

        $this->assertStringContainsString('Search Results', $result);
    }

    // ==================== renderAwardRow Tests ====================

    public function testRenderAwardRowReturnsHtmlString(): void
    {
        $award = ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson'];
        
        $result = $this->view->renderAwardRow($award, 0);

        $this->assertIsString($result);
        $this->assertStringContainsString('<tr', $result);
        $this->assertStringContainsString('</tr>', $result);
    }

    public function testRenderAwardRowDisplaysAwardData(): void
    {
        $award = ['year' => 2025, 'Award' => 'Most Valuable Player', 'name' => 'Magic Johnson'];
        
        $result = $this->view->renderAwardRow($award, 0);

        $this->assertStringContainsString('2025', $result);
        $this->assertStringContainsString('Most Valuable Player', $result);
        $this->assertStringContainsString('Magic Johnson', $result);
    }

    public function testRenderAwardRowCreatesPlainRows(): void
    {
        $award = ['year' => 2025, 'Award' => 'MVP', 'name' => 'Johnson'];

        $evenRow = $this->view->renderAwardRow($award, 0);
        $oddRow = $this->view->renderAwardRow($award, 1);

        // Row alternation is now handled by CSS :nth-child, not row classes
        $this->assertStringContainsString('<tr>', $evenRow);
        $this->assertStringContainsString('<tr>', $oddRow);
        $this->assertStringNotContainsString('row-even', $evenRow);
        $this->assertStringNotContainsString('row-odd', $oddRow);
    }

    public function testRenderAwardRowEscapesHtmlCharacters(): void
    {
        $award = ['year' => 2025, 'Award' => '<script>XSS</script>', 'name' => '<b>Hacker</b>'];
        
        $result = $this->view->renderAwardRow($award, 0);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    public function testRenderAwardRowHandlesMissingData(): void
    {
        $award = ['year' => null, 'Award' => null, 'name' => null];
        
        $result = $this->view->renderAwardRow($award, 0);

        // Should not throw an exception and return valid HTML
        $this->assertIsString($result);
        $this->assertStringContainsString('<tr', $result);
    }

    // ==================== renderTableFooter Tests ====================

    public function testRenderTableFooterClosesTable(): void
    {
        $result = $this->view->renderTableFooter();

        $this->assertIsString($result);
        $this->assertStringContainsString('</table>', $result);
    }
}
