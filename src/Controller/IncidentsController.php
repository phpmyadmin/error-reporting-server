<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
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
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Incidents controller handling incident creation and rendering.
 */
class IncidentsController extends AppController
{
    public $uses = array('Incident', 'Notification');

    public $components = array('Mailer');

    public function create()
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        $bugReport = $this->request->input('json_decode', true);
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);

        if (count($result['incidents']) > 0
            && !in_array(false, $result['incidents'])
        ) {
            $response = array(
                'success' => true,
                'message' => 'Thank you for your submission',
                'incident_id' => $result['incidents'],        // Return a list of incident ids.
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'There was a problem with your submission.',
            );
        }
        $this->autoRender = false;
        $this->response->header(array(
            'Content-Type' => 'application/json',
            'X-Content-Type-Options' => 'nosniff',
        ));
        $this->response->body(
            json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // For all the newly added reports,
        // send notification emails
        foreach ($result['reports'] as $report_id) {
            $this->_sendNotificationMail($report_id);
        }

        return $this->response;
    }

    public function json($id)
    {
        if (!isset($id) || !$id) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $this->Incidents->recursive = -1;
        $incident = $this->Incidents->findById($id)->all()->first();
        if (!$incident) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $incident['full_report'] =
            json_decode($incident['full_report'], true);
        $incident['stacktrace'] =
            json_decode($incident['stacktrace'], true);

        $this->autoRender = false;
        $this->response->body(json_encode($incident, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->response;
    }

    public function view($incidentId)
    {
        if (!isset($incidentId) || !$incidentId) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $incident = $this->Incidents->findById($incidentId)->all()->first();
        if (!$incident) {
            throw new NotFoundException(__('Invalid Incident'));
        }

        $incident['full_report'] =
            json_decode($incident['full_report'], true);
        $incident['stacktrace'] =
            json_decode($incident['stacktrace'], true);

        $this->set('incident', $incident);
    }

    private function _sendNotificationMail($reportId)
    {
        $this->Reports = TableRegistry::get('Reports');
        $report = $this->Reports->findById($reportId)->all()->first()->toArray();
        $this->Reports->id = $reportId;

        $viewVars = array(
            'report' => $report,
            'project_name' => Configure::read('GithubRepoPath'),
            'incidents' => $this->Reports->getIncidents()->toArray(),
            'incidents_with_description' => $this->Reports->getIncidentsWithDescription(),
            'incidents_with_stacktrace' => $this->Reports->getIncidentsWithDifferentStacktrace(),
            'related_reports' => $this->Reports->getRelatedReports(),
            'status' => $this->Reports->status
        );
        $viewVars = array_merge($viewVars, $this->_getSimilarFields($reportId));

        $this->Mailer->sendReportMail($viewVars);
    }

    protected function _getSimilarFields($id)
    {
        $this->Reports->id = $id;

        $viewVars = array(
            'columns' => TableRegistry::get('Incidents')->summarizableFields
        );
        $relatedEntries = array();

        foreach (TableRegistry::get('Incidents')->summarizableFields as $field) {
            list($entriesWithCount, $totalEntries) =
                    $this->Reports->getRelatedByField($field, 25, true);
            $relatedEntries[$field] = $entriesWithCount;
            $viewVars["${field}_distinct_count"] = $totalEntries;
        }

        $viewVars['related_entries'] = $relatedEntries;

        return $viewVars;
    }
}
