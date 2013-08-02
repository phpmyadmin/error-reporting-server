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

	public function view($id) {
		if (!$id) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->findById($id);
		if (!$report || $this->RequestHandler->accepts('json')) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report['Report']['full_report'] =
				Sanitize::clean(json_decode($report['Report']['full_report'], true));

		$this->set('report', $report);
		$this->set('project_name', Configure::read('SourceForgeProjectName'));

		$this->Report->read(null, $id);
		$this->set('related_reports', $this->Report->getRelatedReports());
		$this->set('reportsWithDescription',
				$this->Report->getRelatedReportsWithDescription());

		$this->_setSimilarFields($id);
	}

	public function json($id) {
		if (!$id) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report = $this->Report->findById($id);
		if (!$report || $this->RequestHandler->accepts('json')) {
			throw new NotFoundException(__('Invalid Report'));
		}

		$report['Report']['full_report'] =
				json_decode($report['Report']['full_report'], true);
		$report['Report']['stacktrace'] =
				json_decode($report['Report']['stacktrace'], true);

		$this->autoRender = false;
		return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function submit() {
		$report = $this->request->input('json_decode', true);
		$this->Report->create(array('status' => 'new'));
		$this->Report->saveFromSubmission($report);
		$response = array(
			"success" => true,
			"message" => "Thank you for your submission",
			"report_id" => $this->Report->id,
		);
		$this->autoRender = false;
		return json_encode($response);
	}

	public function data_tables() {
		$aColumns = array('id', 'error_name', 'error_message', 'pma_version',
					'status');
		$search_conditions = $this->_getSearchConditions($aColumns);
		$order_conditions = $this->_getOrder($aColumns);

		$params = array(
			'fields' => $aColumns,
			'conditions' => $search_conditions,
			'order' => $order_conditions,
		);

		$paged_params = $params;
		$paged_params['limit'] = intval($this->request->query('iDisplayLength'));
		$paged_params['offset'] = intval($this->request->query('iDisplayStart'));

		$rows = $this->Report->find('allDataTable', $paged_params);
		$rows = Sanitize::clean($rows);
		$total_filtered = $this->Report->find('count', $params);

		$response = array(
			'iTotalRecords' => $this->Report->find('count'),
			'iTotalDisplayRecords' => $total_filtered,
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

		foreach($fields as $field) {
			list($entriesWithCount, $totalEntries) =
					$this->Report->getRelatedByField($field, 25, true);
			$this->set("${field}_related_entries", $entriesWithCount);
			$this->set("${field}_distinct_count", $totalEntries);
		}
	}

	protected function _getSearchConditions($aColumns) {
		$searchConditions = array('OR' => array());
		if ( $this->request->query('sSearch') != "" )
		{
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				if ($this->request->query('bSearchable_' . $i) == "true") {
					$searchConditions['OR'][] = array($aColumns[$i] . " LIKE" => "%" .
							$this->request->query('sSearch') . "%");
				}
			}
		}

		/* Individual column filtering */
		for ( $i=0 ; $i<count($aColumns) ; $i++ )
		{
			if ($this->request->query('sSearch_' . $i) != '')
			{
				$searchConditions[] = array($aColumns[$i] . " LIKE" =>
						"%" . $this->request->query('sSearch_' . $i) . "%");
			}
		}
		return $searchConditions;
	}

	protected function _getOrder($aColumns) {
		if ( $this->request->query('iSortCol_0') != null )
		{
			$order = array();
			for ( $i=0 ; $i<intval($this->request->query('iSortingCols')) ; $i++ )
			{
				if ( $this->request->query('bSortable_'
						. intval($this->request->query('iSortCol_' . $i))) == "true" )
				{
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
