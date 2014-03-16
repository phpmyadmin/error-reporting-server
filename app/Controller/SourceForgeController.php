<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
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

	public function beforeFilter() {
		$this->SourceForgeApi->accessToken =
				Configure::read('SourceForgeCredentials');
		parent::beforeFilter();
	}

	public function authorize() {
		$requestToken =
				$this->SourceForgeApi->getRequestToken('/source_forge/callback');
		if ($requestToken) {
			$this->Session->write('sourceforge_request_token', $requestToken);
			$this->redirect($this->SourceForgeApi->getRedirectUrl($requestToken));
		}
		$this->autoRender = false;
		return json_encode($requestToken);
	}

	public function callback() {
		$requestToken = $this->Session->read('sourceforge_request_token');
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
			return;
		}

		$data = $this->_getTicketData($reportId);
		$response = $this->SourceForgeApi->createTicket(
				Configure::read('SourceForgeProjectName'), $data);
		if ($response->code[0] === "3") {
			// success
			preg_match("<rest/p/.*/bugs/(\d+)/>",
					$response->headers['Location'], $matches);
			$this->Report->read(null, $reportId);
			$this->Report->save(array('sourceforge_bug_id' => $matches[1]));

			$this->Session->setFlash('Source forge ticket has been created for this'
					. ' report', "default", array("class" => "alert alert-success"));
			$this->redirect(array('controller' => 'reports', 'action' => 'view',
					$reportId));
		} else if ($response->code === "403") {
			$this->Session->setFlash(
					"Unauthorised access to SourceForge ticketing system. SourceForge"
					. " credentials may be out of date. Please check and try again"
					. " later.", "default", array("class" => "alert alert-error"));
		} else {
			//fail
			$response->body = json_decode($response->body, true);
			CakeLog::write('sourceforge', 'Submission for sourceforge ticket may have failed.',
					'sourceforge');
			CakeLog::write('sourceforge', 'Response dump:', 'sourceforge');
			CakeLog::write('sourceforge', print_r($response["raw"], true), 'sourceforge');
			$this->Session->setFlash($this->_getErrors( $response->body), "default",
					array("class" => "alert alert-error"));
		}
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
}
