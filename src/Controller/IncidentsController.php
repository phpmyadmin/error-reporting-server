<?php

/**
 * Incidents controller handling incident creation and rendering.
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

use App\Model\Table\IncidentsTable;
use App\Model\Table\NotificationsTable;
use App\Model\Table\ReportsTable;
use App\Report;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

use function array_merge;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Incidents controller handling incident creation and rendering.
 */
class IncidentsController extends AppController
{
    /**
     * The largest report body accepted, in bytes.
     *
     * Reports are submitted without authentication and each new report sends
     * a notification email, so the size of a single submission is bounded
     * before it is decoded.
     */
    private const int MAX_REQUEST_BODY_SIZE = 1048576;

    protected NotificationsTable $Notifications;
    protected IncidentsTable $Incidents;
    protected ReportsTable $Reports;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * @return void Nothing
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Mailer');
        $this->Notifications = $this->fetchTable('Notifications');
        $this->Incidents = $this->fetchTable('Incidents');
        $this->Reports = $this->fetchTable('Reports');
    }

    public function create(): Response|null
    {
        $this->disableAutoRender();
        $this->response = $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Content-Type-Options', 'nosniff');

        // Only allow POST requests
        $this->request->allowMethod(['post']);

        $requestBody = (string) $this->request->getBody();

        // Refuse oversized submissions before decoding them
        if (strlen($requestBody) > self::MAX_REQUEST_BODY_SIZE) {
            $this->response = $this->response
                ->withStatus(413)
                ->withStringBody($this->rejectionBody());

            return $this->response;
        }

        $bugReport = json_decode($requestBody, true);

        if (! is_array($bugReport) || $requestBody === '') {
            $response = ['success' => false];

            $this->response = $this->response
                ->withStringBody(
                    json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                );

            return $this->response;
        }

        // The report is forwarded later, by a command that has no request: the
        // salted hash of the address travels with the payload, or the identity
        // the tracker groups the events by is the same for every user. The
        // address itself is never stored.
        $bugReport['client_ip_hash'] = Report::hashClientIp($this->request->clientIp());

        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $accepted = count($result['incidents']) > 0 && ! in_array(false, $result['incidents']);

        // The forwarding to the tracker is done by the forward_reports command
        // on the reports that carry no forwarded_at: it used to run here, one
        // request per event, and a slow tracker delayed this answer and the
        // notification mail below.

        if ($accepted) {
            $response = ['success' => true];
        } else {
            $response = ['success' => false];
        }

        $this->response = $this->response
            ->withStringBody(
                json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

        // For all the newly added reports,
        // send notification emails
        foreach ($result['reports'] as $report_id) {
            $this->sendNotificationMail($report_id);
        }

        return $this->response;
    }

    /**
     * Builds the body of a refused report.
     *
     * @return string the JSON encoded failure answer
     */
    private function rejectionBody(): string
    {
        return json_encode(
            ['success' => false],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    public function json(string|null $id): Response|null
    {
        if (empty($id)) {
            throw new NotFoundException('Invalid Incident');
        }

        $incident = $this->Incidents->findById($id)->all()->first();
        if (! $incident) {
            throw new NotFoundException('The incident does not exist.');
        }

        $incident['full_report'] =
            json_decode($incident['full_report'], true);
        $incident['stacktrace'] =
            json_decode($incident['stacktrace'], true);

        $this->disableAutoRender();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($incident, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function view(string|null $incidentId): void
    {
        if (empty($incidentId)) {
            throw new NotFoundException('Invalid Incident');
        }

        $incidentId = (int) $incidentId;

        $incident = $this->Incidents->findById($incidentId)->all()->first();
        if (! $incident) {
            throw new NotFoundException('The incident does not exist.');
        }

        $incident['full_report'] =
            json_decode($incident['full_report'], true);
        $incident['stacktrace'] =
            json_decode($incident['stacktrace'], true);

        $this->set('incident', $incident);
    }

    private function sendNotificationMail(int $reportId): void
    {
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $report = $this->Reports->findById($reportId)->all()->first()->toArray();

        // What the template prints: the report, its incidents, and the fields
        // it summarises the submission by. The two incident lists and the list
        // of related reports used to be built here too and are printed
        // nowhere: 27 queries per mail for three values nobody read.
        $viewVars = [
            'report' => $report,
            'incidents' => $this->Reports->getIncidents($reportId)->toArray(),
        ];
        $viewVars = array_merge($viewVars, $this->getSimilarFields($reportId));

        $this->Mailer->sendReportMail($viewVars);
    }

    /**
     * The distinct values of the fields a submission is summarised by.
     *
     * @param int $id The report
     *
     * @return array<string, mixed> The values the mail template prints
     */
    protected function getSimilarFields(int $id): array
    {
        $fields = TableRegistry::getTableLocator()->get('Incidents')->summarizableFields;
        $viewVars = ['columns' => $fields];
        $relatedEntries = [];

        foreach ($fields as $field) {
            [$entriesWithCount, $totalEntries] =
                    $this->Reports->getRelatedByField($field, 25, true, $id);
            $relatedEntries[$field] = $entriesWithCount->toArray();
            $viewVars[$field . '_distinct_count'] = $totalEntries;
        }

        $viewVars['related_entries'] = $relatedEntries;

        return $viewVars;
    }
}
