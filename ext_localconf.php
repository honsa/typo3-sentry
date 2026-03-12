<?php

declare(strict_types=1);

use Psr\Log\LogLevel;
use Honsa\Sentry\Log\Writer\SentryLogWriter;

defined('TYPO3') || die();

$writerConfiguration = $GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'] ?? [];
$writerAlreadyConfigured = false;

foreach ($writerConfiguration as $writersForLevel) {
	if (\is_array($writersForLevel) && \array_key_exists(SentryLogWriter::class, $writersForLevel)) {
		$writerAlreadyConfigured = true;
		break;
	}
}

if (!$writerAlreadyConfigured) {
	$GLOBALS['TYPO3_CONF_VARS']['LOG']['writerConfiguration'][LogLevel::DEBUG][SentryLogWriter::class] = [];
}


