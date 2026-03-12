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
        // Prevent the writer from calling \Sentry\init() during unit tests by
        // defaulting testing.disableInit to true unless the test explicitly
        // provides the flag (either as flat key or nested array).
        if (!array_key_exists('testing.disableInit', $cfg)) {
            if (!(isset($cfg['testing']) && is_array($cfg['testing']) && array_key_exists('disableInit', $cfg['testing']))) {
                $cfg['testing.disableInit'] = true;
            }
        }
        return new SentryLogWriter(['__config' => $cfg]);
    }

    public function testDisabledWriterSkipsProcessing(): void
    {
        $writer = $this->make(['features' => ['enable' => 0]]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Message');
        self::assertSame($writer, $writer->writeLog($record));
        $getter = \Closure::bind(function (string $name) {
            return $this->$name;
        }, $writer, get_class($writer));
        self::assertFalse($getter('enabled'));
    }

    public function testLevelFiltering(): void
    {
        $writer = $this->make(['features' => ['enable' => 1], 'filter' => ['enabledLogLevels' => 'error,critical']]);
        $getter = \Closure::bind(function (string $name) {
            return $this->$name;
        }, $writer, get_class($writer));
        self::assertSame(['error', 'critical'], $getter('enabledLevels'));
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
