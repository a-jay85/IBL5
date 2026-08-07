<?php

declare(strict_types=1);

namespace Logging;

use Logging\Contracts\LoggerFactoryInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;

/** @phpstan-type LoggingConfig array{log_dir: string|null, level: string, retention: int, channel_retention?: array<string, int>, discord_webhook_url?: string|null, discord_alert_level?: string} */
class LoggerFactory implements LoggerFactoryInterface
{
    private static ?self $instance = null;

    private static int $slowQueryThresholdMs = 200;

    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    /** @var list<\Monolog\Handler\HandlerInterface> */
    private array $handlers;

    /** @var list<ProcessorInterface> */
    private array $processors;

    /** @var array<string, \Monolog\Handler\HandlerInterface> */
    private array $channelHandlers = [];

    /** @var LoggingConfig */
    private const DEFAULT_CONFIG = [
        'log_dir' => null,
        'level' => 'debug',
        'retention' => 30,
    ];

    /**
     * @param list<\Monolog\Handler\HandlerInterface> $handlers
     * @param list<ProcessorInterface> $processors
     * @param array<string, \Monolog\Handler\HandlerInterface> $channelHandlers
     */
    private function __construct(array $handlers, array $processors = [], array $channelHandlers = [])
    {
        $this->handlers = $handlers;
        $this->processors = $processors;
        $this->channelHandlers = $channelHandlers;
        self::$instance = $this;
    }

    public static function fromConfig(): self
    {
        $configPath = self::resolveConfigDir() . '/logging.config.php';
        $examplePath = self::resolveConfigDir() . '/logging.config.example.php';

        if (file_exists($configPath)) {
            /** @var LoggingConfig $config */
            $config = require $configPath; /** @phpstan-ignore ibl.requireOnce (config file returns array; not a class) */
        } elseif (file_exists($examplePath)) {
            /** @var LoggingConfig $config */
            $config = require $examplePath; /** @phpstan-ignore ibl.requireOnce (config file returns array; not a class) */
        } else {
            $config = self::DEFAULT_CONFIG;
        }

        $logDir = is_string($config['log_dir'] ?? null)
            ? $config['log_dir']
            : self::resolveLogDir();

        $levelName = is_string($config['level'] ?? null) ? strtolower($config['level']) : 'debug';
        $level = self::parseLevel($levelName);

        $retention = is_int($config['retention'] ?? null) ? $config['retention'] : 30;

        $formatter = new JsonFormatter();
        $formatter->includeStacktraces(true);

        $handler = new RotatingFileHandler(
            $logDir . '/ibl5.log',
            $retention,
            $level,
        );
        $handler->setFormatter($formatter);

        /** @var list<\Monolog\Handler\HandlerInterface> $handlers */
        $handlers = [$handler];

        if (PHP_SAPI === 'cli') {
            $cliHandler = new StreamHandler('php://stdout', Level::Info);
            $handlers[] = $cliHandler;
        }

        $webhookUrl = $config['discord_webhook_url'] ?? null;
        if (is_string($webhookUrl) && $webhookUrl !== '') {
            $alertLevelName = is_string($config['discord_alert_level'] ?? null)
                ? strtolower($config['discord_alert_level'])
                : 'error';
            $alertLevel = self::parseLevel($alertLevelName);
            $handlers[] = new DiscordWebhookHandler($webhookUrl, $alertLevel);
        }

        /** @var array<string, int> $channelRetention */
        $channelRetention = is_array($config['channel_retention'] ?? null)
            ? $config['channel_retention']
            : [];

        /** @var array<string, \Monolog\Handler\HandlerInterface> $channelHandlers */
        $channelHandlers = [];
        foreach ($channelRetention as $channel => $days) {
            if (!is_string($channel) || !is_int($days)) {
                continue;
            }
            $channelHandler = new RotatingFileHandler(
                $logDir . '/ibl5-' . $channel . '.log',
                $days,
                $level,
            );
            $channelHandler->setFormatter($formatter);
            $channelHandlers[$channel] = $channelHandler;
        }

        // PiiRedactionProcessor must be pushed first so it runs last (after WebProcessor/UserContextProcessor populate extra)
        $processors = [
            new PiiRedactionProcessor(),
            new UidProcessor(7),
            new WebProcessor(),
            new UserContextProcessor(),
        ];

        self::$slowQueryThresholdMs = is_int($config['slow_query_threshold_ms'] ?? null)
            ? $config['slow_query_threshold_ms']
            : 200;

        return new self($handlers, $processors, $channelHandlers);
    }

    public static function forTests(): self
    {
        return self::forTesting(new NullHandler());
    }

    public static function forTesting(\Monolog\Handler\HandlerInterface $handler): self
    {
        self::$slowQueryThresholdMs = 0;
        return new self([$handler]);
    }

    /** @see LoggerFactoryInterface::channel() */
    public function channel(string $channel): LoggerInterface
    {
        if (!isset($this->channels[$channel])) {
            $logger = new Logger($channel);

            if (isset($this->channelHandlers[$channel])) {
                $logger->pushHandler($this->channelHandlers[$channel]);
            }
            foreach ($this->handlers as $handler) {
                $logger->pushHandler($handler);
            }
            foreach ($this->processors as $processor) {
                $logger->pushProcessor($processor);
            }
            $this->channels[$channel] = $logger;
        }

        return $this->channels[$channel];
    }

    public static function getChannel(string $channel): LoggerInterface
    {
        if (self::$instance === null) {
            self::forTests();
        }

        $instance = self::$instance;
        \assert($instance instanceof self);

        return $instance->channel($channel);
    }

    public static function reset(): void
    {
        self::$instance = null;
        self::$slowQueryThresholdMs = 200;
    }

    public static function getSlowQueryThresholdMs(): int
    {
        return self::$slowQueryThresholdMs;
    }

    private static function parseLevel(string $name): Level
    {
        return match ($name) {
            'emergency' => Level::Emergency,
            'alert' => Level::Alert,
            'critical' => Level::Critical,
            'error' => Level::Error,
            'warning' => Level::Warning,
            'notice' => Level::Notice,
            'info' => Level::Info,
            default => Level::Debug,
        };
    }

    private static function resolveConfigDir(): string
    {
        if (is_dir('config')) {
            return 'config';
        }

        if (is_dir('ibl5/config')) {
            return 'ibl5/config';
        }

        return dirname(__DIR__, 2) . '/config';
    }

    private static function resolveLogDir(): string
    {
        if (is_dir('logs') || is_dir('config')) {
            $dir = 'logs';
        } elseif (is_dir('ibl5/logs') || is_dir('ibl5/config')) {
            $dir = 'ibl5/logs';
        } else {
            $dir = dirname(__DIR__, 2) . '/logs';
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
