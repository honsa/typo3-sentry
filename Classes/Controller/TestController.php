<?php
declare(strict_types=1);

namespace Honsa\Sentry\Controller;

use Psr\Log\LogLevel;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TestController extends ActionController
{
    public function indexAction()
    {
        // Render a simple instruction page
    }

    public function triggerExceptionAction()
    {
        throw new \RuntimeException('Test exception from Honsa Sentry TestController::triggerExceptionAction');
    }

    public function triggerUserErrorAction()
    {
        // E_USER_ERROR
        trigger_error('Test user error', E_USER_ERROR);
    }

    public function triggerWarningAction()
    {
        trigger_error('Test warning', E_USER_WARNING);
    }

    public function triggerNoticeAction()
    {
        trigger_error('Test notice', E_USER_NOTICE);
    }

    public function triggerCriticalAction()
    {
        // Log as critical via TYPO3 logging
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->critical('Test critical message from Sentry TestController');
        $this->redirect('index');
    }

    public function triggerEmergencyAction()
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->emergency('Test emergency message from Sentry TestController');
        $this->redirect('index');
    }

    public function logMessagesAction()
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->warning('Test warning message');
        $logger->error('Test error message');
        $logger->info('Test info message');
        $this->redirect('index');
    }
}

