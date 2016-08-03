<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Network\Exception\NotFoundException;
/**
 * Sourceforge controller handling source forge ticket submission and creation
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @package       Server.Controller
 * @link          https://www.phpmyadmin.net/
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Github controller handling github issue submission and creation
 *
 * @package       Server.Controller
 */
class GithubController extends AppController {

	public $helpers = array('Html', 'Form');

	public $components = array('GithubApi');

	public $uses = array('Report');

	public function beforeFilter(Event $event) {
		parent::beforeFilter($event);
		$this->GithubApi->githubConfig = Configure::read('GithubConfig');
		$this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
	}

    /**
     * create Github Issue
     *
     * @param Integer $reportId
     * @throws \NotFoundException
     * @throws NotFoundException
     */
	public function create_issue($reportId) {
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
            'body'  => $this->_augmentDescription(
					$this->request->data['description'], $reportId),
            'labels' => $this->request->data['labels']?split(",", $this->request->data['labels']):Array()
		);
        $data['labels'][] = 'automated-error-report';
        list($issueDetails, $status) = $this->GithubApi->createIssue(
            Configure::read('GithubRepoPath'),
            $data,
            $this->request->session()->read("access_token")
        );

		if ($this->_handleGithubResponse($status, 1, $reportId, $issueDetails['number'])) {
			$this->redirect(array('controller' => 'reports', 'action' => 'view',
					$reportId));
        } else {
            $flash_class = "alert alert-error";
            $this->Flash->default(_getErrors($issueDetails, $status),
					array("params" => array("class" => $flash_class)));
        }
	}

	/**
	 * Links error report to existing issue on Github
	 *
	 */
	public function link_issue($reportId) {
		if (!$reportId) {
				throw new NotFoundException(__('Invalid reportId'));
		}

		$report = TableRegistry::get('Reports')->findById($reportId)->all()->first();
		if (!$report) {
				throw new NotFoundException(__('Invalid Report'));
		}

		$ticket_id = intval($this->request->query['ticket_id']);
		if(!$ticket_id) {
				throw new NotFoundException(__('Invalid Ticket ID!!'));
		}

		$incident = TableRegistry::get('Incidents')->findByReportId($reportId)->all()->first();
		$exception_type = ($incident['exception_type']) ? ('php') : ('js');

		// "formatted" text of the comment.
		$commentText = "Param | Value "
			. "\n -----------|--------------------"
			. "\n Error Type | " . $report['error_name']
			. "\n Error Message |" . $report['error_message']
			. "\n Exception Type |" . $exception_type
			. "\n Link | [Report#"
				. $reportId
				."]("
				. Router::url('/reports/view/'.$reportId,true)
				.")"
			. "\n\n*This comment is posted automatically by phpMyAdmin's "
			. "[error-reporting-server](https://reports.phpmyadmin.net).*";

        list($commentDetails, $status) = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            array('body' => $commentText),
            $ticket_id,
            $this->request->session()->read("access_token")
        );
		if (!$this->_handleGithubResponse($status, 2, $reportId, $ticket_id))
        {
			$flash_class = "alert alert-error";
			$this->Flash->default(_getErrors($commentDetails, $status),
					array("params" => array("class" => $flash_class)));
        }
		$this->redirect(array('controller' => 'reports', 'action' => 'view',
						$reportId));
	}

	/**
	 * Un-links error report to associated issue on Github
	 *
	 */
	public function unlink_issue($reportId) {
		if (!$reportId) {
				throw new NotFoundException(__('Invalid reportId'));
		}

		$report = TableRegistry::get('Reports')->findById($reportId)->all()->first();
		if (!$report) {
				throw new NotFoundException(__('Invalid Report'));
		}

		$ticket_id = $report['sourceforge_bug_id'];
		if(!$ticket_id) {
				throw new NotFoundException(__('Invalid Ticket ID!!'));
		}

		// "formatted" text of the comment.
		$commentText = "This Issue is no longer associated with [Report#"
			. $reportId
			. "]("
			. Router::url('/reports/view/'.$reportId,true)
			. ")"
			. "\n\n*This comment is posted automatically by phpMyAdmin's "
			. "[error-reporting-server](https://reports.phpmyadmin.net).*";

        list($commentDetails, $status) = $this->GithubApi->createComment(
            Configure::read('GithubRepoPath'),
            array('body' => $commentText),
            $ticket_id,
            $this->request->session()->read("access_token")
        );

		if (!$this->_handleGithubResponse($status, 3, $reportId))
        {
			$flash_class = "alert alert-error";
			$this->Flash->default(_getErrors($commentDetails, $status),
					array("params" => array("class" => $flash_class)));
        }
        $this->redirect(array('controller' => 'reports', 'action' => 'view',
						$reportId));
	}

    /**
     * Returns pretty error message string
     *
     * @param Object $response the response returned by Github api
     * @param Integer $status status returned by Github API
     *
     * @return error string
     */
	protected function _getErrors($response, $status) {
		$errorString = "There were some problems with the issue submission."
				." Returned status is (" . $status . ")";
		$errorString .= "<br/> Here is the dump for the errors field provided by"
			. " github: <br/>"
			. "<pre>"
			. print_r($response, true)
			. "</pre>";
		return $errorString;
	}

/**
 * Returns the description with the added string to link to the report
 * @param String $description the original description submitted by the dev
 * @param String $reportId the report id relating to the ticket
 *
 * @return String augmented description
 */
	protected function _augmentDescription($description, $reportId) {
        $report = TableRegistry::get('Reports');
		$report->id = $reportId;
		return "$description\n\n\nThis report is related to user submitted report "
				. "[#" . $report->id . "](" . $report->getUrl()
				. ") on the phpmyadmin error reporting server.";
	}

/**
 * Github Response Handler
 * @param Integer $response the status returned by Github API
 * @param Integer $type type of response.
 *			1 for create_issue,
 *			2 for link_issue,
 *			3 for unlink_issue,
 * @param Integer $report_id report id.
 * @param Integer $ticket_id ticket id, required for link tivket only.
 *
 * @return Boolean value. True on success. False on any type of failure.
 */
	protected function _handleGithubResponse($response, $type, $report_id,  $ticket_id = 1)
	{
		if (!in_array($type, array(1,2,3))) {
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
            $flash_class = "alert alert-success";
            $this->Flash->default($msg,
				array("params" => array("class" => $flash_class)));
			return true;
		} else if ($response === 403) {
			$flash_class = "alert alert-error";
			$this->Flash->default(
					"Unauthorised access to Github. github"
					. " credentials may be out of date. Please check and try again"
					. " later.",
					array("params" => array("class" => $flash_class)));
			return false;
		} else if ($response === 404
			&& $type == 2
		) {
			$flash_class = "alert alert-error";
			$this->Flash->default(
					"Bug Issue not found on Github."
					. " Are you sure the issue number is correct?!! Please check and try again",
					 array("params" => array("class" => $flash_class)));
			return false;
		} else {
			//fail
			$flash_class = "alert alert-error";
			$this->Flash->default(json_encode($response),
					array("params" => array("class" => $flash_class)));
			return false;
		}
	}

	/**
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
