<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

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

use App\Utility\Sanitize;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Reports controller handling reports modification and rendering.
 */
class ReportsController extends AppController
{
    public $components = array('RequestHandler', 'OrderSearch');

    public $helpers = array('Html', 'Form', 'Reports', 'Incidents');
    public $uses = array('Incidents', 'Reports', 'Notifications', 'Developers');

    public function index()
    {
        $this->Reports->recursive = -1;
        $this->set('distinct_statuses',
            $this->_findArrayList($this->Reports->find()->select(array('status'))->distinct(array('status')),
            'status')
        );
        $this->set(
            'distinct_locations',
            $this->_findArrayList(
                $this->Reports->find()->select(array('location'))
                    ->distinct(array('location')),
                'location'
            )
        );
        $this->set('distinct_versions',
            $this->_findArrayList($this->Reports->find()->select(array('pma_version'))->distinct(array('pma_version')), 'pma_version')
        );
        $this->set('distinct_error_names',
            $this->_findArrayList($this->Reports->find('all', array(
                'fields' => array('error_name'),
                'conditions' => array('error_name !=' => ''),
            ))->distinct(array('error_name')), 'error_name')
        );
        $this->set('statuses', $this->Reports->status);
        $this->autoRender = true;
    }

    public function view($reportId)
    {
        if (!isset($reportId) || !$reportId) {
            throw new NotFoundException(__('Invalid Report'));
        }
        $report = $this->Reports->findById($reportId)->toArray();
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $this->set('report', $report);
        $this->set('project_name', Configure::read('GithubRepoPath'));
        $this->Reports->id = $reportId;
        $this->set('incidents', $this->Reports->getIncidents()->toArray());
        $this->set('incidents_with_description',
            $this->Reports->getIncidentsWithDescription());
        $this->set('incidents_with_stacktrace',
            $this->Reports->getIncidentsWithDifferentStacktrace());
        $this->set('related_reports', $this->Reports->getRelatedReports());
        $this->set('status', $this->Reports->status);
        $this->_setSimilarFields($reportId);

        // if there is an unread notification for this report, then mark it as read
        $current_developer = TableRegistry::get('Developers')->
                    findById($this->request->session()->read('Developer.id'))->all()->first();

        if ($current_developer) {
            TableRegistry::get('Notifications')->deleteAll(
                array('developer_id' => $current_developer['Developer']['id'],
                    'report_id' => $reportId,
                ),
                false
            );
        }
    }

    public function data_tables()
    {
        $subquery_params = array(
            'fields' => array(
                'report_id' => 'report_id',
                'inci_count' => 'COUNT(id)',
                ),
            'group' => 'report_id',
        );
        $subquery = TableRegistry::get('incidents')->find('all', $subquery_params);

        // override automatic aliasing, for proper usage in joins
        $aColumns = array(
            'id' => 'id',
            'error_name' => 'error_name',
            'error_message' => 'error_message',
            'location' => 'location',
            'pma_version' => 'pma_version',
            'status' => 'status',
            'exception_type' => 'exception_type',
            'inci_count' => 'inci_count',
        );

        $searchConditions = $this->OrderSearch->getSearchConditions($aColumns);
        $orderConditions = $this->OrderSearch->getOrder($aColumns);

        $params = array(
            'fields' => $aColumns,
            'conditions' => array(
                    $searchConditions,
                    'related_to is NULL',
                ),
            'order' => $orderConditions,
        );

        $pagedParams = $params;
        $pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
        $pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

        $rows = $this->_findAllDataTable(
            $this->Reports->find('all', $pagedParams)->innerJoin(
                array('incidents' => $subquery), array('incidents.report_id = Reports.id')
            )
        );
        //$rows = Sanitize::clean($rows);
        $totalFiltered = $this->Reports->find('all', $params)->count();

        // change exception_type from boolean values to strings
        // add incident count for related reports
        $dispRows = array();
        foreach ($rows as $row) {
            $row[5] = $this->Reports->status[$row[5]];
            $row[6] = (intval($row[6])) ? ('php') : ('js');
            $input_elem = "<input type='checkbox' name='reports[]' value='"
                . $row[0]
                . "'/>";

            $subquery_params_count = array(
                'fields' => array(
                    'report_id' => 'report_id',
                ),
            );
            $subquery_count = TableRegistry::get('incidents')->find(
                'all', $subquery_params_count
            );

            $params_count = array(
                'fields' => array('inci_count' => 'inci_count'),
                'conditions' => array(
                        'related_to = ' . $row[0],
                ),
            );

            $inci_count_related = $this->Reports->find('all', $params_count)->innerJoin(
                array('incidents' => $subquery_count),
                array('incidents.report_id = Reports.related_to')
            )->count();

            $row[7] += $inci_count_related;

            array_unshift($row, $input_elem);
            array_push($dispRows, $row);
        }

        $response = array(
            'iTotalRecords' => $this->Reports->find('all')->count(),
            'iTotalDisplayRecords' => $totalFiltered,
            'sEcho' => intval($this->request->query('sEcho')),
            'aaData' => $dispRows
        );
        $this->autoRender = false;
        $this->response->body(json_encode($response));

        return $this->response;
    }

