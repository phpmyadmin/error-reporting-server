<?php
class SourceForgeController extends AppController {
	public $helpers = array('Html', 'Form');
	public $components = array('SourceForgeApi');
	public $uses = array('Report');

	public function beforeFilter() {
		$this->SourceForgeApi->access_token =
				Configure::read('SourceForgeCredentials');
		parent::beforeFilter();
	}

	public function authorize() {
		$requestToken =
				$this->SourceForgeApi->getRequestToken('/source_forge/callback');
		if($requestToken) {
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

	public function create_ticket($report_id) {

		if (!$report_id) {
				throw new NotFoundException(__('Invalid report'));
		}

		$report = $this->Report->findById($report_id);
		if (!$report) {
				throw new NotFoundException(__('Invalid report'));
		}

		if (empty($this->request->data)) {
			return;
		}

		$data = $this->get_ticket_data($report_id);
		$response = $this->SourceForgeApi->createTicket(
				Configure::read('SourceForgeProjectName'), $data);
		if($response->code[0] === "3") {
			// success
			preg_match("<rest/p/.*/bugs/(\d+)/>",
					$response->headers['Location'], $matches);
			$this->Report->read(null, $report_id);
			$this->Report->save(array('sourceforge_bug_id' => $matches[1]));

			$this->Session->setFlash('Source forge ticket has been created for this'
					. 'report', "default", array("class" => "alert alert-success"));
			$this->redirect(array('controller' => 'reports', 'action' => 'view',
					$report_id));
		} else {
			//fail
			$response->body = json_decode($response->body, true);
			$this->Session->setFlash($this->get_validation_errors(
					$response->body['errors']), "default",
					array("class" => "alert alert-error"));
		}
	}

	private function get_ticket_data($report_id) {
		$data = array(
			'ticket_form.summary' => $this->request->data['Ticket']['summary'],
			'ticket_form.description' => $this->request->data['Ticket']['description'],
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

	private function get_validation_errors($errors) {
		$error_string = "There were some problems with the ticket submission:";
		$error_string .= '<ul>';

		foreach($errors['ticket_form'] as $field => $message) {
			$error_string .= "<li>";
			$error_string .= "$field: $message";
			$error_string .= "</li>";
		}

		$error_string .= '</ul>';
		return $error_string;
	}

}
