<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('AppModel', 'Model');
class Report extends AppModel {

	public $validate = array(
		'error_message' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'pma_version' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'php_version' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'full_report' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'stacktrace' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'browser' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
	);

	public $findMethods = array(
		'allDataTable' =>	true,
		'arrayList' => true,
		'groupedCount'=> true);

	public function getRelatedReports() {
		return $this->find('all', array(
			'limit' => 50,
			'conditions' => $this->_relatedReportsConditions(),
			'order' => 'created desc'
		));
	}

	public function getRelatedReportsWithDescription() {
		return $this->find('all', array(
			'conditions' => array(
				$this->_relatedReportsConditions(),
				'steps IS NOT NULL'
			),
			'order' => 'steps desc'
		));
	}

	public function getRelatedByField($fieldName, $limit = 10, $count = false) {
		$queryDetails = array(
			'fields' => array("DISTINCT $fieldName", "COUNT(id) as count"),
			'conditions' => array(
				$this->_relatedReportsConditions(),
				"$fieldName IS NOT NULL",
			),
			'limit' => $limit,
			'group' => "$fieldName",
			'order' => 'count DESC'
		);

		$groupedCount = $this->find('groupedCount', $queryDetails);

		if ($count) {
			$queryDetails['limit'] = null;
			$totalCount = $this->find('count', $queryDetails);

			return array($groupedCount, $totalCount);
		} else {
			return $groupedCount;
		}
	}

	protected function _relatedReportsConditions() {
		$conditions = array(array('related_report_id' => $this->id));

		if ($this->data["Report"]["related_report_id"]) {
			$conditions[] = array('related_report_id' =>
					$this->data["Report"]["related_report_id"]);
			$conditions[] = array('id' =>
					$this->data["Report"]["related_report_id"]);
		}
		return array('OR' => $conditions);
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

	protected function _findGroupedCount($state, $query, $results = array()) {
		if ($state === 'before') {
			return $query;
		}
		$output = array();
		foreach ($results as $row) {
			foreach ($row['Report'] as $key => $value) {
				$output[$value] = $row[0]['count'];
			}
		}
		return $output;
	}
}
