<?php

declare(strict_types=1);

namespace Honsa\Sentry\Tests\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Sentry\ClientInterface;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use TYPO3\CMS\Core\Log\LogRecord;
use Honsa\Sentry\Log\Writer\SentryLogWriter;

class SentryLogWriterTest extends TestCase
{
    protected function setUp(): void
    {
        SentrySdk::setCurrentHub(new Hub(null));
    }

    protected function tearDown(): void
    {
        SentrySdk::setCurrentHub(new Hub(null));
    }

    private function make(array $cfg): SentryLogWriter
    {
        return new SentryLogWriter(['__config' => $cfg, '__disableInit' => true]);
    }

    private function setProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $propertyName);
        $reflection->setValue($object, $value);
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionProperty($object, $propertyName);
        return $reflection->getValue($object);
    }

    private function attachHub(SentryLogWriter $writer, HubInterface $hub): void
    {
        SentrySdk::setCurrentHub($hub);
        $this->setProperty($writer, 'hub', $hub);
    }

    public function testDisabledWriterSkipsProcessing(): void
    {
        $writer = $this->make(['features' => ['enable' => 0]]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Message');
        self::assertSame($writer, $writer->writeLog($record));
        $getter = \Closure::bind(function (string $name) {
            return $this->$name;
        }, $writer, \get_class($writer));
        self::assertFalse($getter('enabled'));
    }

    public function testLevelFiltering(): void
    {
        $writer = $this->make(['features' => ['enable' => 1], 'filter' => ['enabledLogLevels' => 'error,critical']]);
        $getter = \Closure::bind(function (string $name) {
            return $this->$name;
        }, $writer, \get_class($writer));
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

    public function testDefaultConfigurationForwardsWarningLevelMessages(): void
    {
        $writer = $this->make(['features.enable' => 1]);

        $client = $this->createMock(ClientInterface::class);
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn($client);
        $hub->expects(self::once())
            ->method('withScope')
            ->willReturnCallback(static fn (callable $callback) => $callback(new Scope()));
        $hub->expects(self::once())
            ->method('captureMessage')
            ->with('Warning should reach Sentry', self::anything())
            ->willReturn(null);

        $this->attachHub($writer, $hub);

        self::assertSame($writer, $writer->writeLog(new LogRecord('test', LogLevel::WARNING, 'Warning should reach Sentry')));
    }

    public function testSensitiveContextKeysAreRedactedBeforeSendingToSentry(): void
    {
        $writer = $this->make([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error',
            'context.staticExtras' => 'api_key:top-secret;safe:ok',
            'context.staticTags' => 'authorization:Bearer abc;region:eu',
        ]);

        $client = $this->createMock(ClientInterface::class);
        $scope = null;
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn($client);
        $hub->expects(self::once())
            ->method('withScope')
            ->willReturnCallback(function (callable $callback) use (&$scope) {
                $scope = new Scope();
                return $callback($scope);
            });
        $hub->expects(self::once())
            ->method('captureMessage')
            ->willReturn(null);

        $this->attachHub($writer, $hub);

        $writer->writeLog(new LogRecord('test', LogLevel::ERROR, 'Sensitive context', [
            'password' => 'secret-value',
            'profile' => [
                'authorization' => 'Bearer token',
                'safe' => 'visible',
            ],
            'payload' => new class () implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return [
                        'api_key' => 'abc123',
                        'name' => 'Jane Doe',
                    ];
                }
            },
        ]));

        self::assertInstanceOf(Scope::class, $scope);

        $extra = $this->getProperty($scope, 'extra');
        $tags = $this->getProperty($scope, 'tags');

        self::assertSame('[REDACTED]', $extra['password']);
        self::assertSame('[REDACTED]', $extra['profile']['authorization']);
        self::assertSame('visible', $extra['profile']['safe']);
        self::assertSame('[REDACTED]', $extra['payload']['api_key']);
        self::assertSame('Jane Doe', $extra['payload']['name']);
        self::assertSame('[REDACTED]', $extra['api_key']);
        self::assertSame('ok', $extra['safe']);
        self::assertSame('[REDACTED]', $tags['authorization']);
        self::assertSame('eu', $tags['region']);
    }

    public function testWriteFailuresAreReportedToPhpErrorLog(): void
    {
        $writer = $this->make(['features.enable' => 1, 'filter.enabledLogLevels' => 'error']);

        $client = $this->createMock(ClientInterface::class);
        $hub = $this->createMock(HubInterface::class);
        $hub->method('getClient')->willReturn($client);
        $hub->expects(self::once())
            ->method('withScope')
            ->willThrowException(new \RuntimeException('boom'));
        $hub->expects(self::never())->method('captureMessage');

        $this->attachHub($writer, $hub);

        $logFile = \tempnam(\sys_get_temp_dir(), 'sentry-writer-');
        self::assertNotFalse($logFile);

        $previousErrorLog = \ini_get('error_log');
        $previousLogErrors = \ini_get('log_errors');
        \ini_set('log_errors', '1');
        \ini_set('error_log', $logFile);

        try {
            self::assertSame($writer, $writer->writeLog(new LogRecord('test', LogLevel::ERROR, 'Boom')));

            $contents = (string)\file_get_contents($logFile);
            self::assertStringContainsString('Failed to forward TYPO3 log record to Sentry', $contents);
            self::assertStringContainsString('RuntimeException - boom', $contents);
        } finally {
            \ini_set('log_errors', (string)$previousLogErrors);
            \ini_set('error_log', $previousErrorLog === false ? '' : (string)$previousErrorLog);
            @\unlink($logFile);
        }
    }

    #[RunInSeparateProcess]
    public function testTestingDisableInitIsNotAcceptedAsPublicConfiguration(): void
    {
        SentrySdk::setCurrentHub(new Hub(null));

        try {
            new SentryLogWriter([
                '__config' => [
                    'features.enable' => 1,
                    'connection.dsn' => 'https://public@example.com/1',
                    'testing.disableInit' => true,
                ],
            ]);

            self::assertNotNull(SentrySdk::getCurrentHub()->getClient());
        } finally {
            if (\function_exists('sentry_disable_global_handlers')) {
                \sentry_disable_global_handlers();
            }
            SentrySdk::setCurrentHub(new Hub(null));
        }
    }

    public function testInternalDisableInitOptionSkipsClientInitialization(): void
    {
        SentrySdk::setCurrentHub(new Hub(null));

        new SentryLogWriter([
            '__config' => [
                'features.enable' => 1,
                'connection.dsn' => 'https://public@example.com/1',
            ],
            '__disableInit' => true,
        ]);

        self::assertNull(SentrySdk::getCurrentHub()->getClient());
    }
}
