<?php

namespace App\Forwarding;

use App\Report;
use Cake\Core\Configure;
use Cake\Log\Log;
use Composer\CaBundle\CaBundle;
use CurlHandle;
use Exception;
use Throwable;

use function array_slice;
use function array_values;
use function curl_errno;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function curl_strerror;
use function gmdate;
use function is_array;
use function is_dir;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function max;
use function mb_strcut;
use function rtrim;
use function strcasecmp;
use function strlen;
use function substr;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CAINFO;
use const CURLOPT_CAPATH;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;

class Sentry
{
    /**
     * Largest number of events sent for a single report.
     */
    public const MAX_EVENTS_PER_REPORT = 40;

    /**
     * Seconds allowed to open the connection to the Sentry host.
     */
    public const CONNECT_TIMEOUT = 3;

    /**
     * Seconds allowed for the whole request to Sentry.
     */
    public const REQUEST_TIMEOUT = 5;

    /**
     * Longest comment of a user that is forwarded, in bytes.
     */
    public const MAX_FEEDBACK_LENGTH = 4096;

    /**
     * Attempts in a row that may fail to connect before the run gives up.
     */
    public const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * The cURL handle of the run, and the attempts that failed in a row.
     */
    private static CurlHandle|false|null $handle = null;

    private static int $consecutiveFailures = 0;

