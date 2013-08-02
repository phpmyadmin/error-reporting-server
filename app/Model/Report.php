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

	public function saveFromSubmission($rawReport = array()) {
		$schematizedReport = array(
			'pma_version' => $rawReport['pma_version'],
			'php_version' => $this->getSimplePhpVersion($rawReport['php_version']),
			'steps' => $rawReport['steps'],
			'error_message' => $rawReport['exception']['message'],
			'error_name' => $rawReport['exception']['name'],
			'browser' => $rawReport['browser_name'] . " "
					. $this->getMajorVersion($rawReport['browser_version']),
			'user_os' => $rawReport['user_os'],
			'server_software' => $this->getServer($rawReport['server_software']),
			'full_report' => json_encode($rawReport),
			'stacktrace' => json_encode($rawReport['exception']['stack']),
		);
		return $this->save($schematizedReport);
	}

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

	private function getMajorVersion($fullVersion) {
		preg_match("/^\d+/", $fullVersion, $matches);
		$simpleVersion = $matches[0];
		return $simpleVersion;
	}

	private function getServer($signature) {
		if (preg_match("/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)"
				. "|(lighttpd\/\d+\.\d+)/i",
				$signature, $matches)) {
			return $matches[0];
		} else {
			return "UNKNOWN";
		}
	}

	private function getSimplePhpVersion($phpVersion) {
		preg_match("/^\d+\.\d+/", $phpVersion, $matches);
		$simpleVersion = $matches[0];
		return $simpleVersion;
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
