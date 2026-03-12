# Sentry Logger

A TYPO3 13 extension that forwards TYPO3 logs to Sentry via a configurable custom LogWriter.

- TYPO3: 13.4+
- PHP: 8.2+
- Library: sentry/sentry ^4.3

## Installation

- Require the package via Composer:

```bash
composer require honsa/sentry
```

- Install/activate the extension in TYPO3 (Extension Manager or Install Tool).
- Optionally set Sentry-related environment variables (see below).

## Configuration (Extension Configuration / Install Tool)

Settings correspond to entries in `ext_conf_template.txt` (dots and slashes are interchangeable). Defaults shown.

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| features.enable | boolean | 1 | Enable Sentry log forwarding. Set to 0 to disable all processing. |
| connection.dsn | string | (empty) | Sentry DSN. If empty, writer attempts env `SENTRY_DSN`; if still empty, writer is effectively disabled. |
| connection.environment | string | (empty) | Sentry environment. Fallback order: `connection.environment` > env `SENTRY_ENVIRONMENT` > env `APP_ENV` > `production`. |
| connection.release | string | (empty) | Release identifier (e.g. deploy hash). Fallback env `SENTRY_RELEASE`. |
| sampling.tracesSampleRate | float(string) | 0.0 | Performance tracing sample rate (0.0–1.0). Fallback env `SENTRY_TRACES_SAMPLE_RATE`. |
| sampling.profilesSampleRate | float(string) | 0.0 | Profiling sample rate (0.0–1.0). Fallback env `SENTRY_PROFILES_SAMPLE_RATE`. |
| filter.enabledLogLevels | string | error,critical,alert,emergency,warning | Comma-separated PSR-3 levels forwarded. Allowed: debug,info,notice,warning,error,critical,alert,emergency. |
| filter.captureExceptionsOnly | boolean | 0 | If 1, only records with `context['exception']` are forwarded. |
| context.staticTags | string | (empty) | Semicolon `key:value` pairs added as Sentry tags (e.g. `region:eu;cluster:www`). |
| context.staticExtras | string | (empty) | Semicolon `key:value` pairs added as Sentry extras. |

### Example minimal configuration

Enable forwarding only for errors and above, and add cluster tag:

```txt
features.enable = 1
connection.dsn = https://publicKey@o0.ingest.sentry.io/0
filter.enabledLogLevels = error,critical,alert,emergency
context.staticTags = region:eu;cluster:www
```

## Enable the writer

Keep or adjust your global logging in `config/system/additional.php`:

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

## Notes

- If DSN is empty or `features.enable = 0`, the writer short-circuits and does nothing.
- Exceptions present in `context['exception']` are captured via `captureException`; otherwise messages use `captureMessage` with mapped severity.
- All other context values become Sentry extras. Static tags and extras are always added when configured.
- Log level filtering uses the final, normalized comma list.

## Testing

- From the package directory:

```bash
composer test
# or with coverage (requires Xdebug)
composer test:coverage
```

Manual frontend test

1. Ensure the extension is installed and active in TYPO3 (Extension Manager or `./vendor/bin/typo3 extension:activate sentry`).
2. Set the DSN and environment for Sentry so the writer can initialize. Example (make sure these are visible to PHP-FPM/Apache):

```bash
export SENTRY_DSN="https://<publicKey>@o0.ingest.sentry.io/<projectId>"
export SENTRY_ENVIRONMENT="development"
```

3. Add the plugin to a page:
   - In the backend create a content element -> Plugins -> "Sentry Test" and save the page.
   - Or include the TypoScript snippet in your site template:

```typoscript
# example: include the plugin rendering
tt_content.list.20.sentry_test =< plugin.tx_sentry_test
```

4. Open the page in your browser and use the links in the "Sentry Test" plugin to trigger:
   - Exception (throws RuntimeException)
   - User error (E_USER_ERROR)
   - Warning (E_USER_WARNING)
   - Notice (E_USER_NOTICE)
   - Critical and Emergency (logged via TYPO3 logger)
   - Log multiple messages (warning, error, info)

5. Verify events arrive in Sentry (or check your PHP/TYPO3 logs if not).

Troubleshooting

- If no events appear, confirm `SENTRY_DSN` is set and visible to the PHP process. If using PHP-FPM, set the env in the FPM pool or webserver config.
- Ensure the extension is active and caches were flushed (`./vendor/bin/typo3 cache:flush` if available).
- Check that `features.enable` is enabled in the extension configuration or the `__config` passed in logging config.


