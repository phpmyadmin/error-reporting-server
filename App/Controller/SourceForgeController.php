<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace app\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Routing\Router;

/**
 * Sourceforge controller handling source forge ticket submission and creation
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Controller
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Sourceforge controller handling source forge ticket submission and creation
 *
 * @package       Server.Controller
 */
class SourceForgeController extends AppController {

	public $helpers = array('Html', 'Form');

	public $components = array('SourceForgeApi');

	public $uses = array('Report');

	public function beforeFilter(Event $event) {
		$this->SourceForgeApi->accessToken =
				Configure::read('SourceForgeCredentials');
		if ($this->action != 'sync_ticket_statuses') {
			parent::beforeFilter($event);
		}
	}

	public function authorize() {
		$requestToken =
			$this->SourceForgeApi->getRequestToken('/' .BASE_DIR . 'source_forge/callback');
		if ($requestToken) {
			$this->Session->write('sourceforge_request_token', serialize($requestToken));
			$this->redirect($this->SourceForgeApi->getRedirectUrl($requestToken));
		}
		$this->autoRender = false;
		return json_encode($requestToken);
	}

	public function callback() {
		$requestToken = unserialize($this->Session->read('sourceforge_request_token'));
		$accessToken = $this->SourceForgeApi->getAccessToken($requestToken);
		$this->autoRender = false;
		return json_encode($accessToken);
	}

	public function create_ticket($reportId) {
		if (!$reportId) {
				throw new NotFoundException(__('Invalid report'));
		}

		$report = $this->Report->findById($reportId);
		if (!$report) {
				throw new NotFoundException(__('Invalid report'));
		}

		if (empty($this->request->data)) {
			$this->set('pma_version', $report['Report']['pma_version']);
			return;
		}

		$data = $this->_getTicketData($reportId);
		$response = $this->SourceForgeApi->createTicket(
				Configure::read('SourceForgeProjectName'), $data);

		if ($this->_handleSFResponse($response, 1, $reportId)) {
			$this->redirect(array('controller' => 'reports', 'action' => 'view',
					$reportId));
		}
	}

	/**
	 * Links error report to existing bug ticket on SF.net
	 *
	 */
	public function link_ticket($reportId) {
		if (!$reportId) {
				throw new NotFoundException(__('Invalid reportId'));
		}

		$report = $this->Report->findById($reportId);
		if (!$report) {
				throw new NotFoundException(__('Invalid Report'));
		}

		$ticket_id = $this->request->query['ticket_id'];
		if(!$ticket_id) {
				throw new NotFoundException(__('Invalid Ticket ID!!'));
		}

		$incident = $this->Report->Incident->findByReportId($reportId);
		$exception_type = ($incident['Incident']['exception_type']) ? ('php') : ('js');

		// "formatted" text of the comment.
		$commentText = "Param | Value "
			. "\n -----------|--------------------"
			. "\n Error Type | " . $report['Report']['error_name']
			. "\n Error Message |" . $report['Report']['error_message']
			. "\n Exception Type |" . $exception_type
			. "\n Link | [Report#"
				. $reportId
				."]("
				. Router::url('/reports/view/'.$reportId,true)
				.")"
			. "\n\n*This comment is posted automatically by phpMyAdmin's "
			. "[error-reporting-server](http://reports.phpmyadmin.net).*";

		$response = $this->SourceForgeApi->createComment(
			Configure::read('SourceForgeProjectName'),
			$ticket_id,
			array('text' => $commentText)
		);

		$this->_handleSFResponse($response, 2, $reportId, $ticket_id);
		$this->redirect(array('controller' => 'reports', 'action' => 'view',
						$reportId));
	}

	/**
	 * Un-links error report to associated bug ticket on SF.net
	 *
	 */
	public function unlink_ticket($reportId) {
		if (!$reportId) {
				throw new NotFoundException(__('Invalid reportId'));
		}

		$report = $this->Report->findById($reportId);
		if (!$report) {
				throw new NotFoundException(__('Invalid Report'));
		}

		$ticket_id = $report['Report']['sourceforge_bug_id'];
		if(!$ticket_id) {
				throw new NotFoundException(__('Invalid Ticket ID!!'));
		}

		// "formatted" text of the comment.
		$commentText = "This Bug Ticket is no longer associated with [Report#"
			. $reportId
			. "]("
			. Router::url('/reports/view/'.$reportId,true)
			. ")"
			. "\n\n*This comment is posted automatically by phpMyAdmin's "
			. "[error-reporting-server](http://reports.phpmyadmin.net).*";

		$response = $this->SourceForgeApi->createComment(
			Configure::read('SourceForgeProjectName'),
			$ticket_id,
			array('text' => $commentText)
		);

		$this->_handleSFResponse($response, 3, $reportId);
		$this->redirect(array('controller' => 'reports', 'action' => 'view',
						$reportId));
	}

