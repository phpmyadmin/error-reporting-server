<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */

App::uses('AppModel', 'Model');
App::uses('Sanitize', 'Utility');

class Incident extends AppModel {

  public $actsAs = array('Summarizable');

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

	public $summarizableFields = array('browser', 'pma_version', 'php_version',
			'server_software', 'user_os', 'script_name', 'configuration_storage');

	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);

		$this->filterTimes = array(
			'all_time' => array(
				'label' => 'All Time',
				'limit' => null,
				'group' => "DATE_FORMAT(Incident.created, '%m %Y') as grouped_by",
			),
			'day' => array(
				'label' => 'Last Day',
				'limit' => date('Y-m-d', strtotime('-1 day')),
				'group' =>
						"DATE_FORMAT(Incident.created, '%a %b %d %Y %H') as grouped_by",
			),
			'week' => array(
				'label' => 'Last Week',
				'limit' => date('Y-m-d', strtotime('-1 week')),
				'group' =>
						"DATE_FORMAT(Incident.created, '%a %b %d %Y') as grouped_by",
			),
			'month' => array(
				'label' => 'Last Month',
				'limit' => date('Y-m-d', strtotime('-1 month')),
				'group' =>
						"DATE_FORMAT(Incident.created, '%a %b %d %Y') as grouped_by",
			),
			'year' => array(
				'label' => 'Last Year',
				'limit' => date('Y-m-d', strtotime('-1 year')),
				'group' => "DATE_FORMAT(Incident.created, '%b %u %Y') as grouped_by",
			),
		);
	}

	public function createIncidentFromBugReport($bugReport) {
		$schematizedIncident = $this->_getSchematizedIncident($bugReport);
		$closestReport = $this->_getClosestReport($bugReport);

		if($closestReport) {
			$schematizedIncident["report_id"] = $closestReport["Report"]["id"];

			$this->Report->read(null, $closestReport["Report"]["id"]);
			$incidents = $this->Report->getIncidentsWithDifferentStacktrace();
			$schematizedIncident["different_stacktrace"] =
					$this->_hasDifferentStacktrace($schematizedIncident, $incidents);
			return $this->save($schematizedIncident);
		} else {
			$report = $this->_getReportDetails($bugReport);
			$schematizedIncident["different_stacktrace"] = true;
			$data = array(
				'Incident' => $schematizedIncident,
				'Report' => $report
			);
			return $this->saveAssociated($data);
		}
	}

	protected function _hasDifferentStacktrace($newIncident, $incidents) {
		$newIncident["stacktrace"] = json_decode($newIncident["stacktrace"], true);
		foreach ($incidents as $incident) {
			$incident["Incident"]["stacktrace"] =
					json_decode($incident["Incident"]["stacktrace"], true);
			if ($this->_isSameStacktrace($newIncident["stacktrace"],
					$incident["Incident"]["stacktrace"])) {
				return false;
			}
		}
		return true;
	}

	protected function _getClosestReport($bugReport) {
		List($location, $linenumber) =
				$this->_getIdentifyingLocation($bugReport['exception']['stack']);
		$report = $this->Report->findByLocationAndLinenumberAndPmaVersion(
				$location, $linenumber, $bugReport['pma_version']);
		return $report;
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
			'pma_version' => $bugReport['pma_version'],
		);
		return $reportDetails;
	}

	protected function _getSchematizedIncident($bugReport) {
		$bugReport = Sanitize::clean($bugReport);
		$schematizedReport = array(
			'pma_version' => $bugReport['pma_version'],
			'php_version' => $this->_getSimplePhpVersion($bugReport['php_version']),
			'steps' => $bugReport['steps'],
			'error_message' => $bugReport['exception']['message'],
			'error_name' => $bugReport['exception']['name'],
			'browser' => $bugReport['browser_name'] . " "
					. $this->_getMajorVersion($bugReport['browser_version']),
			'user_os' => $bugReport['user_os'],
			'script_name' => $bugReport['script_name'],
			'configuration_storage' => $bugReport['configuration_storage'],
			'server_software' => $this->_getServer($bugReport['server_software']),
			'full_report' => json_encode($bugReport),
			'stacktrace' => json_encode($bugReport['exception']['stack']),
		);

		return $schematizedReport;
	}

	protected function _getIdentifyingLocation($stacktrace) {
		foreach ($stacktrace as $level) {
			if (isset($level["filename"])) {
				// ignore unrelated files that sometimes appear in the error report
				if ($level["filename"] === "tracekit.js") {
					continue;
				} elseif($level["filename"] === "error_report.js") {
					// incase the error is in the error_report.js file
					if(!isset($fallback)) {
						$fallback = array($level["filename"], $level["line"]);
					}
					continue;
				} else {
					return array($level["filename"], $level["line"]);
				}
			} elseif (isset($level["scriptname"])) {
				return array($level["scriptname"], $level["line"]);
			} else {
				continue;
			}
		}
		return $fallback;
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

	protected function _isSameStacktrace($stacktraceA, $stacktraceB) {
		if (count($stacktraceA) != count($stacktraceB)) {
			return false;
		}

		for ($i = 0; $i < count($stacktraceA); $i++) {
			$levelA = $stacktraceA[$i];
			$levelB = $stacktraceB[$i];
			$elements = array("filename", "scriptname", "line", "func", "column");
			foreach ($elements as $element) {
				if (isset($levelA[$element]) xor isset($levelB[$element])) {
					return false;
				}

				if (!isset($levelA[$element]) && !isset($levelB[$element])) {
					continue;
				}

				if ($levelA[$element] !== $levelB[$element]) {
					return false;
				}
			}
			return true;
		}
	}
}
