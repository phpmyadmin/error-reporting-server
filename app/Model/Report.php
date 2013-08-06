<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('AppModel', 'Model');
class Report extends AppModel {
	
	public $hasMany = array(
		'Incident' => array(
			'dependant' => true
		)
	);

	public $validate = array(
		'error_message' => array(
			'rule' => 'notEmpty',
			'required'	 => true
		)
	);

	public $findMethods = array(
		'allDataTable' =>	true,
		'arrayList' => true,
	);

	public function getIncidents() {
		return $this->Incident->find('all', array(
			'limit' => 50,
			'conditions' => $this->_relatedIncidentsConditions(),
			'order' => 'Incident.created desc'
		));
	}

	public function getRelatedReports() {
		return $this->find("all", array(
			'conditions' => $this->_relatedReportsConditions(),
		));
	}

	public function getIncidentsWithDescription() {
		return $this->Incident->find('all', array(
      'recursive' => -1,
			'conditions' => array(
        'NOT' => array(
          'Incident.steps' => null
        ),
        $this->_relatedIncidentsConditions(),
			),
			'order' => 'Incident.steps desc'
		));
	}

	public function removeFromRelatedGroup() {
		$this->saveField("related_to", null);
		$report = $this->findByRelatedTo($this->id);
		if ($report) {
			$this->updateAll(
				array("related_to" => $report["Report"]["id"]),
				array("related_to" => $this->id)
			);
		}
	}

	public function addToRelatedGroup($related_to) {
		$report = $this->findById($related_to);
		if ($report && $report["Report"]["related_to"]) {
			$this->saveField("related_to", $report["Report"]["related_to"]);
		} else {
			$this->saveField("related_to", $related_to);
		}
	}

	public function getRelatedByField($fieldName, $limit = 10, $count = false) {
		$queryDetails = array(
			'fields' => array("DISTINCT Incident.$fieldName", "COUNT(*) as count"),
			'conditions' => array(
				$this->_relatedIncidentsConditions(),
        'NOT' => array(
          "Incident.$fieldName" => null
        )
			),
			'limit' => $limit,
			'group' => "$fieldName",
			'order' => 'count DESC'
		);

		$groupedCount = $this->Incident->find('groupedCount', $queryDetails);

		if ($count) {
			$queryDetails['limit'] = null;
			$totalCount = $this->Incident->find('count', $queryDetails);

			return array($groupedCount, $totalCount);
		} else {
			return $groupedCount;
		}
	}

	protected function _relatedIncidentsConditions() {
		$conditions = array(array('report_id' => $this->id));
		return $conditions;
	}

	protected function _findAllDataTable($state, $query, $results = array()) {
		if ($state === 'before') {
			return $query;
		}
		$output = array();
		foreach ($results as $row) {
			$output_row = array();
			foreach ($row['Report'] as $key => $value) {
				$output_row[] = $value;
			}
			$output[] = $output_row;
		}
		return $output;
	}

	protected function _findArrayList($state, $query, $results = array()) {
		if ($state === 'before') {
			return $query;
		}
		$output = array();
		foreach ($results as $row) {
			foreach ($row['Report'] as $key => $value) {
				$output[] = $value;
			}
		}
		return $output;
	}
}
