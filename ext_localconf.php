<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Honsa\Sentry\Controller\TestController;

// Register Extbase plugin with extension name (must match TypoScript extensionName)
ExtensionUtility::configurePlugin(
	'Sentry',
	'Test',
	[
		TestController::class => 'index,triggerException,triggerUserError,triggerWarning,triggerNotice,triggerCritical,triggerEmergency,logMessages',
	],
	// non-cacheable actions
	[
		TestController::class => 'triggerException,triggerUserError,triggerWarning,triggerNotice,triggerCritical,triggerEmergency,logMessages',
	]
);

// Load TypoScript configuration
ExtensionManagementUtility::addTypoScriptSetup(
	file_get_contents(\TYPO3\CMS\Core\Utility\GeneralUtility::getFileAbsFileName('EXT:sentry/Configuration/TypoScript/setup.typoscript'))
);

// Bridge PHP native errors to TYPO3 LogManager so the SentryLogWriter receives them.
// This helps capture trigger_error() calls via the logging system and forward them to Sentry.
call_user_func(function (): void {
	if (\function_exists('set_error_handler')) {
		set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
			// Respect error suppression via @
			if (!(error_reporting() & $errno)) {
				return false; // let PHP handle suppressed errors
			}

			$levelMap = [
				E_ERROR => \Psr\Log\LogLevel::ERROR,
				E_USER_ERROR => \Psr\Log\LogLevel::ERROR,
				E_RECOVERABLE_ERROR => \Psr\Log\LogLevel::ERROR,

				// Map notices and deprecated to WARNING so they are captured by default writer filters
				E_WARNING => \Psr\Log\LogLevel::WARNING,
				E_USER_WARNING => \Psr\Log\LogLevel::WARNING,
				E_NOTICE => \Psr\Log\LogLevel::WARNING,
				E_USER_NOTICE => \Psr\Log\LogLevel::WARNING,
				E_STRICT => \Psr\Log\LogLevel::WARNING,
				E_DEPRECATED => \Psr\Log\LogLevel::WARNING,
				E_USER_DEPRECATED => \Psr\Log\LogLevel::WARNING,
			];

			$level = $levelMap[$errno] ?? \Psr\Log\LogLevel::ERROR;

			try {
				$logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger('php_error');
				$message = sprintf('%s in %s:%d', $errstr, $errfile, $errline);
				$logger->log($level, $message, ['errno' => $errno]);
			} catch (\Throwable $e) {
				// If logging fails, fall back to PHP internal handler
				return false;
			}

			// Returning false lets PHP also handle the error (display or convert to exception) if configured.
			// We still log it above so Sentry receives it via the logging pipeline.
			return false;
		});
	}
});



