
# Sentry Logger (concise)

A TYPO3 13 extension that forwards TYPO3 logs to Sentry via a configurable custom LogWriter.

- TYPO3: 13.4+
- PHP: 8.2+
- Library: sentry/sentry ^4.3

## Installation

Require the package via Composer and activate the extension in TYPO3:

```bash
composer require honsa/sentry
./vendor/bin/typo3 extension:activate sentry_logger
```

TYPO3 extension key: `sentry_logger`

## Configuration (extension settings)

The extension reads its configuration from the Extension Configuration (Install Tool / Extension Manager). The `ext_conf_template.txt` shipped with the extension exposes the supported public keys, and the table below mirrors that contract.

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| features.enable | boolean | 1 | Enable Sentry log forwarding. Set to 0 to disable processing. |
| connection.dsn | string | (empty) | Sentry DSN. If empty, the writer will try env `SENTRY_DSN` and will be effectively disabled without a DSN. |
| connection.environment | string | `SENTRY_ENVIRONMENT`, then `APP_ENV`, then `production` | Environment attached to Sentry events. |
| connection.release | string | `SENTRY_RELEASE`, otherwise empty | Release identifier attached to Sentry events. |
| sampling.tracesSampleRate | float | 0.0 | Trace sampling rate passed to the Sentry SDK. |
| sampling.profilesSampleRate | float | 0.0 | Profile sampling rate passed to the Sentry SDK. |
| filter.enabledLogLevels | string | `debug,info,notice,warning,error,critical,alert,emergency` | Comma-separated PSR-3 levels that are forwarded. |
| filter.captureExceptionsOnly | boolean | 0 | If enabled, only records that contain an `exception` in the log context are forwarded. |
| context.staticTags | string | (empty) | Semicolon-separated `key:value` tags added to every Sentry event. Avoid secrets. |
| context.staticExtras | string | (empty) | Semicolon-separated `key:value` extras added to every Sentry event. Secret-like keys are redacted automatically. |

Notes:

If `features.enable = 0` or no DSN is available, the writer short-circuits and does nothing.

By default the writer forwards all PSR-3 log levels. To restrict traffic to Sentry, narrow `filter.enabledLogLevels` in the extension configuration.

Security behavior:

- Log context is sanitized before being sent to Sentry. Keys such as `password`, `token`, `authorization`, `cookie`, `session`, `csrf`, `api_key`, and similar secret-like names are replaced with `[REDACTED]`, including in nested arrays and `JsonSerializable` payloads.
- Internal writer failures are reported via PHP's error log to avoid silently losing telemetry while also avoiding TYPO3 log-recursion.


## Enable the writer

Adjust your global logging config if needed (example in `config/system/additional.php`):

```php
<?php
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\Writer\RotatingFileWriter;
use Honsa\Sentry\Log\Writer\SentryLogWriter;

$GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] = [
    LogLevel::ERROR => [
        RotatingFileWriter::class => [
            'logFile' => Environment::getVarPath() . '/log/typo3-core-error.log',
        ],
    ],
    LogLevel::WARNING => [
        RotatingFileWriter::class => [
            'logFile' => Environment::getVarPath() . '/log/typo3-core-warning.log',
        ],
    ],
    LogLevel::DEBUG => [
        SentryLogWriter::class => [],
    ],
];
```

Registering `SentryLogWriter` under `LogLevel::DEBUG` allows TYPO3 to pass all PSR-3 levels (`debug` through `emergency`) to the writer.

## Testing

Run the package tests from the package directory:

```bash
composer test
```



