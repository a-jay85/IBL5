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

/**
 * Creates named PSR-3 logger instances backed by Monolog.
 *
 * All channels share a single RotatingFileHandler writing JSON to logs/ibl5-YYYY-MM-DD.log.
 * Channels listed in `channel_retention` config get dedicated handlers with their own
 * retention and log file. CLI scripts automatically get an additional stdout handler.
 *
 * Usage:
 *   LoggerFactory::channel('db')->error('Query failed', ['query' => $sql]);
 *   LoggerFactory::channel('discord')->warning('DM skipped', ['team' => $name]);
 *
 * Note: The static accessor (getChannel) exists until Bootstrap\Container is wired into
 * mainfile.php. Once that happens, inject LoggerFactoryInterface via the container instead.
 *
 * @phpstan-type LoggingConfig array{log_dir: string|null, level: string, retention: int, channel_retention?: array<string, int>, discord_webhook_url?: string|null, discord_alert_level?: string}
 */
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

    /**
     * Create a factory from the config file.
     *
     * Loads config/logging.config.php if it exists, falls back to
     * config/logging.config.example.php, then to hardcoded defaults.
     */
    public static function fromConfig(): self
    {
        $configPath = self::resolveConfigDir() . '/logging.config.php';
        $examplePath = self::resolveConfigDir() . '/logging.config.example.php';

        if (file_exists($configPath)) {
            /** @var LoggingConfig $config */
            $config = require $configPath;
        } elseif (file_exists($examplePath)) {
            /** @var LoggingConfig $config */
            $config = require $examplePath;
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

        // CLI scripts get an additional stdout handler at INFO level
        if (PHP_SAPI === 'cli') {
            $cliHandler = new StreamHandler('php://stdout', Level::Info);
            $handlers[] = $cliHandler;
        }

        // Discord webhook handler for error alerting
        $webhookUrl = $config['discord_webhook_url'] ?? null;
        if (is_string($webhookUrl) && $webhookUrl !== '') {
            $alertLevelName = is_string($config['discord_alert_level'] ?? null)
                ? strtolower($config['discord_alert_level'])
                : 'error';
            $alertLevel = self::parseLevel($alertLevelName);
            $handlers[] = new DiscordWebhookHandler($webhookUrl, $alertLevel);
        }

        // Per-channel retention overrides
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

        $processors = [
            new UidProcessor(7),
            new WebProcessor(),
            new UserContextProcessor(),
            new PiiRedactionProcessor(),
        ];

        self::$slowQueryThresholdMs = is_int($config['slow_query_threshold_ms'] ?? null)
            ? $config['slow_query_threshold_ms']
            : 200;

        return new self($handlers, $processors, $channelHandlers);
    }

    /**
     * Create a factory with a NullHandler for tests (no file I/O, no output).
     */
    public static function forTests(): self
    {
        self::$slowQueryThresholdMs = 0;
        return new self([new NullHandler()]);
    }

    /**
     * Create a factory with a specific handler for tests that need to inspect log records.
     *
     * @param \Monolog\Handler\HandlerInterface $handler
     */
    public static function forTesting(\Monolog\Handler\HandlerInterface $handler): self
    {
        self::$slowQueryThresholdMs = 0;
        return new self([$handler]);
    }

    /**
     * Get a logger for the given channel.
     *
     * @see LoggerFactoryInterface::channel()
     */
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

    /**
     * Static accessor for the singleton instance's channel method.
     *
     * Falls back to a NullHandler logger if no instance has been initialized
     * (e.g. during early bootstrap or in edge-case code paths).
     */
    public static function getChannel(string $channel): LoggerInterface
    {
        if (self::$instance === null) {
            // Lazy fallback — avoids crashes if called before fromConfig()
            self::forTests();
        }

        $instance = self::$instance;
        \assert($instance instanceof self);

        return $instance->channel($channel);
    }

    /**
     * Clear the singleton (for test teardown).
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$slowQueryThresholdMs = 200;
    }

    /**
     * Get the configured slow query threshold in milliseconds.
     * Returns 0 if slow query logging is disabled.
     */
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
        // When running from ibl5/ directory (normal app context)
        if (is_dir('config')) {
            return 'config';
        }

        // When running from project root or tests
        if (is_dir('ibl5/config')) {
            return 'ibl5/config';
        }

        // Absolute fallback based on this file's location
        return dirname(__DIR__, 2) . '/config';
    }

    private static function resolveLogDir(): string
    {
        // When running from ibl5/ directory
        if (is_dir('logs') || is_dir('config')) {
            $dir = 'logs';
        } elseif (is_dir('ibl5/logs') || is_dir('ibl5/config')) {
            // When running from project root
            $dir = 'ibl5/logs';
        } else {
            // Absolute fallback
            $dir = dirname(__DIR__, 2) . '/logs';
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
}
