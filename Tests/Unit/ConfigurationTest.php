<?php

declare(strict_types=1);

namespace Honsa\Sentry\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use Honsa\Sentry\Log\Writer\SentryLogWriter;

/**
 * Test all configuration options from ext_conf_template.txt
 * Each configuration setting should be tested for:
 * - Default value
 * - Custom value
 * - Edge cases
 */
class ConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset Sentry hub before each test and ensure no global handlers remain
        \Sentry\SentrySdk::setCurrentHub(new \Sentry\State\Hub(null));
        if (\function_exists('sentry_disable_global_handlers')) {
            \sentry_disable_global_handlers();
        }
    }

    protected function tearDown(): void
    {
        // Reset hub and also disable global handlers after each test
        \Sentry\SentrySdk::setCurrentHub(new \Sentry\State\Hub(null));
        if (\function_exists('sentry_disable_global_handlers')) {
            \sentry_disable_global_handlers();
        }
    }

    /**
     * Create a SentryLogWriter with test configuration
     */
    private function createWriter(array $config): SentryLogWriter
    {
        // Mark that we are in a test context so SentryLogWriter skips Sentry::init()
        $config['testing.disableInit'] = true;
        return new SentryLogWriter(['__config' => $config]);
    }

    /**
     * Get a private/protected property value using reflection
     */
    private function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionProperty($object, $propertyName);
        $reflection->setAccessible(true);
        return $reflection->getValue($object);
    }

    // ===========================
    // features.enable
    // ===========================

    public function testFeatureEnableDefaultTrue(): void
    {
        $writer = $this->createWriter([]);
        self::assertTrue($this->getProperty($writer, 'enabled'));
    }

    public function testFeatureEnableExplicitTrue(): void
    {
        $writer = $this->createWriter(['features.enable' => 1]);
        self::assertTrue($this->getProperty($writer, 'enabled'));
    }

    public function testFeatureEnableExplicitTrueString(): void
    {
        $writer = $this->createWriter(['features.enable' => '1']);
        self::assertTrue($this->getProperty($writer, 'enabled'));
    }

    public function testFeatureEnableCanBeDisabled(): void
    {
        $writer = $this->createWriter(['features.enable' => 0]);
        self::assertFalse($this->getProperty($writer, 'enabled'));
    }

    public function testFeatureEnableDisabledWithString(): void
    {
        $writer = $this->createWriter(['features.enable' => '0']);
        self::assertFalse($this->getProperty($writer, 'enabled'));
    }

    public function testFeatureEnableDisabledSkipsLogging(): void
    {
        $writer = $this->createWriter(['features.enable' => 0]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Error message');
        $result = $writer->writeLog($record);
        // Should return self without processing
        self::assertSame($writer, $result);
    }

    public function testFeatureEnableNestedArrayFormat(): void
    {
        $writer = $this->createWriter(['features' => ['enable' => 1]]);
        self::assertTrue($this->getProperty($writer, 'enabled'));
    }

    // ===========================
    // connection.dsn
    // ===========================

    public function testConnectionDsnDefaultEmpty(): void
    {
        $writer = $this->createWriter([]);
        $config = $this->getProperty($writer, 'config');
        self::assertEmpty($config['connection.dsn'] ?? ($config['connection']['dsn'] ?? ''));
    }

    public function testConnectionDsnCustomValue(): void
    {
        $dsn = 'https://public@o12345.ingest.sentry.io/67890';
        $writer = $this->createWriter(['connection.dsn' => $dsn]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame($dsn, $config['connection.dsn']);
    }

    public function testConnectionDsnNestedFormat(): void
    {
        $dsn = 'https://key@sentry.io/123';
        $writer = $this->createWriter(['connection' => ['dsn' => $dsn]]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame($dsn, $config['connection']['dsn']);
    }

    // ===========================
    // connection.environment
    // ===========================

    public function testConnectionEnvironmentDefaultEmpty(): void
    {
        $writer = $this->createWriter([]);
        $config = $this->getProperty($writer, 'config');
        self::assertEmpty($config['connection.environment'] ?? ($config['connection']['environment'] ?? ''));
    }

    public function testConnectionEnvironmentProduction(): void
    {
        $writer = $this->createWriter(['connection.environment' => 'production']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('production', $config['connection.environment']);
    }

    public function testConnectionEnvironmentStaging(): void
    {
        $writer = $this->createWriter(['connection.environment' => 'staging']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('staging', $config['connection.environment']);
    }

    public function testConnectionEnvironmentDevelopment(): void
    {
        $writer = $this->createWriter(['connection.environment' => 'development']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('development', $config['connection.environment']);
    }

    public function testConnectionEnvironmentNestedFormat(): void
    {
        $writer = $this->createWriter(['connection' => ['environment' => 'testing']]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('testing', $config['connection']['environment']);
    }

    // ===========================
    // connection.release
    // ===========================

    public function testConnectionReleaseDefaultEmpty(): void
    {
        $writer = $this->createWriter([]);
        $config = $this->getProperty($writer, 'config');
        self::assertEmpty($config['connection.release'] ?? ($config['connection']['release'] ?? ''));
    }

    public function testConnectionReleaseSemanticVersion(): void
    {
        $writer = $this->createWriter(['connection.release' => '1.2.3']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('1.2.3', $config['connection.release']);
    }

    public function testConnectionReleaseGitCommit(): void
    {
        $writer = $this->createWriter(['connection.release' => 'abc123def']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('abc123def', $config['connection.release']);
    }

    public function testConnectionReleaseComplexVersion(): void
    {
        $writer = $this->createWriter(['connection.release' => 'v2.5.0-rc1+build.456']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('v2.5.0-rc1+build.456', $config['connection.release']);
    }

    public function testConnectionReleaseNestedFormat(): void
    {
        $writer = $this->createWriter(['connection' => ['release' => '3.0.0']]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('3.0.0', $config['connection']['release']);
    }

    // ===========================
    // sampling.tracesSampleRate
    // ===========================

    public function testSamplingTracesSampleRateDefaultZero(): void
    {
        $writer = $this->createWriter([]);
        // Default should be 0.0 as per ext_conf_template.txt
        $config = $this->getProperty($writer, 'config');
        $rate = $config['sampling.tracesSampleRate'] ?? ($config['sampling']['tracesSampleRate'] ?? 0.0);
        self::assertSame(0.0, (float)$rate);
    }

    public function testSamplingTracesSampleRateHalf(): void
    {
        $writer = $this->createWriter(['sampling.tracesSampleRate' => '0.5']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.5', $config['sampling.tracesSampleRate']);
    }

    public function testSamplingTracesSampleRateFull(): void
    {
        $writer = $this->createWriter(['sampling.tracesSampleRate' => '1.0']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('1.0', $config['sampling.tracesSampleRate']);
    }

    public function testSamplingTracesSampleRateQuarter(): void
    {
        $writer = $this->createWriter(['sampling.tracesSampleRate' => '0.25']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.25', $config['sampling.tracesSampleRate']);
    }

    public function testSamplingTracesSampleRateNestedFormat(): void
    {
        $writer = $this->createWriter(['sampling' => ['tracesSampleRate' => '0.75']]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.75', $config['sampling']['tracesSampleRate']);
    }

    // ===========================
    // sampling.profilesSampleRate
    // ===========================

    public function testSamplingProfilesSampleRateDefaultZero(): void
    {
        $writer = $this->createWriter([]);
        $config = $this->getProperty($writer, 'config');
        $rate = $config['sampling.profilesSampleRate'] ?? ($config['sampling']['profilesSampleRate'] ?? 0.0);
        self::assertSame(0.0, (float)$rate);
    }

    public function testSamplingProfilesSampleRateHalf(): void
    {
        $writer = $this->createWriter(['sampling.profilesSampleRate' => '0.5']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.5', $config['sampling.profilesSampleRate']);
    }

    public function testSamplingProfilesSampleRateFull(): void
    {
        $writer = $this->createWriter(['sampling.profilesSampleRate' => '1.0']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('1.0', $config['sampling.profilesSampleRate']);
    }

    public function testSamplingProfilesSampleRateZero(): void
    {
        $writer = $this->createWriter(['sampling.profilesSampleRate' => '0.0']);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.0', $config['sampling.profilesSampleRate']);
    }

    public function testSamplingProfilesSampleRateNestedFormat(): void
    {
        $writer = $this->createWriter(['sampling' => ['profilesSampleRate' => '0.1']]);
        $config = $this->getProperty($writer, 'config');
        self::assertSame('0.1', $config['sampling']['profilesSampleRate']);
    }

    // ===========================
    // filter.enabledLogLevels
    // ===========================

    public function testFilterEnabledLogLevelsDefault(): void
    {
        $writer = $this->createWriter(['features.enable' => 1]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        // Default: error,critical,alert,emergency,warning
        self::assertIsArray($levels);
        self::assertContains('error', $levels);
        self::assertContains('critical', $levels);
        self::assertContains('alert', $levels);
        self::assertContains('emergency', $levels);
        self::assertContains('warning', $levels);
    }

    public function testFilterEnabledLogLevelsDefaultOrder(): void
    {
        $writer = $this->createWriter(['features.enable' => 1]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        // Verify all 5 default levels are present
        self::assertCount(5, $levels);
    }

    public function testFilterEnabledLogLevelsOnlyErrors(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error'
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertSame(['error'], $levels);
    }

    public function testFilterEnabledLogLevelsErrorAndCritical(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error,critical'
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertSame(['error', 'critical'], $levels);
    }

    public function testFilterEnabledLogLevelsAllLevels(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'debug,info,notice,warning,error,critical,alert,emergency'
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertCount(8, $levels);
        self::assertContains('debug', $levels);
        self::assertContains('info', $levels);
        self::assertContains('notice', $levels);
    }

    public function testFilterEnabledLogLevelsWithSpaces(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => ' error , warning , critical '
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertSame(['error', 'warning', 'critical'], $levels);
    }

    public function testFilterEnabledLogLevelsEmpty(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => ''
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertEmpty($levels);
    }

    public function testFilterEnabledLogLevelsNestedFormat(): void
    {
        $writer = $this->createWriter([
            'features' => ['enable' => 1],
            'filter' => ['enabledLogLevels' => 'error,warning']
        ]);
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertSame(['error', 'warning'], $levels);
    }

    public function testFilterEnabledLogLevelsFiltersOutInfo(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error'
        ]);
        $record = new LogRecord('test', LogLevel::INFO, 'Info message');
        $result = $writer->writeLog($record);
        // Should return without processing
        self::assertSame($writer, $result);
    }

    public function testFilterEnabledLogLevelsAcceptsError(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error'
        ]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Error message');
        $result = $writer->writeLog($record);
        self::assertSame($writer, $result);
    }

    // ===========================
    // filter.captureExceptionsOnly
    // ===========================

    public function testFilterCaptureExceptionsOnlyDefaultFalse(): void
    {
        $writer = $this->createWriter([]);
        self::assertFalse($this->getProperty($writer, 'exceptionsOnly'));
    }

    public function testFilterCaptureExceptionsOnlyExplicitFalse(): void
    {
        $writer = $this->createWriter(['filter.captureExceptionsOnly' => 0]);
        self::assertFalse($this->getProperty($writer, 'exceptionsOnly'));
    }

    public function testFilterCaptureExceptionsOnlyTrue(): void
    {
        $writer = $this->createWriter(['filter.captureExceptionsOnly' => 1]);
        self::assertTrue($this->getProperty($writer, 'exceptionsOnly'));
    }

    public function testFilterCaptureExceptionsOnlyTrueString(): void
    {
        $writer = $this->createWriter(['filter.captureExceptionsOnly' => '1']);
        self::assertTrue($this->getProperty($writer, 'exceptionsOnly'));
    }

    public function testFilterCaptureExceptionsOnlyNestedFormat(): void
    {
        $writer = $this->createWriter(['filter' => ['captureExceptionsOnly' => 1]]);
        self::assertTrue($this->getProperty($writer, 'exceptionsOnly'));
    }

    public function testFilterCaptureExceptionsOnlySkipsNonExceptionMessages(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error',
            'filter.captureExceptionsOnly' => 1
        ]);
        $record = new LogRecord('test', LogLevel::ERROR, 'Error without exception');
        $result = $writer->writeLog($record);
        self::assertSame($writer, $result);
    }

    public function testFilterCaptureExceptionsOnlyAcceptsExceptions(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'filter.enabledLogLevels' => 'error',
            'filter.captureExceptionsOnly' => 1
        ]);
        $exception = new \RuntimeException('Test exception');
        $record = new LogRecord('test', LogLevel::ERROR, 'Error with exception', ['exception' => $exception]);
        $result = $writer->writeLog($record);
        self::assertSame($writer, $result);
    }

    // ===========================
    // context.staticTags
    // ===========================

    public function testContextStaticTagsDefaultEmpty(): void
    {
        $writer = $this->createWriter([]);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertEmpty($tags);
    }

    public function testContextStaticTagsEmptyString(): void
    {
        $writer = $this->createWriter(['context.staticTags' => '']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertEmpty($tags);
    }

    public function testContextStaticTagsSingleTag(): void
    {
        $writer = $this->createWriter(['context.staticTags' => 'region:eu']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['region' => 'eu'], $tags);
    }

    public function testContextStaticTagsMultipleTags(): void
    {
        $writer = $this->createWriter(['context.staticTags' => 'region:eu;cluster:www']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['region' => 'eu', 'cluster' => 'www'], $tags);
    }

    public function testContextStaticTagsWithSpaces(): void
    {
        $writer = $this->createWriter(['context.staticTags' => ' region : eu ; cluster : www ']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['region' => 'eu', 'cluster' => 'www'], $tags);
    }

    public function testContextStaticTagsMultipleValues(): void
    {
        $writer = $this->createWriter(['context.staticTags' => 'env:prod;dc:zh;app:cms']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['env' => 'prod', 'dc' => 'zh', 'app' => 'cms'], $tags);
    }

    public function testContextStaticTagsWithColonInValue(): void
    {
        $writer = $this->createWriter(['context.staticTags' => 'url:https://example.com']);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['url' => 'https://example.com'], $tags);
    }

    public function testContextStaticTagsNestedFormat(): void
    {
        $writer = $this->createWriter(['context' => ['staticTags' => 'key:value']]);
        $tags = $this->getProperty($writer, 'staticTags');
        self::assertSame(['key' => 'value'], $tags);
    }

    public function testContextStaticTagsIgnoresInvalidFormat(): void
    {
        $writer = $this->createWriter(['context.staticTags' => 'valid:value;invalid;another:good']);
        $tags = $this->getProperty($writer, 'staticTags');
        // Should only parse valid key:value pairs
        self::assertArrayHasKey('valid', $tags);
        self::assertArrayHasKey('another', $tags);
        self::assertArrayNotHasKey('invalid', $tags);
    }

    // ===========================
    // context.staticExtras
    // ===========================

    public function testContextStaticExtrasDefaultEmpty(): void
    {
        $writer = $this->createWriter([]);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertEmpty($extras);
    }

    public function testContextStaticExtrasEmptyString(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => '']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertEmpty($extras);
    }

    public function testContextStaticExtrasSingleExtra(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => 'app:frontend']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['app' => 'frontend'], $extras);
    }

    public function testContextStaticExtrasMultipleExtras(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => 'app:frontend;source:typo3']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['app' => 'frontend', 'source' => 'typo3'], $extras);
    }

    public function testContextStaticExtrasWithSpaces(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => ' app : frontend ; source : typo3 ']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['app' => 'frontend', 'source' => 'typo3'], $extras);
    }

    public function testContextStaticExtrasMultipleValues(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => 'version:13.4;php:8.2;server:nginx']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['version' => '13.4', 'php' => '8.2', 'server' => 'nginx'], $extras);
    }

    public function testContextStaticExtrasWithColonInValue(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => 'api:https://api.example.com']);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['api' => 'https://api.example.com'], $extras);
    }

    public function testContextStaticExtrasNestedFormat(): void
    {
        $writer = $this->createWriter(['context' => ['staticExtras' => 'extra:data']]);
        $extras = $this->getProperty($writer, 'staticExtras');
        self::assertSame(['extra' => 'data'], $extras);
    }

    public function testContextStaticExtrasIgnoresInvalidFormat(): void
    {
        $writer = $this->createWriter(['context.staticExtras' => 'valid:value;invalid;another:good']);
        $extras = $this->getProperty($writer, 'staticExtras');
        // Should only parse valid key:value pairs
        self::assertArrayHasKey('valid', $extras);
        self::assertArrayHasKey('another', $extras);
        self::assertArrayNotHasKey('invalid', $extras);
    }

    // ===========================
    // Integration tests - combining multiple options
    // ===========================

    public function testCombinedConfigurationFlatFormat(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 1,
            'connection.dsn' => 'https://key@sentry.io/1',
            'connection.environment' => 'production',
            'connection.release' => '1.0.0',
            'sampling.tracesSampleRate' => '0.5',
            'sampling.profilesSampleRate' => '0.25',
            'filter.enabledLogLevels' => 'error,critical',
            'filter.captureExceptionsOnly' => 0,
            'context.staticTags' => 'region:eu;cluster:www',
            'context.staticExtras' => 'app:frontend;source:typo3'
        ]);

        self::assertTrue($this->getProperty($writer, 'enabled'));
        self::assertSame(['error', 'critical'], $this->getProperty($writer, 'enabledLevels'));
        self::assertFalse($this->getProperty($writer, 'exceptionsOnly'));
        self::assertSame(['region' => 'eu', 'cluster' => 'www'], $this->getProperty($writer, 'staticTags'));
        self::assertSame(['app' => 'frontend', 'source' => 'typo3'], $this->getProperty($writer, 'staticExtras'));
    }

    public function testCombinedConfigurationNestedFormat(): void
    {
        $writer = $this->createWriter([
            'features' => ['enable' => 1],
            'connection' => [
                'dsn' => 'https://key@sentry.io/1',
                'environment' => 'staging',
                'release' => '2.0.0'
            ],
            'sampling' => [
                'tracesSampleRate' => '0.1',
                'profilesSampleRate' => '0.05'
            ],
            'filter' => [
                'enabledLogLevels' => 'warning,error,critical',
                'captureExceptionsOnly' => 1
            ],
            'context' => [
                'staticTags' => 'env:staging',
                'staticExtras' => 'version:2.0'
            ]
        ]);

        self::assertTrue($this->getProperty($writer, 'enabled'));
        self::assertSame(['warning', 'error', 'critical'], $this->getProperty($writer, 'enabledLevels'));
        self::assertTrue($this->getProperty($writer, 'exceptionsOnly'));
        self::assertSame(['env' => 'staging'], $this->getProperty($writer, 'staticTags'));
        self::assertSame(['version' => '2.0'], $this->getProperty($writer, 'staticExtras'));
    }

    public function testDisabledWriterIgnoresAllConfiguration(): void
    {
        $writer = $this->createWriter([
            'features.enable' => 0,
            'filter.enabledLogLevels' => 'debug,info,notice,warning,error,critical,alert,emergency'
        ]);

        self::assertFalse($this->getProperty($writer, 'enabled'));
        // When disabled, enabledLevels should be empty
        $levels = $this->getProperty($writer, 'enabledLevels');
        self::assertEmpty($levels);
    }
}