    /**
     * Whether forwarding is configured well enough to send anything.
     *
     * The single place the required keys are listed: a command that sends
     * nothing because a key is missing has to say so, and saying it twice from
     * two lists is how the two drift apart.
     */
    public static function hasConfig(): bool
    {
        try {
            self::getConfig();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Read the forwarding configuration, or refuse to send without it.
     *
     * @return array<string, mixed> The configuration
     */
    private static function getConfig(): array
    {
        $sentryConfig = Configure::read('Forwarding.Sentry');
        if ($sentryConfig === null) {
            throw new Exception('Missing Sentry config');
        }

        foreach (['base_url', 'project_id', 'key'] as $key) {
            if (($sentryConfig[$key] ?? '') === '') {
                throw new Exception('The Sentry config has no "' . $key . '"');
            }
        }

        return $sentryConfig;
    }

    /**
     * Build an envelope around the items to send.
     *
     * An envelope is a header line, then one item per entry: a header line that
     * gives the byte length of the payload, a newline, and the JSON payload.
     * The last payload is terminated by the end of the body, which the protocol
     * allows, so there is no trailing newline.
     *
     * @param array<int, array<int, mixed>> $items   The type
     *          and payload of each item
     * @param string                        $eventId The event the envelope belongs to: every item type
     *                               this class sends requires it in the envelope header
     * @return string The bytes of the envelope
     *
     * @see https://develop.sentry.dev/sdk/envelopes/
     */
    public static function buildEnvelope(array $items, string $eventId): string
    {
        if ($items === []) {
            throw new Exception('Refusing to build an envelope without an item');
        }

        if ($eventId === '') {
            // The header of an event and the header of a user_report both
            // require it, and a receiver discards the envelope without it.
            throw new Exception('Refusing to build an envelope without an event id');
        }

        $envelope = json_encode(['event_id' => $eventId, 'sent_at' => gmdate('Y-m-d\TH:i:s\Z')]) . "\n";
        foreach (array_values($items) as $index => [$type, $payload]) {
            $json = json_encode($payload);
            if ($json === false) {
                // A crash message is arbitrary text: it can hold invalid UTF-8.
                throw new Exception('Could not encode the "' . $type . '" item: ' . json_last_error_msg());
            }

            if ($index > 0) {
                $envelope .= "\n";
            }

            // The length counts bytes, never characters.
            $envelope .= json_encode(['type' => $type, 'length' => strlen($json)]) . "\n" . $json;
        }

        return $envelope;
    }

    /**
     * The headers of an envelope request.
     *
     * The secret and the timestamp of the legacy protocol are gone: the public
     * key is the only credential the envelope header carries.
     *
     * @param string $key The public key of the project
     * @return array<int, string> The headers
     */
    private static function envelopeHeaders(string $key): array
    {
        return [
            'Content-Type: application/x-sentry-envelope',
            'X-Sentry-Auth: Sentry sentry_version=7, sentry_client=phpmyadmin-proxy/0.2'
            . ', sentry_key=' . $key,
        ];
    }

    /**
     * Send one item of an envelope and read the answer.
     *
     * @param array<int, array<int, mixed>> $items        The items
     * @param string                        $eventId      The event the envelope belongs to
     * @param array<string, mixed>          $sentryConfig The forwarding configuration
     * @return array{int, string} The status code and the body of the answer
     */
    protected static function postEnvelope(array $items, string $eventId, array $sentryConfig): array
    {
        [$status, $output] = static::post(
            self::buildEnvelope($items, $eventId),
            self::envelopeHeaders((string) $sentryConfig['key']),
            $sentryConfig,
        );

        // Deleting this call is how a tracker that answers 400 gets taken for a
        // store, and the report is marked forwarded with nothing sent.
        self::assertAccepted($status, $output);

        return [$status, $output];
    }

    /**
     * Send the bytes of an envelope, and read the answer.
     *
     * @param string               $data         The bytes of the envelope
     * @param array<int, string>   $headers      The headers to send
     * @param array<string, mixed> $sentryConfig The forwarding configuration
     *
     * @return array{int, string} The status code and the body of the answer
     */
    protected static function post(string $data, array $headers, array $sentryConfig): array
    {
        // One handle for the whole run: a fresh one costs a TCP and TLS
        // handshake per event, and a backlog is thousands of them.
        $ch = self::$handle ??= curl_init();
        if ($ch === false) {
            self::$handle = null;

            throw new Exception('Could not init cURL');
        }

        // Use bundled ca file from composer/ca-bundle instead of the system one
        $caPathOrFile = CaBundle::getBundledCaBundlePath();

        if (is_dir($caPathOrFile)) {
            curl_setopt($ch, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($ch, CURLOPT_CAINFO, $caPathOrFile);
        }

        curl_setopt(
            $ch,
            CURLOPT_URL,
            rtrim((string) $sentryConfig['base_url'], '/')
            . '/api/' . $sentryConfig['project_id'] . '/envelope/',
        );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // The ingestion waits for this call: do not let it hang when the
        // service does not answer.
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $output = curl_exec($ch);

        $errNo = curl_errno($ch);
        if ($errNo !== 0) {
            self::$consecutiveFailures++;
        }

        if ($errNo !== 0) {
            $errorMessage = curl_strerror($errNo);

            // The caller logs what it catches: logging here as well would write
            // the same failure twice.
            $error = new Exception(
                'Creating the report failed, cURL error (' . $errNo . '): ' . $errorMessage,
            );

            // A tracker that cannot be reached is not asked again and again:
            // every attempt costs its timeout, and the backlog never drains.
            if (self::$consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                throw new TrackerUnreachableException(
                    'The tracker could not be reached ' . self::$consecutiveFailures
                    . ' times in a row, giving up for this run: ' . $errorMessage,
                );
            }

            throw $error;
        }

        self::$consecutiveFailures = 0;

        if (! is_string($output)) {
            throw new Exception('Creating the report failed: ' . json_encode($output));
        }

        return [(int) curl_getinfo($ch, CURLINFO_HTTP_CODE), (string) $output];
    }

    /**
     * Refuse an answer that the tracker rejected.
     *
     * A status of 0 is what cURL reports for a transfer that is not HTTP: the
     * body checks of the caller are what decide then.
     *
     * @param int    $status The status code of the answer
     * @param string $output The body of the answer
     */
    public static function assertAccepted(int $status, string $output): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        // A redirect is not a store either: cURL does not follow it, and the
        // body of a login page or of a canonical url is not an event.
        if ($status === 0) {
            // Not an HTTP transfer at all: the checks of the caller decide.
            return;
        }

        if ($status === 429) {
            throw new TrackerThrottledException(
                'The tracker asked to wait, status 429: ' . substr($output, 0, 500),
                self::retryAfterFrom($output),
            );
        }

        // The tracker answered, and it answered with a refusal: the body holds
        // the reason, but it is not worth a whole log line.
        throw new Exception(
            'The tracker refused the envelope, status ' . $status . ': ' . substr($output, 0, 500),
        );
    }

    /**
     * Send report and get ID
     *
     * @param array<string,mixed> $report The report as an array
     * @return string The event ID
     *
     * @see https://develop.sentry.dev/sdk/envelopes/
     */
    public static function sendReport(array $report): string
    {
        $sentryConfig = self::getConfig();

        $eventId = (string) ($report['event_id'] ?? '');
        if ($eventId === '') {
            throw new Exception('The report has no event_id');
        }

        [, $output] = static::postEnvelope([['event', $report]], $eventId, $sentryConfig);

        // A tracker that answers 2xx without a body is not a failure: the event
        // is here, under the id it was given.
        $response = json_decode($output, true);
        if (
            is_array($response) && isset($response['id']) && is_scalar($response['id'])
            && (string) $response['id'] !== ''
        ) {
            $answeredId = (string) $response['id'];
            if (strcasecmp($answeredId, $eventId) !== 0) {
                // A diagnostic, not a warning: a tracker that rewrites the id
                // is answering usefully, and a warning here would reach the
                // stderr of every cron run.
                Log::debug('The tracker stored the event as ' . $answeredId . ' instead of ' . $eventId);
            }

            return $answeredId;
        }

        Log::debug('The tracker answered without an event id, keeping ' . $eventId);

        return $eventId;
    }

    /**
     * Send the feedback of a user, attached to the event it was written for.
     *
     * The feedback is best effort and never raises: a tracker that does not
     * know the item must not cost the events of the same report.
     *
     * @param string $eventId  The event ID
     * @param string $comments The comment sent by the user
     * @param string $userId   The unique user id
     * @return void nothing
     */
    public static function sendUserReport(string $eventId, string $comments, string $userId): void
    {
        try {
            $sentryConfig = self::getConfig();
        } catch (Throwable $e) {
            // Forwarding is off, or misconfigured: nothing to forward to.
            return;
        }

        // comments is the "steps" field of the report, which a client fills
        // with up to a megabyte.
        $data = [
            'event_id' => $eventId,
            'comments' => mb_strcut($comments, 0, self::MAX_FEEDBACK_LENGTH, 'UTF-8'),
            'email' => 'u+' . $userId . '@r.local',// 75 chars max (fake hostname to pass email validation rule)
            'name' => 'phpMyAdmin User',
        ];

        try {
            static::postEnvelope([['user_report', $data]], $eventId, $sentryConfig);
        } catch (Throwable $e) {
            Log::error('Creating the report feedback failed: ' . $e->getMessage());
        }
    }

    /**
     * Send the events of a report, then its feedback.
     *
     * @param Report $report The report
     *
     * @return array<int, string> The ids of the events that were stored
     */
    public static function process(Report $report): array
    {
        $reports = array_slice($report->getReports(), 0, self::MAX_EVENTS_PER_REPORT);
        $eventIds = [];
        foreach ($reports as $reportData) {
            $eventIds[] = self::sendReport($reportData);
        }

        if ($eventIds === [] || ! $report->hasUserFeedback()) {
            return $eventIds;
        }

        // The feedback belongs to the report, not to each of its events:
        // attach it to the first one that was stored.
        self::sendUserReport($eventIds[0], $report->getUserFeedback(), $report->getUserId());

        return $eventIds;
    }

    /**
     * The seconds a 429 answer asked to wait, when it says so.
     *
     * @param string $output The body of the answer
     */
    private static function retryAfterFrom(string $output): int
    {
        $decoded = json_decode($output, true);
        if (is_array($decoded) && isset($decoded['retry_after']) && is_numeric($decoded['retry_after'])) {
            return max(0, (int) $decoded['retry_after']);
        }

        return 0;
    }

    /**
     * Forget the handle and the failure count, between two runs.
     */
    public static function reset(): void
    {
        self::$handle = null;
        self::$consecutiveFailures = 0;
    }
}
