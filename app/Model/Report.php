<?php
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

	public function save_from_submission($raw_report = array()) {
		$schematized_report = array(
			'pma_version' => $raw_report['pma_version'],
			'php_version' => $this->get_simple_php_version($raw_report['php_version']),
			'steps' => $raw_report['steps'],
			'error_message' => $raw_report['exception']['message'],
			'error_name' => $raw_report['exception']['name'],
			'browser' => $raw_report['browser_name']. " "
					. $this->get_major_version($raw_report['browser_version']),
			'user_os' => $raw_report['user_os'],
			'server_software' => $this->get_server($raw_report['server_software']),
			'full_report' => json_encode($raw_report),
			'stacktrace' => json_encode($raw_report['exception']['stack']),
		);
		return $this->save($schematized_report);
	}

	public function get_related_reports() {
		return $this->find('all', array(
			'limit' => 50,
			'conditions' => $this->related_reports_conditions(),
			'order' => 'created desc'
		));
	}

	public function get_related_reports_with_description() {
		return $this->find('all', array(
			'conditions' => array(
				$this->related_reports_conditions(),
				'steps IS NOT NULL'
			),
			'order' => 'steps desc'
		));
	}

	public function get_related_by_field($field_name, $limit = 10, $count = false) {
		$query_details = array(
			'fields' => array("DISTINCT $field_name", "COUNT(id) as count"),
			'conditions' => array(
				$this->related_reports_conditions(),
				"$field_name IS NOT NULL",
			),
			'limit' => $limit,
			'group' => "$field_name",
			'order' => 'count DESC'
		);

		$grouped_count = $this->find('groupedCount', $query_details);

		if ($count) {
			$query_details['limit'] = null;
			$total_count = $this->find('count', $query_details);

			return array($grouped_count, $total_count);
		} else {
			return $grouped_count;
		}
	}

	private function get_major_version($full_version) {
		preg_match("/^\d+/", $full_version, $matches);
		$simple_version = $matches[0];
		return $simple_version;
	}

	private function get_server($signature) {
		if (preg_match("/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)"
				. "|(lighttpd\/\d+\.\d+)/i",
				$signature, $matches)) {
			return $matches[0];
		} else {
			return "UNKNOWN";
		}
	}

	private function get_simple_php_version($php_version) {
		preg_match("/^\d+\.\d+/", $php_version, $matches);
		$simple_version = $matches[0];
		return $simple_version;
	}

	private function get_latest_report() {
		$report = $this->find("first", array(
			'conditions' => $this->related_reports_conditions(),
			'order' => 'created desc'
		));
	}

	private function related_reports_conditions() {
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
