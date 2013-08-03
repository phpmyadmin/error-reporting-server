<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('AppModel', 'Model');

class Incident extends AppModel {

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

	public $belongsTo = array('Report');

	public $findMethods = array(
		'groupedCount'=> true
  );

	protected function _getClosestReport($exception) {
		List($location, $linenumber) = $this->_getIdentifyingLocation($exception["stack"]);
		$report = $this->Report->findByLocationAndLinenumber($location, $linenumber);
		return $report;
	}

	public function createIncidentFromBugReport($bugReport) {
		$schematizedIncident = $this->_getSchematizedIncident($bugReport);
		$closestReport = $this->_getClosestReport($bugReport["exception"]);

		if($closestReport) {
			$schematizedIncident["report_id"] = $closestReport["Report"]["id"];
			return $this->save($schematizedIncident);
		} else {
			$report = $this->_getReportDetails($bugReport);
			$data = array(
				'Incident' => $schematizedIncident,
				'Report' => $report
			);
			$this->saveAssociated($data);
		}
	}

	protected function _getReportDetails($bugReport) {
		List($location, $linenumber) =
				$this->_getIdentifyingLocation($bugReport["exception"]["stack"]);

		$reportDetails = array(
			'error_message' => $bugReport['exception']['message'],
			'error_name' => $bugReport['exception']['name'],
			'status' => 'new',
			'location' => $location,
			'linenumber' => $linenumber,
		);
		return $reportDetails;
	}

	protected function _getSchematizedIncident($bugReport) {
		$schematizedReport = array(
			'pma_version' => $bugReport['pma_version'],
			'php_version' => $this->_getSimplePhpVersion($bugReport['php_version']),
			'steps' => $bugReport['steps'],
			'error_message' => $bugReport['exception']['message'],
			'error_name' => $bugReport['exception']['name'],
			'browser' => $bugReport['browser_name'] . " "
					. $this->_getMajorVersion($bugReport['browser_version']),
			'user_os' => $bugReport['user_os'],
			'server_software' => $this->_getServer($bugReport['server_software']),
			'full_report' => json_encode($bugReport),
			'stacktrace' => json_encode($bugReport['exception']['stack']),
		);

		return $schematizedReport;
	}

	protected function _getIdentifyingLocation($stacktrace) {
		foreach ($stacktrace as $level) {
			if (isset($level["filename"])) {
				if ($level["filename"] !== "tracekit.js"
						&& $level["filename"] !== "error_report.js") {
					return array($level["filename"], $level["line"]);
				} else {
					continue;
				}
			}
			if (isset($level["uri"])) {
				return array($level["uri"], $level["line"]);
			} else {
				return array($level["url"], $level["line"]);
			}
		}
	}

	protected function _getMajorVersion($fullVersion) {
		preg_match("/^\d+/", $fullVersion, $matches);
		$simpleVersion = $matches[0];
		return $simpleVersion;
	}

	protected function _getSimplePhpVersion($phpVersion) {
		preg_match("/^\d+\.\d+/", $phpVersion, $matches);
		$simpleVersion = $matches[0];
		return $simpleVersion;
	}

	protected function _getServer($signature) {
		if (preg_match("/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)"
				. "|(lighttpd\/\d+\.\d+)/i",
				$signature, $matches)) {
			return $matches[0];
		} else {
			return "UNKNOWN";
		}
	}

	protected function _findGroupedCount($state, $query, $results = array()) {
		if ($state === 'before') {
			return $query;
		}
		$output = array();
		foreach ($results as $row) {
			foreach ($row['Incident'] as $key => $value) {
				$output[$value] = $row[0]['count'];
			}
		}
		return $output;
	}
}
