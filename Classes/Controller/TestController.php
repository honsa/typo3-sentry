<?php

declare(strict_types=1);

namespace Honsa\Sentry\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TestController extends ActionController
{
    public function indexAction(): ResponseInterface
    {
        // Render a simple frontend page with buttons to trigger the test actions.
        $html = <<<'HTML'
<h1>Sentry Test Controller</h1>
<p>Click a button to trigger a test event. Responses will be shown below.</p>
<div id="buttons">
  <button data-action="triggerException">Throw Exception</button>
  <button data-action="triggerUserError">trigger_error(E_USER_ERROR)</button>
  <button data-action="triggerWarning">trigger_error(E_USER_WARNING)</button>
  <button data-action="triggerNotice">trigger_error(E_USER_NOTICE)</button>
  <button data-action="triggerCritical">Log critical via TYPO3 logger</button>
  <button data-action="triggerEmergency">Log emergency via TYPO3 logger</button>
  <button data-action="logMessages">Log warning, error, info</button>
</div>
<div id="result" style="margin-top:1rem;padding:0.5rem;border:1px solid #ddd;background:#fafafa"></div>
<script>
  (function () {
    const buttons = document.querySelectorAll('#buttons button');
    const result = document.getElementById('result');
    function show(msg) {
      const now = new Date().toISOString();
      result.innerHTML = '[' + now + '] ' + msg;
    }
    buttons.forEach(btn => btn.addEventListener('click', function () {
      const action = this.getAttribute('data-action');
      // Build form data matching Extbase frontend plugin namespace used by this site.
      const fd = new URLSearchParams();
      fd.append('tx_sentry_test[action]', action);
      fd.append('tx_sentry_test[controller]', 'Test');
      // POST to current path (keeping no_cache if present)
      const url = window.location.pathname + (window.location.search || '');
      show('Sending ' + action + '...');
      fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(t => show('Response: ' + t))
        .catch(e => show('Error: ' + e));
    }));
  }());
</script>
HTML;

        return $this->htmlResponse($html);
    }

    public function triggerExceptionAction(): ResponseInterface
    {
        throw new \RuntimeException('Test exception from Honsa Sentry TestController::triggerExceptionAction');
    }

    public function triggerUserErrorAction(): ResponseInterface
    {
        // E_USER_ERROR would be fatal (stop execution) which prevents returning a HTTP response
        // For testing Sentry / logging capture without halting the request we log an error instead.
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->error('Test user error (simulated E_USER_ERROR)');
        return $this->htmlResponse('User error logged');
    }

    public function triggerWarningAction(): ResponseInterface
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->warning('Test warning');
        return $this->htmlResponse('Warning logged');
    }

    public function triggerNoticeAction(): ResponseInterface
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->notice('Test notice');
        return $this->htmlResponse('Notice logged');
    }

    public function triggerCriticalAction(): ResponseInterface
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->critical('Test critical message from Sentry TestController');
        return $this->htmlResponse('Critical logged');
    }

    public function triggerEmergencyAction(): ResponseInterface
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->emergency('Test emergency message from Sentry TestController');
        return $this->htmlResponse('Emergency logged');
    }

    public function logMessagesAction(): ResponseInterface
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        $logger->warning('Test warning message');
        $logger->error('Test error message');
        $logger->info('Test info message');
        return $this->htmlResponse('Messages logged');
    }
}
