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

use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use function __;
use function array_merge;
use function count;
use function in_array;
use function json_decode;
use function json_encode;
use App\Model\Table\NotificationsTable;
use App\Model\Table\IncidentsTable;
use App\Model\Table\ReportsTable;

/**
 * Incidents controller handling incident creation and rendering.
 *
 * @property NotificationsTable $Notifications
 * @property IncidentsTable $Incidents
 * @property ReportsTable $Reports
 */
class IncidentsController extends AppController
{
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
        $this->loadModel('Notifications');
        $this->loadModel('Incidents');
    }

    public function create(): ?Response
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        $bugReport = json_decode((string) $this->request->getBody(), true);
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);

        if (count($result['incidents']) > 0
            && ! in_array(false, $result['incidents'])
        ) {
            $response = [
                'success' => true,
                'message' => 'Thank you for your submission',
                'incident_id' => $result['incidents'],        // Return a list of incident ids.
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'There was a problem with your submission.',
            ];
        }
        $this->disableAutoRender();

        $this->response = $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withStringBody(
                json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

        // For all the newly added reports,
        // send notification emails
        foreach ($result['reports'] as $report_id) {
            $this->sendNotificationMail($report_id);
        }

        return $this->response;
    }

    public function json(?string $id): ?Response
    {
        if (empty($id)) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $this->Incidents->recursive = -1;
        $incident = $this->Incidents->findById($id)->all()->first();
        if (! $incident) {
            throw new NotFoundException(__('Invalid Incident'));
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

    public function view(?string $incidentId): void
    {
        if (empty($incidentId)) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $incident = $this->Incidents->findById($incidentId)->all()->first();
        if (! $incident) {
            throw new NotFoundException(__('Invalid Incident'));
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
        $this->Reports->id = $reportId;

        $viewVars = [
            'report' => $report,
            'project_name' => Configure::read('GithubRepoPath'),
            'incidents' => $this->Reports->getIncidents()->toArray(),
            'incidents_with_description' => $this->Reports->getIncidentsWithDescription(),
            'incidents_with_stacktrace' => $this->Reports->getIncidentsWithDifferentStacktrace(),
            'related_reports' => $this->Reports->getRelatedReports(),
            'status' => $this->Reports->status,
        ];
        $viewVars = array_merge($viewVars, $this->getSimilarFields($reportId));

        $this->Mailer->sendReportMail($viewVars);
    }

    protected function getSimilarFields(int $id): array
    {
        $this->Reports->id = $id;

        $viewVars = [
            'columns' => TableRegistry::getTableLocator()->get('Incidents')->summarizableFields,
        ];
        $relatedEntries = [];

        foreach (TableRegistry::getTableLocator()->get('Incidents')->summarizableFields as $field) {
            [$entriesWithCount, $totalEntries] =
                    $this->Reports->getRelatedByField($field, 25, true);
            $relatedEntries[$field] = $entriesWithCount;
            $viewVars["${field}_distinct_count"] = $totalEntries;
        }

        $viewVars['related_entries'] = $relatedEntries;

        return $viewVars;
    }
}
