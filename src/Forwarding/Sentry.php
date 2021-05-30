<?php

namespace App\Forwarding;

use App\Report;
use Cake\Core\Configure;
use Cake\Log\Log;
use Exception;

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function date_default_timezone_set;
use function http_build_query;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function time;

use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_USERPWD;

class Sentry
{
    public static function getSentryTimestamp(): int
    {
        date_default_timezone_set('UTC');

        return time();
    }

    /**
     * Send report and get ID
     *
     * @param array<string,mixed> $report The report as an array
     * @return string The event ID
     *
     * @see https://develop.sentry.dev/sdk/store/
     */
    public static function sendReport(array $report): string
    {
        $sentryConfig = Configure::read('Forwarding.Sentry');
        if ($sentryConfig === null) {
            throw new Exception('Missing Sentry config');
        }

        $data = json_encode($report);
        $ch = curl_init($sentryConfig['base_url'] . '/api/' . $sentryConfig['project_id'] . '/store/');
        if ($ch === false) {
            throw new Exception('Could not init cURL');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sentryConfig['key'] . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-type: application/json',
            'X-Sentry-Auth: Sentry sentry_version=7, sentry_timestamp=' . self::getSentryTimestamp()
            . ', sentry_key=' . $sentryConfig['key'] . ', sentry_client=phpmyadmin-proxy/0.1'
            . ', sentry_secret=' . $sentryConfig['secret'],
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $output = curl_exec($ch);
        if (! is_string($output)) {
            $error = 'Creating the report failed: ' . json_encode($output);
            Log::error($error);

            throw new Exception($error);
        }

        $response = json_decode((string) $output, true);
        if (! is_array($response)) {
            $error = 'Invalid JSON response: ' . json_encode($output);
            Log::error($error);

            throw new Exception($error);
        }

        if (! isset($response['id'])) {
            $error = 'Invalid response Id: ' . json_encode($response);
            Log::error($error);

            throw new Exception($error);
        }

        return (string) $response['id'];
    }

    /**
     * Set user feedback to Sentry API
     *
     * @param string $eventId  The event ID
     * @param string $comments The comment sent by the user
     * @param string $userId   The unique user id
     * @return void nothing
     */
    public static function sendFeedback(string $eventId, string $comments, string $userId): void
    {
        $sentryConfig = Configure::read('Forwarding.Sentry');
        if ($sentryConfig === null) {
            throw new Exception('Missing Sentry config');
        }

        $data = [
            'comments' => $comments,
            'email' => 'users+' . $userId . '@phpmyadmin.local',
            'name' => 'phpMyAdmin User',
        ];
        $ch = curl_init(
            $sentryConfig['base_url'] . '/api/embed/error-page/?dsn=' . $sentryConfig['dsn_url'] . '&eventId=' . $eventId
        );
        if ($ch === false) {
            throw new Exception('Could not init cURL');
        }

        curl_setopt($ch, CURLOPT_USERPWD, $sentryConfig['key'] . ':');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        curl_exec($ch);
    }

    public static function process(Report $report): void
    {
        foreach ($report->getReports() as $reportData) {
            $eventId = self::sendReport($reportData);
            if (! $report->hasUserFeedback()) {
                continue;
            }

            self::sendFeedback($eventId, $report->getUserFeedback(), $report->getUserId());
        }
    }
}
