<?php

declare(strict_types=1);

namespace Tests\Logging;

use Logging\DiscordWebhookHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class DiscordWebhookHandlerTest extends TestCase
{
    public function testFiresOnErrorLevel(): void
    {
        $calls = [];
        $transport = static function (string $url, string $payload) use (&$calls): void {
            $calls[] = ['url' => $url, 'payload' => $payload];
        };

        $handler = new DiscordWebhookHandler('https://discord.test/webhook', Level::Error, true, $transport);

        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->error('Something broke');

        $this->assertCount(1, $calls);
        $this->assertSame('https://discord.test/webhook', $calls[0]['url']);

        $decoded = json_decode($calls[0]['payload'], true);
        \assert(is_array($decoded));
        $this->assertArrayHasKey('embeds', $decoded);
    }

    public function testDoesNotFireBelowErrorLevel(): void
    {
        $calls = [];
        $transport = static function (string $url, string $payload) use (&$calls): void {
            $calls[] = ['url' => $url, 'payload' => $payload];
        };

        $handler = new DiscordWebhookHandler('https://discord.test/webhook', Level::Error, true, $transport);

        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->warning('Just a warning');

        $this->assertCount(0, $calls);
    }

    public function testFiresOnCriticalLevel(): void
    {
        $calls = [];
        $transport = static function (string $url, string $payload) use (&$calls): void {
            $calls[] = ['url' => $url, 'payload' => $payload];
        };

        $handler = new DiscordWebhookHandler('https://discord.test/webhook', Level::Error, true, $transport);

        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->critical('Critical failure');

        $this->assertCount(1, $calls);
    }

    public function testIncludesContextInPayload(): void
    {
        $calls = [];
        $transport = static function (string $url, string $payload) use (&$calls): void {
            $calls[] = ['url' => $url, 'payload' => $payload];
        };

        $handler = new DiscordWebhookHandler('https://discord.test/webhook', Level::Error, true, $transport);

        $logger = new Logger('test');
        $logger->pushHandler($handler);
        $logger->error('DB error', ['query' => 'SELECT 1']);

        $decoded = json_decode($calls[0]['payload'], true);
        \assert(is_array($decoded));
        $embed = $decoded['embeds'][0];
        \assert(is_array($embed));
        $this->assertArrayHasKey('fields', $embed);
    }

    public function testEmbedsContainChannelAndLevel(): void
    {
        $calls = [];
        $transport = static function (string $url, string $payload) use (&$calls): void {
            $calls[] = ['url' => $url, 'payload' => $payload];
        };

        $handler = new DiscordWebhookHandler('https://discord.test/webhook', Level::Error, true, $transport);

        $logger = new Logger('myapp');
        $logger->pushHandler($handler);
        $logger->error('test error');

        $decoded = json_decode($calls[0]['payload'], true);
        \assert(is_array($decoded));
        $title = $decoded['embeds'][0]['title'];
        $this->assertStringContainsString('Error', $title);
        $this->assertStringContainsString('myapp', $title);
    }
}
