<?php

namespace App\Test\TestCase\Forwarding;

use App\Forwarding\Sentry;
use App\Model\Table\IncidentsTable;
use App\Report;
use Cake\Core\Configure;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

use function array_column;
use function array_slice;
use function array_unique;
use function array_values;
use function bin2hex;
use function curl_strerror;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function dirname;
use function end;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_quote;
use function random_bytes;
use function rmdir;
use function scandir;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr_count;
use function sys_get_temp_dir;
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

        RecordingSentry::$envelopes = [];
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

    public function testSendReportThrowsWhenTheConfigurationIsMissing(): void
    {
        Configure::delete('Forwarding.Sentry');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Missing Sentry config');

        Sentry::sendReport(['message' => 'a is not defined', 'event_id' => str_repeat('a', 32)]);
    }

    /**
     * @param string $responseBody Body returned by the fake tracker
     * @param string $expectedId   Event ID the call answers
     */
    #[DataProvider('sendReportResponseProvider')]
    public function testSendReportReturnsTheEventIdOfTheResponse(string $responseBody, string $expectedId): void
    {
        $sentId = str_repeat('b', 32);
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, $responseBody);
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $this->assertSame($expectedId, Sentry::sendReport(['message' => 'a is not defined', 'event_id' => $sentId]));
    }

    /** @return array<string, array<int, string>> */
    public static function sendReportResponseProvider(): array
    {
        return [
            'string ID' => ['{"id":"abc123"}', 'abc123'],
            'integer ID' => ['{"id":42}', '42'],
            // A tracker that answers without an id is not a failure: the event
            // is here, under the id it was given.
            'empty ID' => ['{"id":""}', str_repeat('b', 32)],
            'no ID' => ['{"foo":"bar"}', str_repeat('b', 32)],
            'no body' => ['', str_repeat('b', 32)],
            'not JSON' => ['not-json', str_repeat('b', 32)],
        ];
    }

    public function testTheEnvelopeCarriesTheEventAndItsLength(): void
    {
        $event = ['event_id' => 'aabb', 'message' => 'a is not defined'];
        $json = (string) json_encode($event);

        $envelope = Sentry::buildEnvelope([['event', $event]], 'aabb');

        $this->assertMatchesRegularExpression(
            '/^\{"event_id":"aabb","sent_at":"[^"]+"\}\n'
            . preg_quote(json_encode(['type' => 'event', 'length' => strlen($json)]), '/')
            . '\n' . preg_quote($json, '/') . '$/',
            $envelope,
        );
        // One newline after each header line, and no trailing newline.
        $this->assertSame(2, substr_count($envelope, "\n"));
        $this->assertStringEndsNotWith("\n", $envelope);
    }

    public function testTheDeclaredLengthIsTheLengthOfThePayload(): void
    {
        $event = ['message' => str_repeat('a', 500)];

        $envelope = Sentry::buildEnvelope([['event', $event]], 'aabb');

        // Whatever the encoding does to the characters, the header must count
        // the bytes of the payload that follows it.
        $parts = explode("\n", $envelope);
        $declared = json_decode($parts[1], true);
        $this->assertIsArray($declared);
        $this->assertSame(strlen($parts[2]), $declared['length']);
        $this->assertSame(500 + strlen('{"message":""}'), $declared['length']);
    }

    public function testAFeedbackEnvelopeNamesItsEventInItsHeader(): void
    {
        // The user_report section of the protocol requires event_id in the
        // envelope header: a receiver discards the envelope without it.
        $envelope = Sentry::buildEnvelope([['user_report', ['event_id' => 'aabb']]], 'aabb');

        $this->assertStringStartsWith('{"event_id":"aabb","sent_at":"', $envelope);
    }

    public function testAnEnvelopeWithoutEventIdIsRefused(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Refusing to build an envelope without an event id');

        Sentry::buildEnvelope([['event', ['message' => 'x']]], '');
    }

    public function testAnEnvelopeWithoutItemIsRefused(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Refusing to build an envelope without an item');

        Sentry::buildEnvelope([], 'aabb');
    }

    public function testAnEnvelopeThatCannotBeEncodedIsRefused(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Could not encode the "event" item');

        // Invalid UTF-8, which a crash message can carry.
        Sentry::buildEnvelope([['event', ['message' => "\xB1\x31"]]], 'aabb');
    }

    public function testAReportWithoutAnEventIdIsRefused(): void
    {
        $this->configureForwarding('file:///tmp/');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('The report has no event_id');

        Sentry::sendReport(['message' => 'a is not defined']);
    }

    /** @param int $status The status of the answer */
    #[DataProvider('statusProvider')]
    public function testARefusedAnswerThrows(int $status): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('The tracker refused the envelope, status ' . $status);

        Sentry::assertAccepted($status, '{"detail":"invalid"}');
    }

    /** @return array<string, array<int, int>> */
    public static function statusProvider(): array
    {
        return [
            'bad request' => [400],
            'unauthorized' => [401],
            'not found' => [404],
            'server error' => [500],
        ];
    }

    public function testAnAcceptedAnswerDoesNotThrow(): void
    {
        foreach ([0, 200, 201, 204] as $status) {
            Sentry::assertAccepted($status, '');
        }

        $this->assertSame([], $this->errorLogs());
    }

    public function testARedirectIsNotAStore(): void
    {
        // cURL does not follow it: the body is a redirect page, not an event.
        foreach ([301, 302, 307] as $status) {
            try {
                Sentry::assertAccepted($status, '');
                $this->fail('A ' . $status . ' must not be taken for a store');
            } catch (Throwable $exception) {
                $this->assertStringContainsString('status ' . $status, $exception->getMessage());
            }
        }
    }

    public function testSendReportLogsAndThrowsOnACurlError(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->configureForwarding('file://' . $baseDirectory . DS . 'missing.json?');

        $expected = 'Creating the report failed, cURL error (37): ' . curl_strerror(37);

        try {
            Sentry::sendReport(['message' => 'a is not defined', 'event_id' => str_repeat('a', 32)]);
            $this->fail('An exception should have been thrown on a cURL error');
        } catch (Throwable $exception) {
            $this->assertSame($expected, $exception->getMessage());
        }

        $this->assertLogContains($expected);
    }

    public function testSendReportEncodesACompleteReportPayload(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->createCurlResponse($baseDirectory, '{"id":"php-event"}');
        $this->configureForwarding($this->fakeBaseUrl($baseDirectory));

        $report = json_decode((string) file_get_contents($this->fixturePath('report_php.json')), true);
        $this->assertIsArray($report);
        $report['event_id'] = str_repeat('c', 32);

        $this->assertSame('php-event', Sentry::sendReport($report));
        $this->assertLogContains('The tracker stored the event as php-event instead of');
    }

    /**
     * The controller decodes the body to arrays and caps it, then hands it to
     * fromString(). Built from the arrays with fromObject(), the nested values
     * stay arrays and every message, culprit and frame comes out empty.
     */
    public function testThePayloadTheControllerForwardsCarriesItsData(): void
    {
        $decoded = (array) json_decode(
            (string) file_get_contents($this->fixturePath('report_php.json')),
            true,
        );
        $capped = IncidentsTable::capReport($decoded);

        $report = Report::fromString((string) json_encode($capped));
        $events = $report->getReports();

        $this->assertNotSame([], $events);
        $this->assertNotSame('', $events[0]['message'], 'the message of the event must not be empty');
        $this->assertNotSame('', $events[0]['culprit'], 'the culprit of the event must not be empty');
        $value = $events[0]['exception']['values'][0];
        $this->assertNotSame('', $value->value, 'the value of the exception must not be empty');
        $this->assertNotSame(
            [],
            $value->stacktrace->frames,
            'the frames of the exception must not be empty',
        );
    }

    public function testUserReportIsSilentWhenTheConfigurationIsMissing(): void
    {
        Configure::delete('Forwarding.Sentry');

        Sentry::sendUserReport('event-id', 'A comment', 'user-id');

        // The feedback is best effort: a missing configuration is not an error
        // the caller has to handle.
        $this->assertSame([], $this->errorLogs());
    }

    public function testUserReportIsSentAndSilentOnSuccess(): void
    {
        $this->configureForwarding('file:///tmp/');

        RecordingSentry::sendUserReport('event-id', 'A comment', 'user-id');

        $this->assertCount(1, RecordingSentry::$envelopes, 'the feedback must be sent');
        [$payload, $type, $headerEventId] = RecordingSentry::$envelopes[0];
        $this->assertSame('user_report', $type);
        $this->assertSame('event-id', $headerEventId, 'the envelope header must name the event');
        $this->assertSame('event-id', $payload['event_id']);
        $this->assertSame('A comment', $payload['comments']);
        $this->assertSame('u+user-id@r.local', $payload['email']);
        $this->assertSame('phpMyAdmin User', $payload['name']);
        $this->assertSame([], $this->errorLogs());
    }

    public function testUserReportIsNotSentWithoutAConfiguration(): void
    {
        Configure::delete('Forwarding.Sentry');

        RecordingSentry::sendUserReport('event-id', 'A comment', 'user-id');

        $this->assertCount(0, RecordingSentry::$envelopes, 'nothing must be sent');
    }

    public function testUserReportLogsATransportFailureWithoutThrowing(): void
    {
        $baseDirectory = $this->createTempDirectory();
        $this->configureForwarding('file://' . $baseDirectory . DS . 'missing.json?');

        Sentry::sendUserReport('event-id', 'A comment', 'user-id');

        $this->assertLogContains('Creating the report feedback failed:');
    }

    public function testProcessSendsAReportWithoutUserFeedback(): void
    {
        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString((string) file_get_contents($this->fixturePath('report_php.json')));
        $this->assertFalse($report->hasUserFeedback());

        RecordingSentry::process($report);

        $types = array_column(RecordingSentry::$envelopes, 1);
        $this->assertSame(['event'], array_values(array_unique($types)));
        $this->assertStringNotContainsString('user_report', implode(',', $types));
        $this->assertCount(3, RecordingSentry::$envelopes, 'one envelope per error of the report');
    }

    public function testProcessSendsTheUserFeedbackOfAReport(): void
    {
        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString((string) file_get_contents($this->fixturePath('report_js.json')));
        $this->assertTrue($report->hasUserFeedback());

        RecordingSentry::process($report);

        $types = array_column(RecordingSentry::$envelopes, 1);
        $this->assertSame('user_report', end($types), 'the feedback is sent last');
        $this->assertSame(
            ['event'],
            array_values(array_unique(array_slice($types, 0, -1))),
            'every envelope before the feedback carries one event',
        );
        // The feedback belongs to the report: it is sent once, and names the
        // event it hangs from.
        $this->assertSame(
            RecordingSentry::$envelopes[0][2],
            RecordingSentry::$envelopes[1][0]['event_id'],
            'the feedback must name the first stored event',
        );
        $this->assertSame(
            RecordingSentry::$envelopes[0][2],
            RecordingSentry::$envelopes[1][2],
            'a feedback envelope names its event in the envelope header too',
        );
    }

    public function testUserFeedbackIsTruncated(): void
    {
        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString((string) json_encode([
            'pma_version' => '5.2.0',
            'exception_type' => 'js',
            'steps' => str_repeat('x', 10000),
            'exception' => ['name' => 'Error', 'message' => 'boom', 'stack' => []],
        ]));

        RecordingSentry::process($report);

        $feedback = end(RecordingSentry::$envelopes);
        $this->assertSame('user_report', $feedback[1]);
        $this->assertSame(Sentry::MAX_FEEDBACK_LENGTH, strlen($feedback[0]['comments']));
    }

    /**
     * One report carries up to one event per error: an unauthenticated caller
     * must not make a single request send thousands of events to Sentry.
     */
    public function testOnlyTheFirstEventsOfAReportAreSent(): void
    {
        $errors = [];
        for ($i = 0; $i < 100; $i++) {
            $errors[] = [
                'msg' => 'error ' . $i,
                'type' => 'Error',
                'file' => '/srv/index.php',
                'line' => 10,
                'stackTrace' => [],
            ];
        }

        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString((string) json_encode([
            'pma_version' => '5.2.0',
            'php_version' => '8.4.0',
            'exception_type' => 'php',
            'errors' => $errors,
        ]));
        $this->assertCount(100, $report->getReports(), 'the report must carry 100 events');

        RecordingSentry::process($report);

        $this->assertCount(
            Sentry::MAX_EVENTS_PER_REPORT,
            RecordingSentry::$envelopes,
            'the report must be cut to the maximum',
        );
        // The first events, not any of them.
        $this->assertSame('error 0', RecordingSentry::$envelopes[0][0]['message']);
        $this->assertSame('error 39', RecordingSentry::$envelopes[39][0]['message']);
    }

    /**
     * What is sent must carry the data of the crash, not only an id: a payload
     * built from arrays had every message, culprit and frame empty.
     */
    public function testTheEventThatIsSentCarriesItsData(): void
    {
        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString(
            (string) file_get_contents($this->fixturePath('report_php.json')),
        );

        RecordingSentry::process($report);

        $event = RecordingSentry::$envelopes[0][0];
        $this->assertSame('event', RecordingSentry::$envelopes[0][1]);
        $this->assertNotSame('', $event['message'], 'the message of the wire event must not be empty');
        $this->assertNotSame('', $event['culprit'], 'the culprit of the wire event must not be empty');
        $value = $event['exception']['values'][0];
        $this->assertNotSame('', $value->value ?? '', 'the value of the exception must not be empty');
        $this->assertNotSame([], $value->stacktrace->frames, 'the frames must not be empty');
    }

    /**
     * The feedback belongs to the report: it is attached to the first stored
     * event and sent once, not once per event.
     */
    public function testTheFeedbackOfAMultiEventReportIsSentOnce(): void
    {
        $this->configureForwarding('file:///tmp/');
        $errors = [];
        for ($i = 0; $i < 3; $i++) {
            $errors[] = ['msg' => 'e' . $i, 'type' => 'Error', 'file' => '/f', 'line' => 1, 'stackTrace' => []];
        }

        $report = Report::fromString((string) json_encode([
            'pma_version' => '5.2.0',
            'php_version' => '8.4.0',
            'exception_type' => 'php',
            'steps' => 'Please fix this',
            'errors' => $errors,
        ]));
        $this->assertTrue($report->hasUserFeedback());
        $this->assertCount(3, $report->getReports());

        RecordingSentry::process($report);

        $types = array_column(RecordingSentry::$envelopes, 1);
        $this->assertSame(['event', 'event', 'event', 'user_report'], $types);
        $feedback = end(RecordingSentry::$envelopes);
        $this->assertSame(RecordingSentry::$envelopes[0][2], $feedback[2], 'it names the first event');
        $this->assertSame('Please fix this', $feedback[0]['comments']);
    }

    public function testTheFeedbackThatCannotBeEncodedIsDropped(): void
    {
        $this->configureForwarding('file:///tmp/');
        $report = Report::fromString((string) json_encode([
            'pma_version' => '5.2.0',
            'exception_type' => 'js',
            'steps' => str_repeat('€', 3000),// cut on a character boundary
            'exception' => ['name' => 'Error', 'message' => 'boom', 'stack' => []],
        ]));

        RecordingSentry::process($report);

        $feedback = end(RecordingSentry::$envelopes);
        $this->assertSame('user_report', $feedback[1]);
        // The truncation must not lose the tail of the string on a broken byte.
        $length = strlen($feedback[0]['comments']);
        $this->assertLessThanOrEqual(Sentry::MAX_FEEDBACK_LENGTH, $length);
        $this->assertGreaterThan(Sentry::MAX_FEEDBACK_LENGTH - 4, $length, 'it must cut close to the maximum');
        $this->assertNotFalse(json_encode($feedback[0]), 'the truncated comment must stay encodable');
    }

    /**
     * Build the Sentry configuration pointing at a local path.    /**
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
