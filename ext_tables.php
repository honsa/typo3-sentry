<?php
defined('TYPO3') || die();

// Register the plugin in the backend plugin list so it appears in the Content Element wizard
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
	[
		'Sentry Test',
		'sentry_test',
		'sentry_test'
	],
	'list_type',
	'sentry'
);

// Register icon with Icon Registry
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
	'sentry_test',
	\TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
	['source' => 'EXT:sentry/Resources/Public/Icons/Extension.svg']
);
