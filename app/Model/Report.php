<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Report model representing a group of incidents.
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

/**
 * A report a representing a group of incidents
 *
 * @package       Server.Model
 */
class Report extends AppModel {

/**
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/associations-linking-models-together.html#hasmany
 * @see Cake::Model::$hasMany
 */
	public $hasMany = array(
		'Incident' => array(
			'dependant' => true
		)
	);

/**
 * @var Array
 * @link http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
 * @link http://book.cakephp.org/2.0/en/models/data-validation.html
 * @see Model::$validate
 */
	public $validate = array(
		'error_message' => array(
			'rule' => 'notEmpty',
			'required' => true
		)
	);

/**
 * List of valid finder method options, supplied as the first parameter to find().
 *
 * @var array
 * @see Model::$findMethods
 */
	public $findMethods = array(
		'allDataTable' =>	true,
		'arrayList' => true,
	);

/**
 * List of valid finder method options, supplied as the first parameter to find().
 *
 * @var array
 */
	public $status = array(
		'new' =>	'New',
		'fixed' =>	'Fixed',
		'wontfix' =>	"Won't Fix",
	);

/**
 * Retrieves the incident records that are related to the current report
 *
 * @return Array the list of incidents ordered by creation date desc
 */
	public function getIncidents() {
		return $this->Incident->find('all', array(
			'limit' => 50,
			'conditions' => $this->_relatedIncidentsConditions(),
			'order' => 'Incident.created desc'
		));
	}

/**
 * Retrieves the report records that are related to the current report
 *
 * @return Array the list of related reports
 */
	public function getRelatedReports() {
		return $this->find("all", array(
			'conditions' => $this->_relatedReportsConditions(),
		));
	}

/**
 * Retrieves the incident records that are related to the current report that
 * also have a description
 *
 * @return Array the list of incidents ordered by description lenght desc
 */
	public function getIncidentsWithDescription() {
		return $this->Incident->find('all', array(
			'conditions' => array(
				'NOT' => array(
					'Incident.steps' => null
				),
				$this->_relatedIncidentsConditions(),
			),
			'order' => 'Incident.steps desc'
		));
	}

/**
 * Retrieves the incident records that are related to the current report that
 * that have a different stacktrace
 *
 * @return Array the list of incidents
 */
	public function getIncidentsWithDifferentStacktrace() {
		return $this->Incident->find('all', array(
			'fields' => array('DISTINCT Incident.stackhash', 'Incident.stacktrace',
					'Incident.full_report'),
			'conditions' => $this->_relatedIncidentsConditions(),
			'group' => "Incident.stackhash",
		));
	}

/**
 * Removes a report from a group of related reports
 *
 * @return void
 */
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

/**
 * Adds a report to a group of related reports
 *
 * @return void
 */
	public function addToRelatedGroup($related_to) {
		$report = $this->findById($related_to);
		if ($report && $report["Report"]["related_to"]) {
			$this->saveField("related_to", $report["Report"]["related_to"]);
		} else {
			$this->saveField("related_to", $related_to);
		}
	}

/**
 * Returns the full url to the current report
 *
 * @return String url
 */
	public function getUrl() {
		return Router::url(array("controller" => "reports", "action" => "view",
				$this->id), true);
	}

/**
 * groups related incidents by distinct values of a field. It may also provide
 * the number of groups, whether to only include incidents that are related
 * to the current report and also to only limit the search to incidents
 * submited after a certain date
 *
 * @param String $fieldName the name of the field to group by
 * @param Integer $limit the max number of groups to return
 * @param Boolean $count whether to return the number of distinct groups
 * @param Boolean $related whether to limit the search to only related incidents
 * @param Date $timeLimit the date at which to start the search
 * @return Array the groups with the count of each group and possibly the number
 *		of groups. Ex: array('Apache' => 2) or array(array('Apache' => 2), 1)
 */
	public function getRelatedByField($fieldName, $limit = 10, $count = false,
			$related = true, $timeLimit = null) {
		$queryDetails = array(
			'fields' => array("DISTINCT Incident.$fieldName", "COUNT(*) as count"),
			'conditions' => array(
				'NOT' => array(
					"Incident.$fieldName" => null
				)
			),
			'limit' => $limit,
			'group' => "Incident.$fieldName",
			'order' => 'count DESC'
		);

		if ($related) {
			$queryDetails["conditions"][] = $this->_relatedIncidentsConditions();
		}

		if ($timeLimit) {
			$queryDetails["conditions"][] = array(
				'Incident.created >=' => $timeLimit
			);
		}

		$groupedCount = $this->Incident->find('groupedCount', $queryDetails);

		if ($count) {
			$queryDetails['limit'] = null;
			$totalCount = $this->Incident->find('count', $queryDetails);

			return array($groupedCount, $totalCount);
		} else {
			return $groupedCount;
		}
	}

/**
 * returns an array of conditions that would return all related incidents to the
 * current report
 *
 * @return Array the related incidents conditions
 */
	protected function _relatedIncidentsConditions() {
		$conditions = array(array('Incident.report_id' => $this->id));

		$conditions[] = array("AND" =>
			array('Report.related_to' => $this->id),
			'Incident.report_id = Report.id',
		);

		if ($this->data["Report"]["related_to"]) {
			$conditions[] = array('Incident.report_id' =>
					$this->data["Report"]["related_to"]);
			$conditions[] = array("AND" =>
				array('Report.related_to' => $this->data["Report"]["related_to"]),
				'Incident.report_id = Report.id'
			);
		}

		return array('OR' => $conditions);
	}

/**
 * returns an array of conditions that would return all related reports to the
 * current report
 *
 * @return Array the related reports conditions
 */
	protected function _relatedReportsConditions() {
		$conditions = array(array('related_to' => $this->id));

		if ($this->data["Report"]["related_to"]) {
			$conditions[] = array('related_to' =>
					$this->data["Report"]["related_to"]);
			$conditions[] = array('id' =>
					$this->data["Report"]["related_to"]);
		}
		$conditions = array(array('OR' => $conditions));
		$conditions[] = array("Report.id !=" => $this->id);
		return $conditions;
	}

/**
 * custom find function that handles find('arrayList') and returns the records
 * as an array of arrays, it does nothing to the query. Only called by
 * Model::find().
 *
 * @param string $state Either "before" or "after"
 * @param array $query
 * @param array $results
 * @return Array the original query or the result as a 2D array
 * @see Model::find()
 */
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

/**
 * Custom find function that handles find('arrayList') and returns records
 * with only one field as a list of values, it does nothing to the query.
 * Only called by Model::find().
 *
 * @param string $state Either "before" or "after"
 * @param array $query
 * @param array $results
 * @return Array the original query or the result as list of values
 * @see Model::find()
 */
	protected function _findArrayList($state, $query, $results = array()) {
		if ($state === 'before') {
			return $query;
		}
		$output = array();
		foreach ($results as $row) {
			$output[] = array_values($row['Report'])[0];
		}
		return $output;
	}
}
