<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Sourceforge controller handling source forge ticket submission and creation.
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
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Github controller handling github issue submission and creation.
 */
class GithubController extends AppController
{
    public $helpers = array('Html', 'Form');

    public $components = array('GithubApi');

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->GithubApi->githubConfig = Configure::read('GithubConfig');
        $this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
    }

    /**
     * create Github Issue.
     *
     * @param int $reportId
     *
     * @throws \NotFoundException
     * @throws NotFoundException
     */
    public function create_issue($reportId)
    {
        if (!$reportId) {
            throw new \NotFoundException(__('Invalid report'));
        }

        $report = TableRegistry::get('Reports')->findById($reportId)->toArray();
        if (!$report) {
            throw new NotFoundException(__('Invalid report'));
        }

        if (empty($this->request->data)) {
            $this->set('pma_version', $report[0]['pma_version']);
            $this->set('error_name', $report[0]['error_name']);
            $this->set('error_message', $report[0]['error_message']);

            return;
        }
        $data = array(
            'title' => $this->request->data['summary'],
            'body' => $this->_augmentDescription(
                    $this->request->data['description'], $reportId),
            'labels' => $this->request->data['labels'] ? explode(',', $this->request->data['labels']) : array(),
        );
        $data['labels'][] = 'automated-error-report';
        list($issueDetails, $status) = $this->GithubApi->createIssue(
            Configure::read('GithubRepoPath'),
            $data,
            $this->request->session()->read('access_token')
        );

        if ($this->_handleGithubResponse($status, 1, $reportId, $issueDetails['number'])) {
            $this->redirect(array('controller' => 'reports', 'action' => 'view',
                $reportId, ));
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default(_getErrors($issueDetails, $status),
                array('params' => array('class' => $flash_class)));
        }
    }

    /**
     * Links error report to existing issue on Github.
     *
     * @param mixed $reportId
     */
    public function link_issue($reportId)
    {
        if (!$reportId) {
            throw new NotFoundException(__('Invalid reportId'));
        }

        $report = TableRegistry::get('Reports')->findById($reportId)->all()->first();
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $ticket_id = intval($this->request->query['ticket_id']);
        if (!$ticket_id) {
            throw new NotFoundException(__('Invalid Ticket ID!!'));
        }

        $incidents_query = TableRegistry::get('Incidents')
            ->findByReportId($reportId)->all();
        $incident = $incidents_query->first();

        $exception_type = $incident['exception_type'] ? 'php' : 'js';
        $incident_count = $this->_getTotalIncidentCount($reportId);

        // "formatted" text of the comment.
        $commentText = 'Param | Value '
            . "\n -----------|--------------------"
            . "\n Error Type | " . $report['error_name']
            . "\n Error Message |" . $report['error_message']
            . "\n Exception Type |" . $exception_type
            . "\n phpMyAdmin version |" . $report['pma_version']
            . "\n Incident count | " . $incident_count
            . "\n Link | [Report#"
                . $reportId
                . ']('
                . Router::url('/reports/view/' . $reportId, true)
                . ')'
            . "\n\n*This comment is posted automatically by phpMyAdmin's "
            . '[error-reporting-server](https://reports.phpmyadmin.net).*';

        list($commentDetails, $status) = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            array('body' => $commentText),
            $ticket_id,
            $this->request->session()->read('access_token')
        );
        if (!$this->_handleGithubResponse($status, 2, $reportId, $ticket_id)) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(_getErrors($commentDetails, $status),
                    array('params' => array('class' => $flash_class)));
        }

        $this->redirect(array('controller' => 'reports', 'action' => 'view',
                        $reportId, ));
    }

    /**
     * Un-links error report to associated issue on Github.
     *
     * @param mixed $reportId
     */
    public function unlink_issue($reportId)
    {
        if (!$reportId) {
            throw new NotFoundException(__('Invalid reportId'));
        }

        $report = TableRegistry::get('Reports')->findById($reportId)->all()->first();
        if (!$report) {
            throw new NotFoundException(__('Invalid Report'));
        }

        $ticket_id = $report['sourceforge_bug_id'];
        if (!$ticket_id) {
            throw new NotFoundException(__('Invalid Ticket ID!!'));
        }

        // "formatted" text of the comment.
        $commentText = 'This Issue is no longer associated with [Report#'
            . $reportId
            . ']('
            . Router::url('/reports/view/' . $reportId, true)
            . ')'
            . "\n\n*This comment is posted automatically by phpMyAdmin's "
            . '[error-reporting-server](https://reports.phpmyadmin.net).*';

        list($commentDetails, $status) = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            array('body' => $commentText),
            $ticket_id,
            $this->request->session()->read('access_token')
        );

        if (!$this->_handleGithubResponse($status, 3, $reportId)) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(_getErrors($commentDetails, $status),
                    array('params' => array('class' => $flash_class)));
        }

        $this->redirect(array('controller' => 'reports', 'action' => 'view',
                        $reportId, ));
    }

    /**
     * Returns pretty error message string.
     *
     * @param object $response the response returned by Github api
     * @param int    $status   status returned by Github API
     *
     * @return error string
     */
    protected function _getErrors($response, $status)
    {
        $errorString = 'There were some problems with the issue submission.'
            . ' Returned status is (' . $status . ')';
        $errorString .= '<br/> Here is the dump for the errors field provided by'
            . ' github: <br/>'
            . '<pre>'
            . print_r($response, true)
            . '</pre>';

        return $errorString;
    }

    /**
     * Returns the description with the added string to link to the report.
     *
     * @param string $description the original description submitted by the dev
     * @param string $reportId    the report id relating to the ticket
     *
     * @return string augmented description
     */
    protected function _augmentDescription($description, $reportId)
    {
        $report = TableRegistry::get('Reports');
        $report->id = $reportId;
        $incident_count = $this->_getTotalIncidentCount($reportId);

        return "$description\n\n\nThis report is related to user submitted report "
            . '[#' . $report->id . '](' . $report->getUrl()
            . ') on the phpmyadmin error reporting server.'
            . 'It, along with its related reports, has been reported **'
            . $incident_count . '** times.';
    }

    /**
     * Github Response Handler.
     *
     * @param int $response  the status returned by Github API
     * @param int $type      type of response. 1 for create_issue, 2 for link_issue, 3 for unlink_issue,
     *                       1 for create_issue,
     *                       2 for link_issue,
     *                       3 for unlink_issue,
     * @param int $report_id report id
     * @param int $ticket_id ticket id, required for link tivket only
     *
     * @return bool value. True on success. False on any type of failure.
     */
    protected function _handleGithubResponse($response, $type, $report_id, $ticket_id = 1)
    {
        if (!in_array($type, array(1, 2, 3))) {
            throw new InvalidArgumentException('Invalid Argument "$type".');
        }

        if ($response == 201) {
            echo $response;
            // success
            switch ($type) {
                case 1:
                    $msg = 'Github issue has been created for this report.';
                    break;
                case 2:
                    $msg = 'Github issue has been linked with this report.';
                    break;
                case 3:
                    $msg = 'Github issue has been unlinked with this report.';
                    $ticket_id = null;

                    break;
                default:
                    $msg = 'Something went wrong!!';
                    break;
            }
            $report = TableRegistry::get('Reports')->get($report_id);
            $report->sourceforge_bug_id = $ticket_id;
            TableRegistry::get('Reports')->save($report);
            $flash_class = 'alert alert-success';
            $this->Flash->default($msg,
                array('params' => array('class' => $flash_class)));

            return true;
        } elseif ($response === 403) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                    'Unauthorised access to Github. github'
                    . ' credentials may be out of date. Please check and try again'
                    . ' later.',
                    array('params' => array('class' => $flash_class)));

            return false;
        } elseif ($response === 404
            && $type == 2
        ) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                    'Bug Issue not found on Github.'
                    . ' Are you sure the issue number is correct? Please check and try again!',
                     array('params' => array('class' => $flash_class)));

            return false;
        }
            //fail
            $flash_class = 'alert alert-error';
        $this->Flash->default(json_encode($response),
                    array('params' => array('class' => $flash_class)));

        return false;
    }

    /**
     * Get Incident counts for a report and
     * all its related reports
     *
     * @param $reportId Report ID
     *
     * @return $total_incident_count Total Incident count for a report
     */
    protected function _getTotalIncidentCount($reportId)
    {
        $incidents_query = TableRegistry::get('Incidents')->findByReportId($reportId)->all();
        $incident_count = $incidents_query->count();

        $params_count = array(
            'fields' => array('inci_count' => 'inci_count'),
            'conditions' => array(
                    'related_to = ' . $reportId,
            ),
        );
        $subquery_params_count = array(
            'fields' => array(
                'report_id' => 'report_id',
            ),
        );
        $subquery_count = TableRegistry::get('Incidents')->find(
            'all', $subquery_params_count
        );
        $inci_count_related = TableRegistry::get('Reports')->find('all', $params_count)->innerJoin(
                array('incidents' => $subquery_count),
                array('incidents.report_id = Reports.related_to')
            )->count();

        return $incident_count + $inci_count_related;
    }

    /*
     * Synchronize Report Statuses from Github issue
     * To be used as a cron job.
     * Can not (& should not) be directly accessed via Web.
     * TODO
     */
    /* public function sync_issue_statuses(){
        if (!defined('CRON_DISPATCHER')) {
            $this->redirect('/');
            exit();
        }

        $reports = TableRegistry::get('Reports')->find(
            'all',
            array(
                'conditions' => array(
                    'NOT' => array(
                        'Report.sourceforge_bug_id' => null
                    )
                )
            )
        );

        foreach ($reports as $key => $report) {
            $i=0;
            // fetch the new ticket status
            do {
                $new_status = $this->SourceForgeApi->getBugTicketStatus(
                    Configure::read('SourceForgeProjectName'),
                    $report['sourceforge_bug_id']
                );
                $i++;
            } while($new_status == false && $i <= 3);

            // if fails all three times, then simply write failure
            // into cron_jobs log and move on.
            if (!$new_status) {
                Log::write(
                    'cron_jobs',
                    'FAILED: Fetching status of BugTicket#'
                        . ($report['sourceforge_bug_id'])
                        . ' associated with Report#'
                        . ($report['id']),
                    'cron_jobs'
                );
                continue;
            }

            if ($report['status'] != $new_status) {
                $rep = TableRegistry::get('Reports')->get($report['id']);
                $rep->status = $new_status;
                TableRegistry::get('Reports')->save($rep);
            }
        }
        $this->autoRender = false;
    } */
}
