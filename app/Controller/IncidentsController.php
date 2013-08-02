<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('Sanitize', 'Utility');
App::uses('AppController', 'Controller');

class IncidentsController extends AppController {

	public function create() {
		$bugReport = $this->request->input('json_decode', true);
		if ($this->Incident->createIncidentFromBugReport($bugReport)) {
			$response = array(
				"success" => true,
				"message" => "Thank you for your submission",
				"report_id" => $this->Incident->id,
			);
		} else {
			$response = array(
				"success" => false,
				"message" => "There was a problem with your submission."
			);
		}
		$this->autoRender = false;
		return json_encode($response);
	}

}
