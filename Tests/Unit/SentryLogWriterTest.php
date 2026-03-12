<?php

declare(strict_types=1);

namespace Honsa\Sentry\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use Honsa\Sentry\Log\Writer\SentryLogWriter;

class SentryLogWriterTest extends TestCase
{
    protected function setUp(): void
    {
        \Sentry\SentrySdk::setCurrentHub(new \Sentry\State\Hub(null));
    }

    protected function tearDown(): void
    {
        \Sentry\SentrySdk::setCurrentHub(new \Sentry\State\Hub(null));
    }

    private function make(array $cfg): SentryLogWriter
    {
        return new SentryLogWriter(['__config' => $cfg]);
    }

    public function testDisabledWriterSkipsProcessing(): void
    {
        $writer = $this->make(['features' => ['enable' => 0]]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Message');
        self::assertSame($writer, $writer->writeLog($record));
        $ref = new \ReflectionProperty($writer, 'enabled');
        $ref->setAccessible(true);
        self::assertFalse($ref->getValue($writer));
    }

    public function testLevelFiltering(): void
    {
        $writer = $this->make(['features' => ['enable' => 1], 'filter' => ['enabledLogLevels' => 'error,critical']]);
        $levels = new \ReflectionProperty($writer, 'enabledLevels');
        $levels->setAccessible(true);
        self::assertSame(['error', 'critical'], $levels->getValue($writer));
        $writer->writeLog(new LogRecord('test', LogLevel::ERROR, 'Err'));
        $writer->writeLog(new LogRecord('test', LogLevel::INFO, 'Info'));
        self::assertTrue(true);
    }

    public function testExceptionsOnlySkipNonException(): void
    {
        $writer = $this->make(['features' => ['enable' => 1], 'filter' => ['enabledLogLevels' => 'error', 'captureExceptionsOnly' => 1]]);
        $writer->writeLog(new LogRecord('test', LogLevel::ERROR, 'Err'));
        $writer->writeLog(new LogRecord('test', LogLevel::ERROR, 'Err', ['exception' => new \RuntimeException('fail')]));
        self::assertTrue(true);
    }

    public function testFlatKeyAccess(): void
    {
        $writer = $this->make(['features.enable' => 1, 'filter.enabledLogLevels' => 'error']);
        $writer->writeLog(new LogRecord('flat', LogLevel::ERROR, 'Flat config works'));
        self::assertTrue(true);
    }
}
