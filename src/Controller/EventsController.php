<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Events controller Github webhook events.
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
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Events controller Github webhook events.
 */
class EventsController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('Csrf');

        $this->Reports = TableRegistry::get('Reports');
    }

    public function beforeFilter(Event $event)
    {
        $this->eventManager()->off($this->Csrf);
    }

    public function index()
    {
        // Only allow POST requests
        $this->request->allowMethod(array('post'));

        // Validate request
        if (201 !== ($statusCode = $this->_validateRequest($this->request))) {
            Log::error(
                'Could not validate the request. Sending a '
                    . $statusCode . ' response.'
            );

            // Send a response
            $this->auto_render = false;
            $this->response->statusCode($statusCode);

            return $this->response;
        } elseif (200 === $statusCode) {
            // Send a success response to ping event
            $this->auto_render = false;
            $this->response->statusCode($statusCode);

            return $this->response;
        }

        $issuesData = $this->request->input('json_decode', true);
        $eventAction = $issuesData['action'];
        $issueNumber = $issuesData['issue'] ? $issuesData['issue']['number'] : '';

        if ('closed' === $eventAction
            || 'opened' === $eventAction
            || 'reopened' === $eventAction
        ) {
            $status = $this->_getAppropriateStatus($eventAction);

            if (($reportsUpdated = $this->Reports->setLinkedReportStatus($issueNumber, $status)) > 0) {
                Log::debug(
                    $reportsUpdated . ' linked reports to issue number '
                        . $issueNumber . ' were updated according to recieved action '
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
                'Recieved a webhook event for action \'' . $eventAction
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
     * Validate HTTP Request recieved.
     *
     * @param Request $request Request object
     *
     * @return int status code based on if this is a valid request
     */
    protected function _validateRequest($request)
    {
        // Default $statusCode
        $statusCode = 201;

        $userAgent = $request->getHeaderLine('User-Agent');
        $eventType = $request->getHeaderLine('X-GitHub-Event');

        $recievedHashHeader = $request->getHeaderLine('X-Hub-Signature');
        $algo = '';
        $recievedHash = '';
        if (null !== $recievedHashHeader) {
            $parts = explode('=', $recievedHashHeader);
            if (count($parts) > 1) {
                $algo = $parts[0];
                $recievedHash = $parts[1];
            }
        }

        $expectedHash = $this->_getHash(file_get_contents('php://input'), $algo);

        if (null !== $userAgent && 0 !== strpos($userAgent, 'GitHub-Hookshot')) {
            // Check if the User-agent is Github
            // Otherwise, Send a '403: Forbidden'

            Log::error(
                'Invalid User agent: ' . $userAgent
                . '. Ignoring the event.'
            );
            $statusCode = 403;

            return $statusCode;
        } elseif (null !== $eventType && 'ping' === $eventType) {
            // Check if the request is based on 'issues' event
            // Otherwise, Send a '400: Bad Request'

            Log::info(
                'Ping event type recieved.'
            );
            $statusCode = 200;

            return $statusCode;
        } elseif (null !== $eventType && 'issues' !== $eventType) {
            // Check if the request is based on 'issues' event
            // Otherwise, Send a '400: Bad Request'

            Log::error(
                'Unexpected event type: ' . $eventType
                . '. Ignoring the event.'
            );
            $statusCode = 400;

            return $statusCode;
        } elseif ($recievedHash !== $expectedHash) {
            // Check if hash matches
            // Otherwise, Send a '401: Unauthorized'

            Log::error(
                'Recieved hash ' . $recievedHash . ' does not match '
                . ' expected hash ' . $expectedHash
                . '. Ignoring the event.'
            );
            $statusCode = 401;

            return $statusCode;
        }

        return $statusCode;
    }

    /**
     * Get the hash of raw POST payload.
     *
     * @param string $payload Raw POST body string
     * @param string $algo    Algorithm used to calculate the hash
     *
     * @return string Hmac Digest-based hash of payload
     */
    protected function _getHash($payload, $algo)
    {
        if ('' === $algo) {
            return '';
        }
        $key = Configure::read('GithubWebhookSecret');

        return hash_hmac($algo, $payload, $key);
    }

    /**
     * Get appropriate new status based on action recieved in github event.
     *
     * @param string $action Action recieved in Github webhook event
     *
     * @return string Appropriate new status for the related reports
     */
    protected function _getAppropriateStatus($action)
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
