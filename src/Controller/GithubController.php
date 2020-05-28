<?php

/**
 * Github controller handling issue creation, comments and sync.
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
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use InvalidArgumentException;
use function __;
use function array_key_exists;
use function explode;
use function in_array;
use function intval;
use function print_r;

/**
 * Github controller handling github issue submission and creation.
 */
class GithubController extends AppController
{
    /** @var string */
    public $helpers = [
        'Html',
        'Form',
    ];
    /** @var string */
    public $components = ['GithubApi'];

    public function beforeFilter(Event $event): void
    {
        parent::beforeFilter($event);
        $this->GithubApi->githubConfig = Configure::read('GithubConfig');
        $this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
    }

    /**
     * create Github Issue.
     *
     * @param int $reportId The report number
     *
     * @throws NotFoundException
     * @return void Nothing
     */
    public function create_issue(int $reportId): void
    {
        if (! isset($reportId) || ! $reportId) {
            throw new NotFoundException(__('Invalid report'));
        }

        $reportsTable = TableRegistry::getTableLocator()->get('Reports');
        $report = $reportsTable->findById($reportId)->all()->first();

        if (! $report) {
            throw new NotFoundException(__('Invalid report'));
        }

        $reportArray = $report->toArray();
        if (empty($this->request->data)) {
            $this->set('error_name', $reportArray['error_name']);
            $this->set('error_message', $reportArray['error_message']);

            return;
        }

        $this->autoRender = false;
        $data = [
            'title' => $this->request->data['summary'],
            'labels' => $this->request->data['labels'] ? explode(',', $this->request->data['labels']) : [],
        ];
        $incidents_query = TableRegistry::getTableLocator()->get('Incidents')->findByReportId($reportId)->all();
        $incident = $incidents_query->first();
        $reportArray['exception_type'] = $incident['exception_type'] ? 'php' : 'js';
        $reportArray['description'] = $this->request->data['description'];

        $data['body']
            = $this->getReportDescriptionText($reportId, $reportArray);
        $data['labels'][] = 'automated-error-report';

        [$issueDetails, $status] = $this->GithubApi->createIssue(
            Configure::read('GithubRepoPath'),
            $data,
            $this->request->getSession()->read('access_token')
        );

        if ($this->handleGithubResponse($status, 1, $reportId, $issueDetails['number'])) {
            // Update report status
            $report->status = $this->getReportStatusFromIssueState($issueDetails['state']);
            $reportsTable->save($report);

            $this->redirect(['controller' => 'reports', 'action' => 'view',
                $reportId,
            ]);
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                $this->getErrors($issueDetails, $status),
                ['params' => ['class' => $flash_class]]
            );
        }
    }

    /**
     * Links error report to existing issue on Github.
     *
     * @param int $reportId The report Id
     * @return void Nothing
     */
    public function link_issue(int $reportId): void
    {
        if (! isset($reportId) || ! $reportId) {
            throw new NotFoundException(__('Invalid reportId'));
        }

        $reportsTable = TableRegistry::getTableLocator()->get('Reports');
        $report = $reportsTable->findById($reportId)->all()->first();

        if (! $report) {
            throw new NotFoundException(__('Invalid report'));
        }

        $ticket_id = intval($this->request->query['ticket_id']);
        if (! $ticket_id) {
            throw new NotFoundException(__('Invalid Ticket ID!!'));
        }
        $reportArray = $report->toArray();

        $incidents_query = TableRegistry::getTableLocator()->get('Incidents')->findByReportId($reportId)->all();
        $incident = $incidents_query->first();
        $reportArray['exception_type'] = $incident['exception_type'] ? 'php' : 'js';

        $commentText = $this->getReportDescriptionText(
            $reportId,
            $reportArray
        );
        [$commentDetails, $status] = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            ['body' => $commentText],
            $ticket_id,
            $this->request->getSession()->read('access_token')
        );
        if ($this->handleGithubResponse($status, 2, $reportId, $ticket_id)) {
            // Update report status
            $report->status = 'forwarded';

            [$issueDetails, $status] = $this->GithubApi->getIssue(
                Configure::read('GithubRepoPath'),
                [],
                $ticket_id,
                $this->request->getSession()->read('access_token')
            );
            if ($this->handleGithubResponse($status, 4, $reportId, $ticket_id)) {
                // If linked Github issue state is available, use it to update Report's status
                $report->status = $this->getReportStatusFromIssueState(
                    $issueDetails['state']
                );
            }

            $reportsTable->save($report);
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                $this->getErrors($commentDetails, $status),
                ['params' => ['class' => $flash_class]]
            );
        }

        $this->redirect(['controller' => 'reports', 'action' => 'view',
            $reportId,
        ]);
    }

    /**
     * Un-links error report to associated issue on Github.
     *
     * @param int $reportId The report Id
     * @return void Nothing
     */
    public function unlink_issue(int $reportId): void
    {
        if (! isset($reportId) || ! $reportId) {
            throw new NotFoundException(__('Invalid reportId'));
        }

        $reportsTable = TableRegistry::getTableLocator()->get('Reports');
        $report = $reportsTable->findById($reportId)->all()->first();

        if (! $report) {
            throw new NotFoundException(__('Invalid report'));
        }

        $reportArray = $report->toArray();
        $ticket_id = $reportArray['sourceforge_bug_id'];

        if (! $ticket_id) {
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

        [$commentDetails, $status] = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            ['body' => $commentText],
            $ticket_id,
            $this->request->getSession()->read('access_token')
        );

        if ($this->handleGithubResponse($status, 3, $reportId)) {
            // Update report status
            $report->status = 'new';
            $reportsTable->save($report);
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                $this->getErrors($commentDetails, $status),
                ['params' => ['class' => $flash_class]]
            );
        }

        $this->redirect(['controller' => 'reports', 'action' => 'view',
            $reportId,
        ]);
    }

    /**
     * Returns pretty error message string.
     *
     * @param object|array $response the response returned by Github api
     * @param int          $status   status returned by Github API
     *
     * @return string error string
     */
    protected function getErrors($response, int $status): string
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
     * Returns the text to be added while creating an issue
     *
     * @param int   $reportId Report Id
     * @param array $report   Report associative array
     * @return string the text
     */
    protected function getReportDescriptionText(int $reportId, array $report): string
    {
        $incident_count = $this->getTotalIncidentCount($reportId);

        // "formatted" text of the comment.
        $formattedText
            = array_key_exists('description', $report) ? $report['description'] . "\n\n"
                : '';
        $formattedText .= "\nParam | Value "
            . "\n -----------|--------------------"
            . "\n Error Type | " . $report['error_name']
            . "\n Error Message |" . $report['error_message']
            . "\n Exception Type |" . $report['exception_type']
            . "\n phpMyAdmin version |" . $report['pma_version']
            . "\n Incident count | " . $incident_count
            . "\n Link | [Report#"
                . $reportId
                . ']('
                . Router::url('/reports/view/' . $reportId, true)
                . ')'
            . "\n\n*This comment is posted automatically by phpMyAdmin's "
            . '[error-reporting-server](https://reports.phpmyadmin.net).*';

        return $formattedText;
    }

    /**
     * Github Response Handler.
     *
     * @param int $response  the status returned by Github API
     * @param int $type      type of response. 1 for create_issue, 2 for link_issue, 3 for unlink_issue,
     *                       1 for create_issue,
     *                       2 for link_issue,
     *                       3 for unlink_issue,
     *                       4 for get_issue
     * @param int $report_id report id
     * @param int $ticket_id ticket id, required for link ticket only
     *
     * @return bool value. True on success. False on any type of failure.
     */
    protected function handleGithubResponse(int $response, int $type, int $report_id, int $ticket_id = 1): bool
    {
        if (! in_array($type, [1, 2, 3, 4])) {
            throw new InvalidArgumentException('Invalid Argument ' . $type . '.');
        }

        $updateReport = true;

        if ($type === 4 && $response === 200) {
            // issue details fetched successfully
            return true;
        }

        if ($response === 201) {
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
                    $msg = 'Something went wrong!';
                    break;
            }

            if ($updateReport) {
                $report = TableRegistry::getTableLocator()->get('Reports')->get($report_id);
                $report->sourceforge_bug_id = $ticket_id;
                TableRegistry::getTableLocator()->get('Reports')->save($report);
            }

            if ($msg !== '') {
                $flash_class = 'alert alert-success';
                $this->Flash->default(
                    $msg,
                    ['params' => ['class' => $flash_class]]
                );
            }

            return true;
        }

        if ($response === 403) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                'Unauthorised access to Github. github'
                    . ' credentials may be out of date. Please check and try again'
                    . ' later.',
                ['params' => ['class' => $flash_class]]
            );

            return false;
        }

        if ($response === 404
            && $type === 2
        ) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                'Bug Issue not found on Github.'
                    . ' Are you sure the issue number is correct? Please check and try again!',
                ['params' => ['class' => $flash_class]]
            );

            return false;
        }

        // unknown response code
        $flash_class = 'alert alert-error';
        $this->Flash->default(
            'Unhandled response code received: ' . $response,
            ['params' => ['class' => $flash_class]]
        );

        return false;
    }

    /**
     * Get Incident counts for a report and
     * all its related reports
     *
     * @param int $reportId The report Id
     *
     * @return int Total Incident count for a report
     */
    protected function getTotalIncidentCount(int $reportId): int
    {
        $incidents_query = TableRegistry::getTableLocator()->get('Incidents')->findByReportId($reportId)->all();
        $incident_count = $incidents_query->count();

        $params_count = [
            'fields' => ['inci_count' => 'inci_count'],
            'conditions' => [
                'related_to = ' . $reportId,
            ],
        ];
        $subquery_params_count = [
            'fields' => ['report_id' => 'report_id'],
        ];
        $subquery_count = TableRegistry::getTableLocator()->get('Incidents')->find(
            'all',
            $subquery_params_count
        );
        $inci_count_related = TableRegistry::getTableLocator()->get('Reports')->find('all', $params_count)->innerJoin(
            ['incidents' => $subquery_count],
            ['incidents.report_id = Reports.related_to']
        )->count();

        return $incident_count + $inci_count_related;
    }

    /**
     * Get corresponding report status from Github issue state
     *
     * @param string $issueState Linked Github issue's state
     *
     * @return string Corresponding status to which the linked report should be updated to
     */
    protected function getReportStatusFromIssueState(string $issueState): string
    {
        // default
        $reportStatus = '';
        switch ($issueState) {
            case 'closed':
                $reportStatus = 'resolved';
                break;

            default:
                $reportStatus = 'forwarded';
                break;
        }

        return $reportStatus;
    }

    /**
     * Synchronize Report Statuses from Github issues
     *
     * To be used as a cron job (using webroot/cron_dispatcher.php).
     *
     * Can not (& should not) be directly accessed via web.
     *
     * @return void Nothing
     */
    public function sync_issue_status(): void
    {
        if (! Configure::read('CronDispatcher')) {
            $flash_class = 'alert alert-error';
            $this->Flash->default(
                'Unauthorised action! This action is not available on Web interface',
                ['params' => ['class' => $flash_class]]
            );

            $this->redirect('/');

            return;
        }

        $this->autoRender = false;
        $reportsTable = TableRegistry::getTableLocator()->get('Reports');

        // Fetch all linked reports
        $reports = $reportsTable->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'NOT' => ['status' => 'resolved'],
                ],
            ]
        );

        foreach ($reports as $report) {
            $report = $report->toArray();

            // fetch the new issue status
            [$issueDetails, $status] = $this->GithubApi->getIssue(
                Configure::read('GithubRepoPath'),
                [],
                $report['sourceforge_bug_id'],
                Configure::read('GithubAccessToken')
            );

            if (! $this->handleGithubResponse($status, 4, $report['id'], $report['sourceforge_bug_id'])) {
                Log::error(
                    'FAILED: Fetching status of Issue #'
                        . $report['sourceforge_bug_id']
                        . ' associated with Report#'
                        . $report['id']
                        . '. Status returned: ' . $status,
                    ['scope' => 'cron_jobs']
                );
                continue;
            }

            // if Github issue state has changed, update the status of report
            if ($report['status'] === $issueDetails['state']) {
                continue;
            }

            $rep = $reportsTable->get($report['id']);
            $rep->status = $this->getReportStatusFromIssueState($issueDetails['state']);

            // Save the report
            $reportsTable->save($rep);

            Log::debug(
                'SUCCESS: Updated status of Report #'
                . $report['id'] . ' from state of its linked Github issue #'
                . $report['sourceforge_bug_id'] . ' to ' . $rep->status,
                ['scope' => 'cron_jobs']
            );
        }
    }
}
