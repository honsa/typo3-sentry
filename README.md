
# Sentry Logger (concise)

A TYPO3 13 extension that forwards TYPO3 logs to Sentry via a configurable custom LogWriter.

- TYPO3: 13.4+
- PHP: 8.2+
- Library: sentry/sentry ^4.3

## Installation

Require the package via Composer and activate the extension in TYPO3:

```bash
composer require honsa/sentry
./vendor/bin/typo3 extension:activate sentry
```

## Configuration (extension settings)

The extension reads its configuration from the Extension Configuration (Install Tool / Extension Manager). The `ext_conf_template.txt` shipped with the extension exposes the keys supported by the UI; the table below lists those keys.

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| features.enable | boolean | 1 | Enable Sentry log forwarding. Set to 0 to disable processing. |
| connection.dsn | string | (empty) | Sentry DSN. If empty, the writer will try env `SENTRY_DSN` and will be effectively disabled without a DSN. |

Notes:

If `features.enable = 0` or no DSN is available, the writer short-circuits and does nothing.


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
        SentryLogWriter::class => [],
    ],
    LogLevel::WARNING => [
        SentryLogWriter::class => [],
    ],
];
```

## Testing

Run the package tests from the package directory:

```bash
composer test
```



