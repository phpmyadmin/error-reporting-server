<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Reports controller handling reports creation and rendering
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
 * Reports controller handling reports modification and rendering
 *
 * @package       Server.Controller
 */
class ReportsController extends AppController {

	public $components = array('RequestHandler');

	public $helpers = array('Html', 'Form', 'Reports', 'Incidents');

	public $uses = array('Incident', 'Report', 'Notification', 'Developer');

	public function index() {
		$this->Report->recursive = -1;
		$this->set('distinct_statuses',
			$this->Report->find('arrayList', array(
				'fields' => array('DISTINCT Report.status'),
			))
		);
		$this->set('distinct_versions',
			$this->Report->find('arrayList', array(
				'fields' => array('DISTINCT Report.pma_version'),
			))
		);
		$this->set('distinct_error_names',
			$this->Report->find('arrayList', array(
				'fields' => array('DISTINCT Report.error_name'),
				'conditions' => array('error_name !=' => ''),
			))
		);
	}

	public function view($reportId) {
		if (!$reportId) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->findById($reportId);
		if (!$report) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$this->set('report', $report);
		$this->set('project_name', Configure::read('SourceForgeProjectName'));

		$this->Report->read(null, $reportId);
		$this->set('incidents', $this->Report->getIncidents());
		$this->set('incidents_with_description',
				$this->Report->getIncidentsWithDescription());
		$this->set('incidents_with_stacktrace',
				$this->Report->getIncidentsWithDifferentStacktrace());
		$this->set('related_reports', $this->Report->getRelatedReports());
		$this->set('status', $this->Report->status);

		$this->_setSimilarFields($reportId);

		// if there is an unread notification for this report, then mark it as read
		$current_developer = $this->Developer->
					findById($this->Session->read('Developer.id'));
		$current_developer = Sanitize::clean($current_developer);
		if ($current_developer) {
			$this->Notification->deleteAll(
				array('developer_id' => $current_developer['Developer']['id'],
					'report_id' => $reportId
				),
				false
			);
		}
	}

	public function data_tables() {
		$aColumns = array('id', 'error_name', 'error_message', 'pma_version',
					'status','exception_type');
		$searchConditions = $this->_getSearchConditions($aColumns);
		$orderConditions = $this->_getOrder($aColumns);

		$params = array(
			'fields' => $aColumns,
			'conditions' => array(
					$searchConditions,
					'related_to' => NULL
				),
			'order' => $orderConditions,
		);

		$pagedParams = $params;
		$pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
		$pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

		$rows = $this->Report->find('allDataTable', $pagedParams);
		$rows = Sanitize::clean($rows);
		$totalFiltered = $this->Report->find('count', $params);

		// change exception_type from boolean values to strings
		$dispRows = array();
		foreach($rows as $row) {
			$row[5] = (intval($row[5]))?('php'):('js');
			array_push($dispRows, $row);
		}
		$response = array(
			'iTotalRecords' => $this->Report->find('count'),
			'iTotalDisplayRecords' => $totalFiltered,
			'sEcho' => intval($this->request->query('sEcho')),
			'aaData' => $dispRows
		);
		$this->autoRender = false;
		return json_encode($response);
	}

	public function mark_related_to($reportId) {
		$relatedTo = $this->request->query("related_to");
		if (!$reportId || !$relatedTo) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->read(null, $reportId);
		if (!$report) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$this->Report->addToRelatedGroup($relatedTo);
		$this->Session->setFlash("This report has been marked the same as #"
				. $relatedTo, "default", array("class" => "alert alert-success"));
		$this->redirect("/reports/view/$reportId");
	}

	public function unmark_related_to($reportId) {
		if (!$reportId) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->read(null, $reportId);
		if (!$report) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$this->Report->removeFromRelatedGroup();
		$this->Session->setFlash("This report has been marked as different."
				, "default", array("class" => "alert alert-success"));
		$this->redirect("/reports/view/$reportId");
	}

	public function change_state($reportId) {
		if (!$reportId) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->read(null, $reportId);
		if (!$report) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$state = $this->request->data['state'];
		$newState = $this->Report->status[$state];
		if (!$newState) {
			throw new NotFoundException(__('Invalid State'));
		}

		$this->Report->saveField("status", $state);
		$this->Session->setFlash("The state has been successfully changed."
				, "default", array("class" => "alert alert-success"));
		$this->redirect("/reports/view/$reportId");
	}

## HELPERS
	protected function _setSimilarFields($id) {
		$this->Report->read(null, $id);

		$this->set('columns', $this->Incident->summarizableFields);
		$relatedEntries = array();

		foreach ($this->Incident->summarizableFields as $field) {
			list($entriesWithCount, $totalEntries) =
					$this->Report->getRelatedByField($field, 25, true);
			$relatedEntries[$field] = $entriesWithCount;
			$this->set("${field}_distinct_count", $totalEntries);
		}
		$this->set("related_entries", $relatedEntries);
	}

	/**
	 * @param string[] $aColumns
	 */
	protected function _getSearchConditions($aColumns) {
		$searchConditions = array('OR' => array());
		if ( $this->request->query('sSearch') != "" ) {
			for ( $i = 0; $i < count($aColumns); $i++ ) {
				if ($this->request->query('bSearchable_' . $i) == "true") {
					$searchConditions['OR'][] = array($aColumns[$i] . " LIKE" =>
							"%" . $this->request->query('sSearch') . "%");
				}
			}
		}

		/* Individual column filtering */
		for ( $i = 0; $i < count($aColumns); $i++ ) {
			if ($this->request->query('sSearch_' . $i) != '') {
				$searchConditions[] = array($aColumns[$i] . " LIKE" =>
						$this->request->query('sSearch_' . $i));
			}
		}
		return $searchConditions;
	}

	/**
	 * @param string[] $aColumns
	 */
	protected function _getOrder($aColumns) {
		if ( $this->request->query('iSortCol_0') != null ) {
			$order = array();
			for ( $i = 0; $i < intval($this->request->query('iSortingCols')); $i++ ) {
				if ( $this->request->query('bSortable_'
						. intval($this->request->query('iSortCol_' . $i))) == "true" ) {
					$order[] = array(
						$aColumns[intval($this->request->query('iSortCol_' . $i))] =>
							$this->request->query('sSortDir_' . $i)
					);
				}
			}
			return $order;
		} else {
			return null;
		}
	}
}
