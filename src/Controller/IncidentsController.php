<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Network\Exception\NotFoundException;
use App\Utility\Sanitize;
/**
 * Incidents controller handling incident creation and rendering
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
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Incidents controller handling incident creation and rendering
 *
 * @package       Server.Controller
 */
class IncidentsController extends AppController {
	public $uses = array("Incident", "Notification");

	public function create() {
		$bugReport = $this->request->input('json_decode', true);
		$result = $this->Incidents->createIncidentFromBugReport($bugReport);
		if (count($result) > 0 
			&& !in_array(false, $result)
		) {
			$response = array(
				"success" => true,
				"message" => "Thank you for your submission",
				"incident_id" => $result,		// Return a list of incident ids.
			);
		} else {
			$response = array(
				"success" => false,
				"message" => "There was a problem with your submission.",
			);
		}
		$this->autoRender = false;
        $this->response->body(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $this->response;
	}

	public function json($id) {
		if (!$id) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$this->Incidents->recursive = -1;
		$incident = $this->Incidents->findById($id)->all()->first();
		if (!$incident) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident['full_report'] =
				json_decode($incident['full_report'], true);
		$incident['stacktrace'] =
				json_decode($incident['stacktrace'], true);

		$this->autoRender = false;
        $this->response->body(json_encode($incident, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $this->response;
	}

	public function view($incidentId) {
		if (!$incidentId) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident = $this->Incidents->findById($incidentId)->all()->first();
		if (!$incident) {
			throw new NotFoundException(__('Invalid Incident'));
		}

		$incident['full_report'] =
				json_decode($incident['full_report'], true);
		$incident['stacktrace'] =
				json_decode($incident['stacktrace'], true);

		$this->set('incident', $incident);
	}
}
