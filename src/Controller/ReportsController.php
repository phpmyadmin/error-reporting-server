<?php

/**
 * Reports controller handling reports creation and rendering.
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

use App\Model\Table\DevelopersTable;
use App\Model\Table\IncidentsTable;
use App\Model\Table\NotificationsTable;
use App\Model\Table\ReportsTable;
use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

use function array_key_exists;
use function array_push;
use function array_unshift;
use function count;
use function json_encode;

/**
 * Reports controller handling reports modification and rendering.
 */
class ReportsController extends AppController
{
    protected NotificationsTable $Notifications;
    protected DevelopersTable $Developers;
    protected ReportsTable $Reports;
    protected IncidentsTable $Incidents;

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
        $this->loadComponent('OrderSearch');
        $this->viewBuilder()->setHelpers([
            'Html',
            'Form',
            'Reports',
            'Incidents',
        ]);
        $this->Notifications = $this->fetchTable('Notifications');
        $this->Developers = $this->fetchTable('Developers');
        $this->Reports = $this->fetchTable('Reports');
        $this->Incidents = $this->fetchTable('Incidents');
    }

    public function index(): void
    {
        $this->Reports->recursive = -1;
        $this->set(
            'distinct_statuses',
            $this->findArrayList(
                $this->Reports->find()->select(['status'])->distinct(['status']),
                'status'
            )
        );
        $this->set(
            'distinct_locations',
            $this->findArrayList(
                $this->Reports->find()->select(['location'])
                    ->distinct(['location']),
                'location'
            )
        );
        $this->set(
            'distinct_versions',
            $this->findArrayList($this->Reports->find()->select(['pma_version'])->distinct(['pma_version']), 'pma_version')
        );
        $this->set(
            'distinct_error_names',
            $this->findArrayList($this->Reports->find(
                'all',
                fields: ['error_name'],
                conditions: ['error_name !=' => '']
            )->distinct(['error_name']), 'error_name')
        );
        $this->set('statuses', $this->Reports->status);
        $this->autoRender = true;
    }

    public function view(?string $reportId): void
    {
        if (empty($reportId)) {
            throw new NotFoundException('Invalid report Id.');
        }

        $reportId = (int) $reportId;

        $report = $this->Reports->findById($reportId)->toArray();
        if (! $report) {
            throw new NotFoundException('The report does not exist.');
        }

        $this->set('report', $report);
        $this->set('project_name', Configure::read('GithubRepoPath'));
        $this->Reports->id = $reportId;
        $this->set('incidents', $this->Reports->getIncidents()->toArray());
        $this->set(
            'incidents_with_description',
            $this->Reports->getIncidentsWithDescription()
        );
        $this->set(
            'incidents_with_stacktrace',
            $this->Reports->getIncidentsWithDifferentStacktrace()
        );
        $this->set('related_reports', $this->Reports->getRelatedReports()->all());
        $this->set('status', $this->Reports->status);
        $this->setSimilarFields($reportId);

        // if there is an unread notification for this report, then mark it as read
        $current_developer = TableRegistry::getTableLocator()->get('Developers')->
                    findById($this->request->getSession()->read('Developer.id'))->all()->first();

        if (! $current_developer || ! $current_developer['Developer']) {
            return;
        }

        TableRegistry::getTableLocator()->get('Notifications')->deleteAll(
            [
                'developer_id' => $current_developer['Developer']['id'],
                'report_id' => $reportId,
            ]
        );
    }

    public function data_tables(): ?Response
    {
        $subquery_params = [
            'fields' => [
                'report_id' => 'report_id',
                'inci_count' => 'COUNT(id)',
            ],
            'group' => 'report_id',
        ];
        $subquery = TableRegistry::getTableLocator()->get('incidents')->find('all', $subquery_params);

        // override automatic aliasing, for proper usage in joins
        $aColumns = [
            'id' => 'id',
            'error_name' => 'error_name',
            'error_message' => 'error_message',
            'location' => 'location',
            'pma_version' => 'pma_version',
            'status' => 'status',
            'exception_type' => 'exception_type',
            'inci_count' => 'inci_count',
        ];

        $searchConditions = $this->OrderSearch->getSearchConditions($aColumns, $this->request);
        $orderConditions = $this->OrderSearch->getOrder($aColumns, $this->request);

        $params = [
            'fields' => $aColumns,
            'conditions' => [
                $searchConditions,
                'related_to is NULL',
            ],
            'order' => $orderConditions,
        ];

        $pagedParams = $params;
        $pagedParams['limit'] = (int) $this->request->getQuery('iDisplayLength');
        $pagedParams['offset'] = (int) $this->request->getQuery('iDisplayStart');

        $rows = $this->findAllDataTable(
            $this->Reports->find(
                'all',
                fields: $pagedParams['fields'],
                conditions: $pagedParams['conditions'],
                order: $pagedParams['order'],
                limit: $pagedParams['limit'],
                offset: $pagedParams['offset'],
            )->innerJoin(
                ['incidents' => $subquery],
                ['incidents.report_id = Reports.id']
            )
        );
        //$rows = Sanitize::clean($rows);
        $totalFiltered = $this->Reports->find('all', $params)->count();

        // change exception_type from boolean values to strings
        // add incident count for related reports
        $dispRows = [];
        foreach ($rows as $row) {
            $row[5] = $this->Reports->status[$row[5]];
            $row[6] = (int) $row[6] ? 'php' : 'js';
            $input_elem = '<input type="checkbox" name="reports[]" value="'
                . $row[0]
                . '"/>';

            $subquery_params_count = [
                'fields' => ['report_id' => 'report_id'],
            ];
            $subquery_count = TableRegistry::getTableLocator()->get('incidents')->find(
                'all',
                $subquery_params_count
            );

            $params_count = [
                'fields' => ['inci_count' => 'inci_count'],
                'conditions' => [
                    'related_to = ' . $row[0],
                ],
            ];

            $inci_count_related = $this->Reports->find('all', $params_count)->innerJoin(
                ['incidents' => $subquery_count],
                ['incidents.report_id = Reports.related_to']
            )->count();

            $row[7] += $inci_count_related;

            array_unshift($row, $input_elem);
            array_push($dispRows, $row);
        }

        $response = [
            'iTotalRecords' => $this->Reports->find('all')->count(),
            'iTotalDisplayRecords' => $totalFiltered,
            'sEcho' => (int) $this->request->getQuery('sEcho'),
            'aaData' => $dispRows,
        ];
        $this->disableAutoRender();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    public function mark_related_to(?string $reportId): Response
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        $relatedTo = $this->request->getData('related_to');
        if (
            ! $reportId
            || ! $relatedTo
            || $reportId === $relatedTo
        ) {
            throw new NotFoundException('Invalid Report');
        }

        $report = $this->Reports->get($reportId);
        if (! $report) {
            throw new NotFoundException('The report does not exist.');
        }

        $this->Reports->addToRelatedGroup($report, $relatedTo);

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'This report has been marked the same as #'
                . $relatedTo,
            ['params' => ['class' => $flash_class]]
        );

        return $this->redirect('/reports/view/' . $reportId);
    }

    public function unmark_related_to(?string $reportId): Response
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        if (! $reportId) {
            throw new NotFoundException('Invalid Report');
        }

        $report = $this->Reports->get($reportId);
        if (! $report) {
            throw new NotFoundException('The report does not exist.');
        }

        $this->Reports->removeFromRelatedGroup($report);

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'This report has been marked as different.',
            ['params' => ['class' => $flash_class]]
        );

        return $this->redirect('/reports/view/' . $reportId);
    }

    public function change_state(?string $reportId): Response
    {
        if (! $reportId) {
            throw new NotFoundException('Invalid Report');
        }

        $report = $this->Reports->get($reportId);
        if (! $report) {
            throw new NotFoundException('The report does not exist.');
        }

        $state = $this->request->getData('state');
        $newState = null;

        if (array_key_exists($state, $this->Reports->status)) {
            $newState = $this->Reports->status[$state];
        }

        if (! $newState) {
            throw new NotFoundException('Invalid State');
        }

        $report->status = $state;
        $this->Reports->save($report);

        $flash_class = 'alert alert-success';
        $this->Flash->set(
            'The state has been successfully changed.',
            ['params' => ['class' => $flash_class]]
        );

        return $this->redirect('/reports/view/' . $reportId);
    }

    /**
     * To carry out mass actions on Reports
     * Currently only to change their statuses.
     * Can be Extended for other mass operations as well.
     * Expects an array of Report Ids as a POST parameter.
     */
    public function mass_action(): Response
    {
        $flash_class = 'alert alert-error';
        $state = $this->request->getData('state');
        $newState = null;
        if (array_key_exists($state, $this->Reports->status)) {
            $newState = $this->Reports->status[$state];
        }

        if (! $newState) {
            Log::write(
                'error',
                'ERRORED: Invalid param "state" in ReportsController::mass_action()',
                'alert'
            );
            $msg = 'ERROR: Invalid State!!';
        } elseif (count($this->request->getData('reports')) === 0) {
            $msg = 'No Reports Selected!! Please Select Reports and try again.';
        } else {
            $msg = "Status has been changed to '"
                . $this->request->getData('state')
                . "' for selected Reports!";
            $flash_class = 'alert alert-success';
            foreach ($this->request->getData('reports') as $report_id) {
                $report = $this->Reports->get($report_id);
                if (! $report) {
                    Log::write(
                        'error',
                        'ERRORED: Invalid report_id in ReportsController::mass_action()',
                        'alert'
                    );
                    $msg = 'ERROR:Invalid Report ID:' . $report_id;
                    $flash_class = 'alert alert-error';
                    break;
                }

                $report->status = $state;
                $this->Reports->save($report);
            }
        }

        $this->Flash->set(
            $msg,
            ['params' => ['class' => $flash_class]]
        );

        return $this->redirect('/reports/');
    }

    protected function setSimilarFields(int $id): void
    {
        $this->Reports->id = $id;

        $this->set('columns', TableRegistry::getTableLocator()->get('Incidents')->summarizableFields);
        $relatedEntries = [];

        foreach (TableRegistry::getTableLocator()->get('Incidents')->summarizableFields as $field) {
            [$entriesWithCount, $totalEntries] =
                    $this->Reports->getRelatedByField($field, 25, true);
            $relatedEntries[$field] = $entriesWithCount->toArray();
            $this->set($field . '_distinct_count', $totalEntries);
        }

        $this->set('related_entries', $relatedEntries);
    }

    /**
     * @param array|mixed $results The row
     * @param string      $key     The search in the row
     * @return array Results
     */
    protected function findArrayList($results, string $key): array
    {
        $output = [];
        foreach ($results as $row) {
            $output[] = $row[$key];
        }

        return $output;
    }

    /**
     * @param mixed $results
     * @return array
     */
    protected function findAllDataTable($results): array
    {
        $output = [];
        foreach ($results as $row) {
            $output_row = [];
            $row = $row->toArray();
            foreach ($row as $key => $value) {
                $output_row[] = $value;
            }

            $output[] = $output_row;
        }

        return $output;
    }
}
