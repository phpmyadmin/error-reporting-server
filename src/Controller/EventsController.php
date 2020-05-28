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

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use function count;
use function explode;
use function file_get_contents;
use function hash_hmac;
use function strpos;

/**
 * Events controller Github webhook events
 */
class EventsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Csrf');

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    public function beforeFilter(Event $event): void
    {
        $this->eventManager()->off($this->Csrf);
    }

    public function index(): ?Response
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        // Validate request
        $statusCode = $this->validateRequest($this->request);
        if ($statusCode !== 201) {
            Log::error(
                'Could not validate the request. Sending a '
                    . $statusCode . ' response.'
            );

            // Send a response
            $this->auto_render = false;
            $this->response->statusCode($statusCode);

            return $this->response;
        }

        if ($statusCode === 200) {
           // Send a success response to ping event
            $this->auto_render = false;
            $this->response->statusCode($statusCode);

            return $this->response;
        }

        $issuesData = $this->request->input('json_decode', true);
        $eventAction = $issuesData['action'];
        $issueNumber = $issuesData['issue'] ? $issuesData['issue']['number'] : '';

        if ($eventAction === 'closed'
            || $eventAction === 'opened'
            || $eventAction === 'reopened'
        ) {
            $status = $this->getAppropriateStatus($eventAction);
            $reportsUpdated = $this->Reports->setLinkedReportStatus($issueNumber, $status);
            if ($reportsUpdated > 0) {
                Log::debug(
                    $reportsUpdated . ' linked reports to issue number '
                        . $issueNumber . ' were updated according to received action '
                        . $eventAction
                );
            } else {
                Log::info(
                    'No linked report found for issue number \'' . $issueNumber
                    . '\'. Ignoring the event.'
                );
                $statusCode = 204;
            }
        } else {
            Log::info(
                'received a webhook event for action \'' . $eventAction
                . '\' on issue number ' . $issueNumber . '. Ignoring the event.'
            );
            $statusCode = 204;
        }

        // Send a response
        $this->auto_render = false;
        $this->response->statusCode($statusCode);

        return $this->response;
    }

    /**
     * Validate HTTP Request received
     *
     * @param ServerRequest $request Request object
     *
     * @return int status code based on if this is a valid request
     */
    protected function validateRequest(ServerRequest $request): int
    {
        // Default $statusCode
        $statusCode = 201;

        $userAgent = $request->getHeaderLine('User-Agent');
        $eventType = $request->getHeaderLine('X-GitHub-Event');

        $receivedHashHeader = $request->getHeaderLine('X-Hub-Signature');
        $algo = '';
        $receivedHash = '';
        if ($receivedHashHeader !== null) {
            $parts = explode('=', $receivedHashHeader);
            if (count($parts) > 1) {
                $algo = $parts[0];
                $receivedHash = $parts[1];
            }
        }

        $expectedHash = $this->getHash(file_get_contents('php://input'), $algo);

        if ($userAgent !== null && strpos($userAgent, 'GitHub-Hookshot') !== 0) {
            // Check if the User-agent is Github
            // Otherwise, Send a '403: Forbidden'

            Log::error(
                'Invalid User agent: ' . $userAgent
                . '. Ignoring the event.'
            );

            return 403;
        }

        if ($eventType !== null && $eventType === 'ping') {
            // Check if the request is based on 'issues' event
            // Otherwise, Send a '400: Bad Request'

            Log::info(
                'Ping event type received.'
            );

            return 200;
        }

        if ($eventType !== null && $eventType !== 'issues') {
            // Check if the request is based on 'issues' event
            // Otherwise, Send a '400: Bad Request'

            Log::error(
                'Unexpected event type: ' . $eventType
                . '. Ignoring the event.'
            );

            return 400;
        }

        if ($receivedHash !== $expectedHash) {
            // Check if hash matches
            // Otherwise, Send a '401: Unauthorized'

            Log::error(
                'received hash ' . $receivedHash . ' does not match '
                . ' expected hash ' . $expectedHash
                . '. Ignoring the event.'
            );

            return 401;
        }

        return $statusCode;
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
        if ($algo === '') {
            return '';
        }
        $key = Configure::read('GithubWebhookSecret');

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