    public function mark_related_to($reportId)
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        $relatedTo = $this->request->getData('related_to');
        if (!$reportId
            || !$relatedTo
            || $reportId == $relatedTo
        ) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $report = $this->Reports->get($reportId);
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $this->Reports->addToRelatedGroup($report, $relatedTo);

        $flash_class = 'alert alert-success';
        $this->Flash->default('This report has been marked the same as #'
                . $relatedTo,
                array('params' => array('class' => $flash_class)));
        $this->redirect("/reports/view/$reportId");
    }

    public function unmark_related_to($reportId)
    {
        // Only allow POST requests
        $this->request->allowMethod(['post']);

        if (!$reportId) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $report = $this->Reports->get($reportId);
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $this->Reports->removeFromRelatedGroup($report);

        $flash_class = 'alert alert-success';
        $this->Flash->default('This report has been marked as different.',
            array('params' => array('class' => $flash_class)));
        $this->redirect("/reports/view/$reportId");
    }

    public function change_state($reportId)
    {
        if (!$reportId) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $report = $this->Reports->get($reportId);
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $state = $this->request->data['state'];
        $newState = null;

        if (array_key_exists($state, $this->Reports->status)) {
            $newState = $this->Reports->status[$state];
        }
        if (!$newState) {
            throw new NotFoundException(__('Invalid State'));
        }
        $report->status = $state;
        $this->Reports->save($report);

        $flash_class = 'alert alert-success';
        $this->Flash->default('The state has been successfully changed.',
            array('params' => array('class' => $flash_class)));
        $this->redirect("/reports/view/$reportId");
    }

    /**
     * To carry out mass actions on Reports
     * Currently only to change their statuses.
     * Can be Extended for other mass operations as well.
     * Expects an array of Report Ids as a POST parameter.
     */
    public function mass_action()
    {
        $flash_class = 'alert alert-error';
        $state = $this->request->data['state'];
        $newState = null;
        if (array_key_exists($state, $this->Reports->status)) {
            $newState = $this->Reports->status[$state];
        }

        if (!$newState) {
            Log::write(
                'error',
                'ERRORED: Invalid param "state" in ReportsController::mass_action()',
                'alert'
            );
            $msg = 'ERROR: Invalid State!!';
        } elseif (count($this->request->data['reports']) == 0) {
            $msg = 'No Reports Selected!! Please Select Reports and try again.';
        } else {
            $msg = "Status has been changed to '"
                . $this->request->data['state']
                . "' for selected Reports!";
            $flash_class = 'alert alert-success';
            foreach ($this->request->data['reports'] as $report_id) {
                $report = $this->Reports->get($report_id);
                if (!$report) {
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

        $this->Flash->default($msg,
            array('params' => array('class' => $flash_class)));
        $this->redirect('/reports/');
    }

    //# HELPERS

    protected function _setSimilarFields($id)
    {
        $this->Reports->id = $id;

        $this->set('columns', TableRegistry::get('Incidents')->summarizableFields);
        $relatedEntries = array();

        foreach (TableRegistry::get('Incidents')->summarizableFields as $field) {
            list($entriesWithCount, $totalEntries) =
                    $this->Reports->getRelatedByField($field, 25, true);
            $relatedEntries[$field] = $entriesWithCount;
            $this->set("${field}_distinct_count", $totalEntries);
        }
        //error_log(json_encode($relatedEntries));
        $this->set('related_entries', $relatedEntries);
    }

    protected function _findArrayList($results, $key)
    {
        $output = array();
        foreach ($results as $row) {
            $output[] = $row[$key];
        }

        return $output;
    }

    protected function _findAllDataTable($results)
    {
        $output = array();
        foreach ($results as $row) {
            $output_row = array();
            $row = $row->toArray();
            foreach ($row as $key => $value) {
                $output_row[] = $value;
            }
            $output[] = $output_row;
        }

        return $output;
    }
}
