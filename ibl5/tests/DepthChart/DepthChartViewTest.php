<?php

use PHPUnit\Framework\TestCase;
use DepthChart\DepthChartView;
use DepthChart\DepthChartProcessor;

/**
 * Tests for DepthChartView
 */
class DepthChartViewTest extends TestCase
{
    private $view;

    protected function setUp(): void
    {
        $processor = new DepthChartProcessor();
        $this->view = new DepthChartView($processor);
    }

    /**
     * Test that renderFormFooter includes both Reset and Submit buttons
     */
    public function testRenderFormFooterIncludesResetButton()
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = ob_get_clean();

        // Check that Reset button is present
        $this->assertStringContainsString('value="Reset"', $output);
        $this->assertStringContainsString('onclick="resetDepthChart();"', $output);

        // Check that Submit button is still present
        $this->assertStringContainsString('value="Submit Depth Chart"', $output);

        // Check that buttons have visual differentiation
        $this->assertStringContainsString('background-color', $output);
    }

    /**
     * Test that renderFormFooter includes the reset JavaScript function
     */
    public function testRenderFormFooterIncludesResetScript()
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = ob_get_clean();

        // Check that the JavaScript function is included
        $this->assertStringContainsString('function resetDepthChart()', $output);
        $this->assertStringContainsString('document.forms[\'Depth_Chart\']', $output);

        // Check that it includes confirmation dialog
        $this->assertStringContainsString('confirm(', $output);

        // Check that it handles different field types correctly
        $this->assertStringContainsString('active', $output);
        $this->assertStringContainsString('pg|sg|sf|pf|c', $output);
        $this->assertStringContainsString('min|OF|DF', $output);
        $this->assertStringContainsString('OI|DI|BH', $output);
    }

    /**
     * Test that the reset button is a button type (not submit)
     */
    public function testResetButtonIsButtonType()
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = ob_get_clean();

        // The Reset button should be type="button" to prevent form submission
        $this->assertStringContainsString('type="button" value="Reset"', $output);
    }

    /**
     * Test that the deprecated radio button is not present
     */
    public function testRadioButtonNotPresent()
    {
        ob_start();
        $this->view->renderFormFooter();
        $output = ob_get_clean();

        // The deprecated "Submit Depth Chart?" radio button should not be present
        $this->assertStringNotContainsString('Submit Depth Chart?', $output);
        $this->assertStringNotContainsString('type="radio"', $output);
    }
}
