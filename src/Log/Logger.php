<?php

namespace Trunk\Log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Trunk\Config\Repository;

use function is_object;
use function is_scalar;
use function sprintf;

class Logger extends AbstractLogger
{
    private string $channel;
    private string $logPath;
    private array $levels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];
    private int $minLevel;

    public function __construct(Repository $config)
    {
        $this->channel = $config->get('logging.default', 'stack');
        $channelConfig = $config->get("logging.channels.{$this->channel}", []);

        $levelStr = $channelConfig['level'] ?? LogLevel::DEBUG;
        $this->minLevel = $this->levels[$levelStr] ?? 7;

        $this->logPath = $channelConfig['path'] ?? 'php://stdout';
    }

    public function log($level, $message, array $context = []): void
    {
        if (!isset($this->levels[$level]) || $this->levels[$level] > $this->minLevel) {
            return;
        }

        $formatted = $this->formatMessage($level, $message, $context);

        if ($this->logPath === 'php://stdout' || $this->logPath === 'php://stderr') {
            file_put_contents($this->logPath, $formatted);
        } else {
            // Async non-blocking file append fallback (using standard stream write, which is fast)
            // In a high-performance system, we could queue logs or use react/filesystem.
            @file_put_contents($this->logPath, $formatted, FILE_APPEND);
        }
    }

    private function formatMessage(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = $this->interpolate($message, $context);
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        return sprintf("[%s] trunk.%s: %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || is_object($val) && method_exists($val, '__toString')) {
                $replace["{$key}"] = $val;
            }
        }
        return strtr($message, $replace);
    }
}
