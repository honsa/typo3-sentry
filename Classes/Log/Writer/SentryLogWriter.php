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
    private HubInterface $hub;
    private array $config;
    private bool $enabled = true;
    private array $enabledLevels = [];
    private bool $exceptionsOnly = false;
    private array $staticTags = [];
    private array $staticExtras = [];

    public function __construct(array $options = [])
    {
        // Extract internal test configuration before parent processing to avoid invalid option exception
        $internalConfig = [];
        if (isset($options['__config']) && \is_array($options['__config'])) {
            $internalConfig = $options['__config'];
            unset($options['__config']);
        }
        // No supported writer-specific options are currently used; skip parent constructor if only internal config provided
        if ($options !== []) {
            parent::__construct($options); // will validate recognized set* methods if ever added
        }
        $this->config = $internalConfig !== [] ? $internalConfig : $this->loadExtensionConfig();
        $this->enabled = (bool)$this->getConfigValue('features.enable', true);
        $this->exceptionsOnly = (bool)$this->getConfigValue('filter.captureExceptionsOnly', false);
        $this->enabledLevels = $this->enabled ? $this->parseList((string)$this->getConfigValue('filter.enabledLogLevels', 'error,critical,alert,emergency,warning')) : [];
        $this->staticTags = $this->parsePairs((string)$this->getConfigValue('context.staticTags', ''));
        $this->staticExtras = $this->parsePairs((string)$this->getConfigValue('context.staticExtras', ''));

        $disableInitForTests = (bool)$this->getConfigValue('testing.disableInit', false);

        if ($this->enabled && !$disableInitForTests) {
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
                    $scope->setTag($k, $v);
                }
                foreach ($this->staticExtras as $k => $v) {
                    $scope->setExtra($k, $v);
                }
                foreach ($context as $key => $value) {
                    if ($key === 'exception') {
                        continue;
                    }
                    if (\is_scalar($value) || $value === null) {
                        $scope->setExtra((string)$key, $value);
                    } elseif ($value instanceof \JsonSerializable) {
                        $scope->setExtra((string)$key, $value->jsonSerialize());
                    } else {
                        $encoded = \json_encode($value);
                        $scope->setExtra((string)$key, $encoded !== false ? $encoded : (string)$value);
                    }
                }
            });

            if ($exception instanceof \Throwable) {
                \Sentry\captureException($exception);
            } else {
                \Sentry\captureMessage($message, $severity);
            }
        } catch (\Throwable $e) {
            // swallow
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
            // Read configuration using the extension key 'honsa_sentry'
            $cfg = $extConfig->get('honsa_sentry');
            return \is_array($cfg) ? $cfg : [];
        } catch (\Throwable $e) {
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
}
