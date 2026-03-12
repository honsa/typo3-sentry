<?php

use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die();

$logger = GeneralUtility::makeInstance(LogManager::class)->getLogger('sentry');

// Log levels
$logger->emergency('Emergency', ['context' => 'data']);
$logger->alert('Alert', ['context' => 'data']);
$logger->critical('Critical', ['context' => 'data']);
$logger->error('Error', ['context' => 'data']);
$logger->warning('Warning', ['context' => 'data']);
$logger->notice('Notice', ['context' => 'data']);
$logger->info('Info', ['context' => 'data']);
$logger->debug('Debug', ['context' => 'data']);


