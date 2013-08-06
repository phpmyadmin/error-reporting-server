<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('Sanitize', 'Utility');
App::uses('AppController', 'Controller');

class ReportsController extends AppController {

	public $components = array('RequestHandler');

	public $helpers = array('Html', 'Form', 'Reports');

	public function index() {
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

	public function test($id) {
		$this->Report->recursive = -1;
		$report = $this->Report->read(null, $id);
		$this->autoRender = false;
		return json_encode($this->Report->getIncidentsWithDescription());
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
		$this->set('related_reports', $this->Report->getRelatedReports());

		$this->_setSimilarFields($reportId);
	}

	public function data_tables() {
		$aColumns = array('id', 'error_name', 'error_message', 'pma_version',
					'status');
		$searchConditions = $this->_getSearchConditions($aColumns);
		$orderConditions = $this->_getOrder($aColumns);

		$params = array(
			'fields' => $aColumns,
			'conditions' => $searchConditions,
			'order' => $orderConditions,
		);

		$pagedParams = $params;
		$pagedParams['limit'] = intval($this->request->query('iDisplayLength'));
		$pagedParams['offset'] = intval($this->request->query('iDisplayStart'));

		$rows = $this->Report->find('allDataTable', $pagedParams);
		$rows = Sanitize::clean($rows);
		$totalFiltered = $this->Report->find('count', $params);

		$response = array(
			'iTotalRecords' => $this->Report->find('count'),
			'iTotalDisplayRecords' => $totalFiltered,
			'sEcho' => intval($this->request->query('sEcho')),
			'aaData' => $rows
		);
		$this->autoRender = false;
		return json_encode($response);
	}

## PRIVATE HELPERS
	protected function _setSimilarFields($id) {
		$fields = array('browser', 'pma_version', 'php_version', 'server_software');

		$this->Report->read(null, $id);

		foreach ($fields as $field) {
			list($entriesWithCount, $totalEntries) =
					$this->Report->getRelatedByField($field, 25, true);
			$this->set("${field}_related_entries", $entriesWithCount);
			$this->set("${field}_distinct_count", $totalEntries);
		}
	}

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
						"%" . $this->request->query('sSearch_' . $i) . "%");
			}
		}
		return $searchConditions;
	}

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
