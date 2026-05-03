<?php

declare(strict_types=1);

namespace Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DiscordWebhookHandler extends AbstractProcessingHandler
{
    private string $webhookUrl;

    /** @var callable(string, string): void */
    private $transport;

    /**
     * @param callable(string, string): void|null $transport Custom transport for testing. Receives (url, jsonPayload).
     */
    public function __construct(string $webhookUrl, Level $level = Level::Error, bool $bubble = true, ?callable $transport = null)
    {
        parent::__construct($level, $bubble);
        $this->webhookUrl = $webhookUrl;
        $this->transport = $transport ?? self::defaultTransport();
    }

    protected function write(LogRecord $record): void
    {
        $embed = [
            'title' => $record->level->name . ': ' . $record->channel,
            'description' => mb_substr($record->message, 0, 2000),
            'color' => $this->levelColor($record->level),
            'timestamp' => $record->datetime->format(\DateTimeInterface::ATOM),
        ];

        $context = $record->context;
        if ($context !== []) {
            $contextStr = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (is_string($contextStr)) {
                $embed['fields'] = [
                    ['name' => 'Context', 'value' => '```json' . "\n" . mb_substr($contextStr, 0, 1000) . "\n" . '```'],
                ];
            }
        }

        $payload = json_encode(['embeds' => [$embed]], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }

        ($this->transport)($this->webhookUrl, $payload);
    }

    private function levelColor(Level $level): int
    {
        return match (true) {
            $level->value >= Level::Emergency->value => 0x000000,
            $level->value >= Level::Critical->value => 0xFF0000,
            $level->value >= Level::Error->value => 0xE74C3C,
            default => 0xE67E22,
        };
    }

    /**
     * @return callable(string, string): void
     */
    private static function defaultTransport(): callable
    {
        return static function (string $url, string $jsonPayload): void {
            $ch = curl_init($url);
            if ($ch === false) {
                return;
            }
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        };
    }
}
