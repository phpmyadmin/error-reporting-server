<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * An incident a representing a single incident of a submited bug
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Model
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('AppModel', 'Model');
App::uses('Sanitize', 'Utility');

/**
 * An incident a representing a single incident of a submited bug
 *
 * @package       Server.Model
 */
class Incident extends AppModel {

/**
 * @var Array
 * @link http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
 * @see Model::$actsAs
 */
	public $actsAs = array('Summarizable');

/**
 * @var Array
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
 * @link http://book.cakephp.org/2.0/en/models/data-validation.html
 * @see Model::$validate
 */
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
		'stackhash' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'user_os' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'script_name' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'server_software' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
		'configuration_storage' => array(
			'rule' => 'notEmpty',
			'required'	 => true,
		),
	);

/**
 * @var Array
 * @link http://book.cakephp.org/2.0/en/models/associations-linking-models-together.html#belongsto
 * @see Model::$belongsTo
 */
	public $belongsTo = array('Report');

/**
 * The fields which are summarized in the report page with charts and are also
 * used in the overall stats and charts for the website
 * @var Array
 */
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

/**
 * creates an incident/report record given a raw bug report object
 *
 * This gets a decoded bug report from the submitted json body. This has not
 * yet been santized. It either adds it as an incident to another report or
 * creates a new report if nothing matches.
 *
 * @param Array $bugReport the bug report being submitted
 * @return Boolean If the report/incident was correctly saved
 */
	public function createIncidentFromBugReport($bugReport) {
		$schematizedIncident = $this->_getSchematizedIncident($bugReport);
		$closestReport = $this->_getClosestReport($bugReport);

		if($closestReport) {
			$schematizedIncident["report_id"] = $closestReport["Report"]["id"];
			return $this->save($schematizedIncident);
		} else {
			$report = $this->_getReportDetails($bugReport);
			$data = array(
				'Incident' => $schematizedIncident,
				'Report' => $report
			);
			return $this->saveAssociated($data);
		}
	}

/**
 * retrieves the closest report to a given bug report
 *
 * it checks for another report with the same line number, filename and
 * pma_version
 *
 * @param Array $bugReport the bug report being checked
 * @return Array the first similar report or null
 */
	protected function _getClosestReport($bugReport) {
		List($location, $linenumber) =
				$this->_getIdentifyingLocation($bugReport['exception']['stack']);
		$report = $this->Report->findByLocationAndLinenumberAndPmaVersion(
				$location, $linenumber, $bugReport['pma_version']);
		return $report;
	}

/**
 * creates the report data from an incident that has no related report.
 *
 * @param Array $bugReport the bug report the report record is being created for
 * @return Array an array with the report fields can be used with Report->save
 */
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

/**
 * creates the incident data from the submitted bug report.
 *
 * @param Array $bugReport the bug report the report record is being created for
 * @return Array an array with the incident fields can be used with ِIncident->save
 */
	protected function _getSchematizedIncident($bugReport) {
		$bugReport = Sanitize::clean($bugReport);
		$schematizedReport = array(
			'pma_version' => $bugReport['pma_version'],
			'php_version' => $this->_getSimpleVersion($bugReport['php_version'], 2),
			'steps' => $bugReport['steps'],
			'error_message' => $bugReport['exception']['message'],
			'error_name' => $bugReport['exception']['name'],
			'browser' => $bugReport['browser_name'] . " "
					. $this->_getSimpleVersion($bugReport['browser_version'], 1),
			'user_os' => $bugReport['user_os'],
			'script_name' => $bugReport['script_name'],
			'configuration_storage' => $bugReport['configuration_storage'],
			'server_software' => $this->_getServer($bugReport['server_software']),
			'stackhash' => $this->getStackHash($bugReport['exception']['stack']),
			'full_report' => json_encode($bugReport),
			'stacktrace' => json_encode($bugReport['exception']['stack']),
		);

		return $schematizedReport;
	}

/**
 * Gets the identifiying location info from a stacktrace
 *
 * This is used to skip stacktrace levels that are within the error reporting js
 * files that sometimes appear in the stacktrace but are not related to the bug
 * report
 *
 * returns two things in an array:
 * - the first element is the filename/scriptname of the error
 * - the second element is the linenumber of the error
 *
 * @param Array $stacktrace the stacktrace being examined
 * @return Array an array with the filename/scriptname and linenumber of the
 *	 error
 */
	protected function _getIdentifyingLocation($stacktrace) {
		foreach ($stacktrace as $level) {
			if (isset($level["filename"])) {
				// ignore unrelated files that sometimes appear in the error report
				if ($level["filename"] === "tracekit.js") {
					continue;
				} elseif($level["filename"] === "error_report.js") {
					// in case the error really is in the error_report.js file save it for
					// later
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


/**
 * Gets a part of a version string according to the specified version Length
 *
 * @param  String $phpVersion the version string
 * @param  String $versionLength the number of version components to return. eg
 *                               1 for major version only and 2 for major and
 *                               minor version
 * @return String the major and minor version part
 */
	protected function _getSimpleVersion($versionString, $versionLength) {
		$versionLength = (int) $versionLength;
		if ($versionLength < 1) {
			$versionLength = 1;
		}
		/* modify the re to accept a variable number of version components. I
		 * atleast take one component and optionally get more components if need be.
		 * previous code makes sure that the $versionLength variable is a positive
		 * int
		 */
		preg_match("/^(\d+\.){" . ($versionLength - 1) . "}\d+/", $versionString,
				$matches);
		$simpleVersion = $matches[0];
		return $simpleVersion;
	}

/**
 * Gets the server name and version from the server signature
 *
 * @param String $signature the server signature
 * @return String the server name and version or UNKNOWN
 */
	protected function _getServer($signature) {
		if (preg_match("/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)"
				. "|(lighttpd\/\d+\.\d+)/i",
				$signature, $matches)) {
			return $matches[0];
		} else {
			return "UNKNOWN";
		}
	}

/**
 * returns the hash pertaining to a stacktrace
 *
 * @param Array $stacktrace the stacktrace in question
 * @return String the hash string of the stacktrace
 */
	public function getStackHash($stacktrace) {
		$handle = hash_init("md5");
		foreach ($stacktrace as $level) {
			$elements = array("filename", "scriptname", "line", "func", "column");
			foreach ($elements as $element) {
				if (!isset($level[$element])) {
					continue;
				}
				hash_update($handle, $level[$element]);
			}
		}
		return hash_final($handle);
	}
}
