<?php

declare(strict_types=1);

namespace Tests\Api\Response;

use Api\Response\HtmlResponder;
use PHPUnit\Framework\TestCase;

class HtmlResponderTest extends TestCase
{
    private HtmlResponder $responder;

    protected function setUp(): void
    {
        $this->responder = new HtmlResponder();
    }

    public function testHtmlOutputsContentVerbatim(): void
    {
        $content = '<div class="test">Hello World</div>';

        ob_start();
        $this->responder->html($content);
        $output = ob_get_clean();

        $this->assertSame($content, $output);
    }

    public function testHtmlOutputsEmptyString(): void
    {
        ob_start();
        $this->responder->html('');
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function testJsonOutputsEncodedData(): void
    {
        $data = ['success' => true, 'html' => '<div>test</div>'];

        ob_start();
        $this->responder->json($data);
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        $decoded = json_decode($output, true);
        $this->assertSame($data, $decoded);
    }

    public function testJsonThrowsOnUnencodableData(): void
    {
        $this->expectException(\JsonException::class);

        $resource = fopen('php://memory', 'r');
        $this->assertNotFalse($resource);

        try {
            ob_start();
            $this->responder->json($resource);
        } finally {
            ob_end_clean();
            fclose($resource);
        }
    }
}
