<?php

declare(strict_types=1);

namespace Honsa\Sentry\Log\Writer;

use Psr\Log\LogLevel;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\AbstractWriter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SentryLogWriter extends AbstractWriter
{
    private const REDACTED_VALUE = '[REDACTED]';
    private const MAX_SANITIZE_DEPTH = 5;

    private HubInterface $hub;
    private array $config;
    private bool $enabled = true;
    private array $enabledLevels = [];
    private bool $exceptionsOnly = false;
    private array $staticTags = [];
    private array $staticExtras = [];
    private bool $disableInit = false;
    private static bool $reportingInternalFailure = false;

    public function __construct(array $options = [])
    {
        // Extract internal test configuration before parent processing to avoid invalid option exception
        $internalConfig = [];
        $hasInternalConfig = false;
        if (isset($options['__config']) && \is_array($options['__config'])) {
            $hasInternalConfig = true;
            $internalConfig = $options['__config'];
            unset($options['__config']);
        }
        if (isset($options['__disableInit'])) {
            $this->disableInit = (bool)$options['__disableInit'];
            unset($options['__disableInit']);
        }
        // No supported writer-specific options are currently used; skip parent constructor if only internal config provided
        if ($options !== []) {
            parent::__construct($options); // will validate recognized set* methods if ever added
        }
        $this->config = $hasInternalConfig ? $internalConfig : $this->loadExtensionConfig();
        $this->enabled = (bool)$this->getConfigValue('features.enable', true);
        $this->exceptionsOnly = (bool)$this->getConfigValue('filter.captureExceptionsOnly', false);
        $this->enabledLevels = $this->enabled ? $this->parseList((string)$this->getConfigValue('filter.enabledLogLevels', 'warning,error,critical,alert,emergency')) : [];
        $this->staticTags = $this->parsePairs((string)$this->getConfigValue('context.staticTags', ''));
        $this->staticExtras = $this->parsePairs((string)$this->getConfigValue('context.staticExtras', ''));

        if ($this->enabled && !$this->disableInit) {
            $client = SentrySdk::getCurrentHub()->getClient();
            if ($client === null) {
                $dsn = (string)$this->getConfigValue('connection.dsn', \getenv('SENTRY_DSN') ?: '');
                if ($dsn !== '') {
                    \Sentry\init([
                        'dsn' => $dsn,
                        'environment' => (string)$this->getConfigValue('connection.environment', \getenv('SENTRY_ENVIRONMENT') ?: (\getenv('APP_ENV') ?: 'production')),
                        'release' => $this->getConfigValue('connection.release', \getenv('SENTRY_RELEASE') ?: null),
                        'traces_sample_rate' => (float)$this->getConfigValue('sampling.tracesSampleRate', (float)(\getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.0)),
                        'profiles_sample_rate' => (float)$this->getConfigValue('sampling.profilesSampleRate', (float)(\getenv('SENTRY_PROFILES_SAMPLE_RATE') ?: 0.0)),
                    ]);
                }
            }
        }
        $this->hub = SentrySdk::getCurrentHub();
    }

    public function writeLog(LogRecord $record)
    {
        try {
            if (!$this->enabled || SentrySdk::getCurrentHub()->getClient() === null) {
                return $this;
            }

            $psrLevel = $record->getLevel();
            if (!\in_array($psrLevel, $this->enabledLevels, true)) {
                return $this; // level filtered out
            }

            $context = $record->getData();
            $exception = $context['exception'] ?? null;
            if ($this->exceptionsOnly && !$exception instanceof \Throwable) {
                return $this; // skip non-exception messages when exceptions only
            }

            $severity = $this->mapLevel($psrLevel);
            $message = (string)$record->getMessage();

            $this->hub->withScope(function (Scope $scope) use ($record, $severity, $context) {
                $scope->setLevel($severity);
                $scope->setTag('typo3.component', $record->getComponent());
                if ($record->getRequestId()) {
                    $scope->setTag('typo3.request_id', $record->getRequestId());
                }
                foreach ($this->staticTags as $k => $v) {
                    $scope->setTag($k, $this->sanitizeTagValue($k, $v));
                }
                foreach ($this->staticExtras as $k => $v) {
                    $scope->setExtra($k, $this->sanitizeExtraValue($k, $v));
                }
                foreach ($context as $key => $value) {
                    if ($key === 'exception') {
                        continue;
                    }
                    $scope->setExtra((string)$key, $this->sanitizeExtraValue((string)$key, $value));
                }
            });

            if ($exception instanceof \Throwable) {
                \Sentry\captureException($exception);
            } else {
                \Sentry\captureMessage($message, $severity);
            }
        } catch (\Throwable $e) {
            $this->reportInternalFailure('Failed to forward TYPO3 log record to Sentry', $e);
        }
        return $this;
    }

    private function mapLevel(string $level): Severity
    {
        return match ($level) {
            LogLevel::DEBUG => Severity::debug(),
            LogLevel::INFO, LogLevel::NOTICE => Severity::info(),
            LogLevel::WARNING => Severity::warning(),
            LogLevel::ERROR => Severity::error(),
            LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY => Severity::fatal(),
            default => Severity::info(),
        };
    }

    private function loadExtensionConfig(): array
    {
        try {
            /** @var ExtensionConfiguration $extConfig */
            $extConfig = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            // Read configuration using the extension key 'sentry'
            $cfg = $extConfig->get('sentry');
            return \is_array($cfg) ? $cfg : [];
        } catch (\Throwable $e) {
            $this->reportInternalFailure('Failed to load Sentry extension configuration', $e);
            return [];
        }
    }

    /**
     * Resolve a configuration value by dot-notated path.
     * Supports both nested arrays (e.g. ['connection' => ['dsn' => '...']])
     * and flat keys stored directly using the full path (e.g. 'connection.dsn' => '...').
     */
    private function getConfigValue(string $path, mixed $default = null): mixed
    {
        // Support flat keys (e.g. 'connection.dsn' stored directly)
        if (\array_key_exists($path, $this->config)) {
            return $this->config[$path];
        }
        $segments = \explode('.', $path);
        $value = $this->config;
        foreach ($segments as $seg) {
            if (!\is_array($value) || !\array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }

    /**
     * @deprecated Use getConfigValue() instead. Kept for backward compatibility.
     */
    private function cfg(string $path, mixed $default = null): mixed
    {
        return $this->getConfigValue($path, $default);
    }

    private function parseList(string $raw): array
    {
        return \array_filter(\array_map('trim', \explode(',', \strtolower($raw))));
    }

    private function parsePairs(string $raw): array
    {
        $result = [];
        $items = \array_filter(\array_map('trim', \explode(';', $raw)));
        foreach ($items as $item) {
            if (\str_contains($item, ':')) {
                [$k, $v] = \array_map('trim', \explode(':', $item, 2));
                if ($k !== '') {
                    $result[$k] = $v;
                }
            }
        }
        return $result;
    }

    private function sanitizeTagValue(string $key, string $value): string
    {
        if ($this->isSensitiveKey($key)) {
            return self::REDACTED_VALUE;
        }

        return $value;
    }

    private function sanitizeExtraValue(string $key, mixed $value): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return self::REDACTED_VALUE;
        }

        return $this->sanitizeValue($value);
    }

    private function sanitizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= self::MAX_SANITIZE_DEPTH) {
            return '[TRUNCATED]';
        }

        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize(), $depth + 1);
        }

        if (\is_array($value)) {
            $sanitized = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $normalizedKey = \is_int($nestedKey) ? (string)$nestedKey : (string)$nestedKey;
                $sanitized[$normalizedKey] = $this->sanitizeExtraValue($normalizedKey, $this->sanitizeValue($nestedValue, $depth + 1));
            }

            return $sanitized;
        }

        if ($value instanceof \Stringable) {
            return '[object ' . $value::class . ']';
        }

        return '[object ' . $value::class . ']';
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool)\preg_match('/(?:^|[._\-])(pass(word)?|pwd|secret|token|api[_\-]?key|authorization|auth|cookie|set[_\-]?cookie|session|phpsessid|csrf|xsrf)(?:$|[._\-])/i', $key);
    }

    private function reportInternalFailure(string $message, ?\Throwable $exception = null): void
    {
        if (self::$reportingInternalFailure) {
            return;
        }

        self::$reportingInternalFailure = true;

        try {
            $suffix = $exception === null ? '' : ': ' . $exception::class . ' - ' . $exception->getMessage();
            \error_log('[honsa/sentry] ' . $message . $suffix);
        } catch (\Throwable) {
            // ignore secondary failure while reporting
        } finally {
            self::$reportingInternalFailure = false;
        }
    }
}
