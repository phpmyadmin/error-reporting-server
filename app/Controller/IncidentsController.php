<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Incidents controller handling incident creation and rendering
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
App::uses('Sanitize', 'Utility');
App::uses('AppController', 'Controller');

/**
 * Incidents controller handling incident creation and rendering
 *
 * @package       Server.Controller
 */
class IncidentsController extends AppController {

	public function create() {
		$bugReport = $this->request->input('json_decode', true);
		if ($this->Incident->createIncidentFromBugReport($bugReport)) {
			$response = array(
				"success" => true,
				"message" => "Thank you for your submission",
				"incident_id" => $this->Incident->id,
			);
		} else {
			$response = array(
				"success" => false,
				"message" => "There was a problem with your submission.",
			);
		}
		$this->autoRender = false;
		return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function json($id) {
		if (!$id) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$this->Incident->recursive = -1;
		$incident = $this->Incident->findById($id);
		if (!$incident) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident['Incident']['full_report'] =
				json_decode($incident['Incident']['full_report'], true);
		$incident['Incident']['stacktrace'] =
				json_decode($incident['Incident']['stacktrace'], true);

		$this->autoRender = false;
		return json_encode($incident, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function view($incidentId) {
		if (!$incidentId) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident = $this->Incident->findById($incidentId);
		if (!$incident) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident['Incident']['full_report'] =
				json_decode($incident['Incident']['full_report'], true);
		$incident['Incident']['stacktrace'] =
				json_decode($incident['Incident']['stacktrace'], true);

		$this->set('incident', $incident);
	}
}
