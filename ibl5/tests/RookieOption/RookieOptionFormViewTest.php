<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionFormView;

/**
 * Tests for RookieOptionFormView
 */
class RookieOptionFormViewTest extends TestCase
{
    private $view;
    
    protected function setUp(): void
    {
        $this->view = new RookieOptionFormView();
    }
    
    /**
     * Test rendering form with proper HTML escaping
     */
    public function testRenderFormEscapesHtml()
    {
        $mockPlayer = new stdClass();
        $mockPlayer->playerID = 123;
        $mockPlayer->position = 'PG';
        $mockPlayer->name = 'Test Player';
        
        ob_start();
        $this->view->renderForm($mockPlayer, 'Test Team', 500);
        $output = ob_get_clean();
        
        // Check that key elements are present
        $this->assertStringContainsString('PG Test Player', $output);
        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('Test Team', $output);
        $this->assertStringContainsString('images/player/123.jpg', $output);
        $this->assertStringContainsString('name="teamname"', $output);
        $this->assertStringContainsString('name="playerID"', $output);
        $this->assertStringContainsString('name="rookieOptionValue"', $output);
    }
    
    /**
     * Test rendering form escapes potentially malicious HTML
     */
    public function testRenderFormEscapesMaliciousHtml()
    {
        $mockPlayer = new stdClass();
        $mockPlayer->playerID = 123;
        $mockPlayer->position = 'PG';
        $mockPlayer->name = '<script>alert("xss")</script>';
        
        ob_start();
        $this->view->renderForm($mockPlayer, '<script>bad</script>', 500);
        $output = ob_get_clean();
        
        // Verify that scripts are escaped
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringNotContainsString('<script>bad</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
    
    /**
     * Test rendering generic error message
     */
    public function testRenderError()
    {
        ob_start();
        $this->view->renderError('This is an error message.');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('This is an error message.', $output);
        $this->assertStringContainsString('Go Back', $output);
    }
    
    /**
     * Test that error message escapes HTML
     */
    public function testRenderErrorEscapesHtml()
    {
        ob_start();
        $this->view->renderError('<script>alert("xss")</script>');
        $output = ob_get_clean();
        
        // Verify HTML is escaped
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
}
