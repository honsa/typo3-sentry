<?php
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Honsa\Sentry\Controller\TestController;

// Register a simple Extbase plugin to test Sentry integration from the frontend
ExtensionUtility::configurePlugin(
	'Honsa.Sentry',
	'Test',
	[
		TestController::class => 'index,triggerException,triggerUserError,triggerWarning,triggerNotice,triggerCritical,triggerEmergency,logMessages',
	],
	// non-cacheable actions
	[
		TestController::class => 'triggerException,triggerUserError,triggerWarning,triggerNotice,triggerCritical,triggerEmergency,logMessages',
	]
);

// Register the plugin in the backend plugin list (so editors can add it)
// This is optional for testing via TypoScript/Fluid but helpful for completeness.
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	[
		'Sentry Test',
		'honsasentry_test',
		'EXT:honsa_sentry/Resources/Public/Icons/Extension.svg'
	],
	'list_type',
	'honsa_sentry'
);

