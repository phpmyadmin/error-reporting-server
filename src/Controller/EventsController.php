<?php

/**
 * Events controller Github webhook events
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */

namespace App\Controller;

use App\Model\Table\ReportsTable;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

use function hash_equals;
use function hash_hmac;
use function json_decode;
use function json_encode;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Events controller Github webhook events
 */
class EventsController extends AppController
{
    protected ReportsTable $Reports;

    public function initialize(): void
    {
        parent::initialize();

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    public function index(): Response|null
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        // The raw body is the only thing the signature can be checked against,
        // read it once from the request.
        $payload = (string) $this->request->getBody();

        // Validate request
        $statusCode = $this->validateRequest($this->request, $payload);

        if ($statusCode === 200) {
            // A ping event only checks the delivery, there is nothing to do.
            return $this->respond($statusCode);
        }

        if ($statusCode !== 201) {
            Log::error(
                'Could not validate the request. Sending a '
                    . $statusCode . ' response.',
            );

            return $this->respond($statusCode);
        }

        $issuesData = json_decode($payload, true);
        $eventAction = $issuesData['action'];
        $issueNumber = $issuesData['issue'] ? $issuesData['issue']['number'] : '';

        if ($eventAction === 'closed' || $eventAction === 'opened' || $eventAction === 'reopened') {
            $status = $this->getAppropriateStatus($eventAction);
            $reportsUpdated = $this->Reports->setLinkedReportStatus($issueNumber, $status);
            if ($reportsUpdated > 0) {
                Log::debug(
                    $reportsUpdated . ' linked reports to issue number '
                        . $issueNumber . ' were updated according to received action '
                        . $eventAction,
                );
            } else {
                Log::info(
                    'No linked report found for issue number \'' . $issueNumber
                    . '\'. Ignoring the event.',
                );
                $statusCode = 204;
            }
        } else {
            Log::info(
                'received a webhook event for action \'' . $eventAction
                . '\' on issue number ' . $issueNumber . '. Ignoring the event.',
            );
            $statusCode = 204;
        }

        // Send a response
        return $this->respond($statusCode);
    }

    /**
     * Build the response of the webhook.
     *
     * A response without content must not carry a Content-Type header: the
     * field describes the content of the message (RFC 9110 section 8.3) and
     * CakePHP already removes it from a 204. The refusals carry a small JSON
     * body instead, so the Content-Type tells the truth and the reason can be
     * read in the "Recent Deliveries" of GitHub.
     *
     * @param int $statusCode Status code to answer with
     *
     * @return Response The response to send
     */
    protected function respond(int $statusCode): Response
    {
        $this->disableAutoRender();

        $response = $this->response->withStatus($statusCode);

        $reason = match ($statusCode) {
            400 => 'Unexpected event type.',
            401 => 'The signature of the payload is missing or does not match.',
            403 => 'The request does not come from GitHub.',
            default => null,
        };
        if ($reason === null) {
            return $response;
        }

        return $response
            ->withType('application/json')
            ->withStringBody((string) json_encode(['error' => $reason]));
    }

    /**
     * Validate HTTP Request received
     *
     * @param ServerRequest $request Request object
     * @param string        $payload Raw body of the request
     *
     * @return int status code based on if this is a valid request
     */
    protected function validateRequest(ServerRequest $request, string $payload): int
    {
        $secret = (string) Configure::read('GithubWebhookSecret');
        if ($secret === '') {
            // With an empty key anyone can compute the expected HMAC.
            Log::error(
                'GithubWebhookSecret is empty: refusing every webhook event.',
            );

            return 401;
        }

        $userAgent = $request->getHeaderLine('User-Agent');
        $eventType = $request->getHeaderLine('X-GitHub-Event');

        if (strpos($userAgent, 'GitHub-Hookshot') !== 0) {
            // Check if the User-agent is Github
            // Otherwise, Send a '403: Forbidden'

            Log::error(
                'Invalid User agent: ' . $userAgent
                . '. Ignoring the event.',
            );

            return 403;
        }

        if ($eventType === 'ping') {
            // Ping events only check the delivery, there is nothing to do.
            Log::info(
                'Ping event type received.',
            );

            return 200;
        }

        if ($eventType !== 'issues') {
            // Check if the request is based on 'issues' event
            // Otherwise, Send a '400: Bad Request'

            Log::error(
                'Unexpected event type: ' . $eventType
                . '. Ignoring the event.',
            );

            return 400;
        }

        // GitHub signs the deliveries with sha256 in X-Hub-Signature-256.
        // Older integrations still use sha1 in X-Hub-Signature. The client
        // must not be able to pick any other algorithm.
        $algo = 'sha256';
        $signature = $request->getHeaderLine('X-Hub-Signature-256');
        if ($signature === '') {
            $algo = 'sha1';
            $signature = $request->getHeaderLine('X-Hub-Signature');
        }

        $prefix = $algo . '=';
        if (! str_starts_with($signature, $prefix)) {
            Log::error(
                'The signature of the webhook payload is missing or uses an '
                . 'unsupported algorithm. Ignoring the event.',
            );

            return 401;
        }

        $receivedHash = substr($signature, strlen($prefix));
        $expectedHash = $this->getHash($payload, $algo);

        if (! hash_equals($expectedHash, $receivedHash)) {
            // Never log either hash: whoever reads the log could use the
            // expected one to forge a valid signature.
            Log::error(
                'The signature of the webhook payload does not match. '
                . 'Ignoring the event.',
            );

            return 401;
        }

        return 201;
    }

    /**
     * Get the hash of raw POST payload
     *
     * @param string $payload Raw POST body string
     * @param string $algo    Algorithm used to calculate the hash
     *
     * @return string Hmac Digest-based hash of payload
     */
    protected function getHash(string $payload, string $algo): string
    {
        $key = (string) Configure::read('GithubWebhookSecret');

        return hash_hmac($algo, $payload, $key);
    }

    /**
     * Get appropriate new status based on action received in github event
     *
     * @param string $action Action received in Github webhook event
     *
     * @return string Appropriate new status for the related reports
     */
    protected function getAppropriateStatus(string $action): string
    {
        $status = 'forwarded';

        switch ($action) {
            case 'opened':
                break;

            case 'reopened':
                break;

            case 'closed':
                $status = 'resolved';
                break;
        }

        return $status;
    }
}
