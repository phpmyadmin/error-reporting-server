<?php

namespace App\Test\TestCase\Forwarding;

use App\Forwarding\Sentry;
use App\Report;
use Cake\Core\Configure;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

use function bin2hex;
use function curl_strerror;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function str_contains;
use function str_starts_with;
use function sys_get_temp_dir;
use function time;
use function unlink;

use const DS;

/**
 * Tests for the Sentry forwarding.
 *
 * The Sentry class calls the global cURL functions through "use function"
 * imports, so phpmock cannot intercept them (it can only mock unqualified
 * calls resolved through the namespace fallback). The tests therefore drive
 * the real cURL extension, but only against "file://" URLs: the base URL ends
 * with "?", so everything Sentry appends becomes a query string that cURL
 * ignores while reading a local file. No network request is ever made.
 */
class SentryTest extends TestCase
{
    private mixed $originalSentryConfig;

    private string $originalTimezone;

    private ArrayLog $logCapture;

    /** @var array<int, string> */
    private array $tempDirectories = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->originalSentryConfig = Configure::read('Forwarding.Sentry');
        $this->originalTimezone = date_default_timezone_get();
        $this->configureLogCapture();
    }

    public function tearDown(): void
    {
        if ($this->originalSentryConfig === null) {
            Configure::delete('Forwarding.Sentry');
        } else {
            Configure::write('Forwarding.Sentry', $this->originalSentryConfig);
        }

        date_default_timezone_set($this->originalTimezone);

        foreach ($this->tempDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->tempDirectories = [];

        parent::tearDown();
    }

    public function testGetSentryTimestampReturnsTheCurrentTimeInUtc(): void
    {
        date_default_timezone_set('Europe/Paris');

        $before = time();
        $timestamp = Sentry::getSentryTimestamp();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
        $this->assertSame('UTC', date_default_timezone_get());
    }

    public function testSendReportThrowsWhenTheConfigurationIsMissing(): void
    {
        Configure::delete('Forwarding.Sentry');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Missing Sentry config');

        Sentry::sendReport(['message' => 'a is not defined']);
    }

    /**
     * @param string $responseBody Body returned by the fake Sentry endpoint
     * @param string $expectedId   Event ID Sentry returns
     */
    #[DataProvider('sendReportResponseProvider')]
    public function testSendReportReturnsTheEventIdOfTheResponse(string $responseBody, string $expectedId): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, $responseBody);
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $this->assertSame($expectedId, Sentry::sendReport(['message' => 'a is not defined']));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function sendReportResponseProvider(): array
    {
        return [
            'string ID' => ['{"id":"abc123"}', 'abc123'],
            'integer ID' => ['{"id":42}', '42'],
            'empty ID' => ['{"id":""}', ''],
        ];
    }

    public function testSendReportEncodesACompleteReportPayload(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"id":"php-event"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $report = json_decode((string) file_get_contents($this->fixturePath('report_php.json')), true);
        $this->assertIsArray($report);

        $this->assertSame('php-event', Sentry::sendReport($report));
        $this->assertSame([], $this->errorLogs());
    }

    public function testSendReportLogsAndThrowsOnACurlError(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->configureForwarding('file://' . $baseDirectory . DS . 'missing.json?');

        $expected = 'Creating the report failed, cURL error (37): ' . curl_strerror(37);

        try {
            Sentry::sendReport(['message' => 'a is not defined']);
            $this->fail('An exception should have been thrown on a cURL error');
        } catch (Throwable $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }

        $this->assertLogContains($expected);
    }

    public function testSendReportLogsAndThrowsOnAnInvalidJsonResponse(): void
    {
        $body = 'not-json';
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, $body);
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $expected = 'Invalid JSON response: ' . json_encode($body);

        try {
            Sentry::sendReport(['message' => 'a is not defined']);
            $this->fail('An exception should have been thrown on an invalid JSON response');
        } catch (Throwable $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }

        $this->assertLogContains($expected);
    }

    public function testSendReportLogsAndThrowsWhenTheResponseHasNoId(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"foo":"bar"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $expected = 'Invalid response Id: ' . json_encode(['foo' => 'bar']);

        try {
            Sentry::sendReport(['message' => 'a is not defined']);
            $this->fail('An exception should have been thrown when the response has no ID');
        } catch (Throwable $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }

        $this->assertLogContains($expected);
    }

    public function testSendFeedbackThrowsWhenTheConfigurationIsMissing(): void
    {
        Configure::delete('Forwarding.Sentry');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Missing Sentry config');

        Sentry::sendFeedback('event-id', 'A comment', 'user-id');
    }

    public function testSendFeedbackAcceptsAValidResponse(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"result":"ok"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        Sentry::sendFeedback('event-id', 'A comment', 'user-id');

        $this->assertLogContains('Response-code: 0');
        $this->assertSame([], $this->errorLogs());
    }

    public function testSendFeedbackLogsANonStringResponse(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->configureForwarding('file://' . $baseDirectory . DS . 'missing.json?');

        Sentry::sendFeedback('event-id', 'A comment', 'user-id');

        $this->assertLogContains('Creating the report feedback failed: false');
    }

    public function testSendFeedbackLogsAnInvalidJsonResponse(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, 'not-json');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        Sentry::sendFeedback('event-id', 'A comment', 'user-id');

        $this->assertLogContains('Invalid JSON response: ' . json_encode('not-json'));
    }

    public function testSendFeedbackLogsAnErrorResponse(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"errors":["invalid event"]}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        Sentry::sendFeedback('event-id', 'A comment', 'user-id');

        $this->assertLogContains('Response has errors: ["invalid event"]');
    }

    public function testProcessSendsAReportWithoutUserFeedback(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"id":"php-event"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $report = Report::fromString((string) file_get_contents($this->fixturePath('report_php.json')));
        $this->assertFalse($report->hasUserFeedback());

        Sentry::process($report);

        // sendFeedback() logs the HTTP code of its response: this report has
        // no user feedback, so its feedback endpoint must not be called.
        $this->assertLogDoesNotContain('Response-code:');
        $this->assertSame([], $this->errorLogs());
    }

    public function testProcessSendsTheUserFeedbackOfAReport(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"id":"js-event"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $report = Report::fromString((string) file_get_contents($this->fixturePath('report_js.json')));
        $this->assertTrue($report->hasUserFeedback());

        Sentry::process($report);

        $this->assertLogContains('Response-code: 0');
        $this->assertLogDoesNotContain('report feedback failed');
        $this->assertSame([], $this->errorLogs());
    }

    /**
     * Build the Sentry configuration pointing at a local path.
     *
     * @param string                $baseUrl   Base URL of the fake Sentry
     * @param array<string, string> $overrides Configuration values to replace
     */
    private function configureForwarding(string $baseUrl, array $overrides = []): void
    {
        Configure::write('Forwarding.Sentry', $overrides + [
            'base_url' => $baseUrl,
            'project_id' => 2,
            'key' => 'the-key',
            'secret' => 'the-secret',
            'dsn_url' => 'https://the-key@example.invalid/2',
        ]);
    }

    /**
     * Prepare a fake cURL response.
     *
     * The base URL built by fakeBaseUrl() ends with "?": everything Sentry
     * appends to it (API path, event ID, DSN) becomes the query string of a
     * "file://" URL. cURL reads this local file and ignores the query, which
     * simulates any endpoint without issuing a network request.
     *
     * @param string $baseDirectory Directory holding the fake response
     * @param string $body          Content the endpoint must return
     */
    private function createCurlResponse(string $baseDirectory, string $body): void
    {
        file_put_contents($baseDirectory . DS . 'response.json', $body);
    }

    /**
     * Build a base URL whose requests read the fake response file.
     *
     * @param string $baseDirectory Directory holding the fake response
     */
    private function fakeBaseUrl(string $baseDirectory): string
    {
        return 'file://' . $baseDirectory . DS . 'response.json?';
    }

    private function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir() . DS . 'ers-sentry-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function fixturePath(string $name): string
    {
        return dirname(__DIR__, 2) . DS . 'Fixture' . DS . $name;
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DS . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function configureLogCapture(): void
    {
        $engine = Log::engine('sentry_test');
        if (! $engine instanceof ArrayLog) {
            Log::setConfig('sentry_test', ['className' => ArrayLog::class]);
            $engine = Log::engine('sentry_test');
        }

        $this->assertInstanceOf(ArrayLog::class, $engine);

        $this->logCapture = $engine;
        $this->logCapture->clear();
    }

    private function assertLogContains(string $expected): void
    {
        $this->assertTrue(
            $this->hasLogContaining($expected),
            'Expected log message not found: ' . $expected . "\nLogs:\n" . implode("\n", $this->logCapture->read()),
        );
    }

    private function assertLogDoesNotContain(string $unexpected): void
    {
        $this->assertFalse(
            $this->hasLogContaining($unexpected),
            'Unexpected log message found: ' . $unexpected,
        );
    }

    private function hasLogContaining(string $needle): bool
    {
        foreach ($this->logCapture->read() as $line) {
            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    private function errorLogs(): array
    {
        $errors = [];
        foreach ($this->logCapture->read() as $line) {
            if (! str_starts_with($line, 'error:') && ! str_starts_with($line, 'warning:')) {
                continue;
            }

            $errors[] = $line;
        }

        return $errors;
    }
}