	protected function _getTicketData($reportId) {
		$data = array(
			'ticket_form.summary' => $this->request->data['Ticket']['summary'],
			'ticket_form.description' => $this->_augmentDescription(
					$this->request->data['Ticket']['description'], $reportId),
			'ticket_form.status' => 'open',
			'ticket_form.labels' => $this->request->data['Ticket']['labels'],
			'ticket_form._milestone' => $this->request->data['Ticket']['milestone'],
		);
		if (!empty($data['ticket_form.labels'])) {
			$data['ticket_form.labels'] .= ',';
		}
		$data['ticket_form.labels'] .= 'automated-error-report';
		return $data;
	}

	protected function _getErrors($body) {
		$errorString = "There were some problems with the ticket submission."
				." Returned status is (" . $body["status"] . ")";

		$errors = $body["errors"];
		if ($body["status"] === "Validation Error") {
			$errorString .= '<ul>';
			foreach ($errors['ticket_form'] as $field => $message) {
				$errorString .= "<li>";
				$errorString .= "$field: $message";
				$errorString .= "</li>";
			}
			$errorString .= '</ul>';
		} else {
			$errorString .= "<br/> Here is the dump for the errors field provided by"
					. " sourceforge: <br/>"
					. "<pre>"
					. print_r($errors, true)
					. "</pre>";
		}

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
		$this->Report->read(null, $reportId);
		return "$description\n\n\nThis report is related to user submitted report "
				. "[#" . $this->Report->id . "](" . $this->Report->getUrl()
				. ") on the phpmyadmin error reporting server.";
	}

/**
 * Sourceforge Response Handler
 * @param Object $response the response returned by sourceforge API
 * @param Integer $type type of response.
 *			1 for create_ticket,
 *			2 for link_ticket,
 *			3 for unlink_ticket,
 * @param Integer $report_id report id.
 * @param Integer $ticket_id ticket id, required for link tivket only.
 *
 * @return Boolean value. True on success. False on any type of failure.
 */
	protected function _handleSFResponse($response, $type, $report_id,  $ticket_id = 1)
	{
		if (!in_array($type, array(1,2,3))) {
			throw new InvalidArgumentException('Invalid Argument "$type".');
		}

		if ($response->code[0] === "3") {
			// success
			switch ($type) {
				case 1:
					$msg = 'Source forge ticket has been created for this report.';
					preg_match("<rest/p/.*/bugs/(\d+)/>",
					$response->headers['Location'], $matches);
					$ticket_id = $matches[1];
					break;
				case 2:
					$msg = 'Source forge ticket has been linked with this report.';
					break;
				case 3:
					$msg = 'Source forge ticket has been unlinked with this report.';
					$ticket_id = null;
					break;
				default:
					$msg = 'Something went wrong!!';
					break;
			}

			$this->Report->read(null, $report_id);
			$this->Report->save(array('sourceforge_bug_id' => $ticket_id));
			$this->Session->setFlash($msg, "default", array("class" => "alert alert-success"));
			return true;
		} else if ($response->code === "403") {
			$this->Session->setFlash(
					"Unauthorised access to SourceForge ticketing system. SourceForge"
					. " credentials may be out of date. Please check and try again"
					. " later.", "default", array("class" => "alert alert-error"));
			return false;
		} else if ($response->code === "404"
			&& $type == 2
		) {
			$this->Session->setFlash(
					"Bug Ticket not found on SourceForge."
					. " Are you sure the ticket number is correct?!! Please check and try again",
					"default", array("class" => "alert alert-error"));
			return false;
		} else {
			//fail
			$response->body = json_decode($response->body, true);
			Log::write('sourceforge', 'Submission for sourceforge ticket may have failed.',
					'sourceforge');
			Log::write('sourceforge', 'Response dump:', 'sourceforge');
			Log::write('sourceforge', print_r($response["raw"], true), 'sourceforge');
			$this->Session->setFlash($this->_getErrors( $response->body), "default",
					array("class" => "alert alert-error"));
			return false;
		}
	}

	/**
	 * Synchronize Report Statuses from SF Bug Tickets
	 * To be used as a cron job.
	 * Can not (& should not) be directly accessed via Web.
	 */
	public function sync_ticket_statuses(){
		if (!defined('CRON_DISPATCHER')) {
			$this->redirect('/');
			exit();
		}

		$reports = $this->Report->find(
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
					$report['Report']['sourceforge_bug_id']
				);
				$i++;
			} while($new_status == false && $i <= 3);

			// if fails all three times, then simply write failure
			// into cron_jobs log and move on.
			if (!$new_status) {
				Log::write(
					'cron_jobs',
					'FAILED: Fetching status of BugTicket#'
						. ($report['Report']['sourceforge_bug_id'])
						. ' associated with Report#'
						. ($report['Report']['id']),
					'cron_jobs'
				);
				continue;
			}

			if ($report['Report']['status'] != $new_status) {
				$this->Report->read(null, $report['Report']['id']);
				$this->Report->save(array('status' => $new_status));
			}
		}
		$this->autoRender = false;
	}
}
