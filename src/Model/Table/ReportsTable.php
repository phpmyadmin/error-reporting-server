<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Model\Table;

use Cake\ORM\Table;
use App\Model\AppModel;
use Cake\Model\Model;
use Cake\Routing\Router;
use Cake\ORM\TableRegistry;
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


/**
 * A report a representing a group of incidents
 *
 * @package       Server.Model
 */
class ReportsTable extends Table {

    /**
 * @var array
 * @link http://book.cakephp.org/2.0/en/models/associations-linking-models-together.html#hasmany
 * @see Cake::Model::$hasMany
 */
	public $hasMany = array(
		'Incidents' => array(
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
		'open' => 	"Open",
		'pending' =>	"Pending",
		'resolved' =>	"Resolved",
		'invalid' => "Invalid",
		'duplicate' => "Duplicate",
		'works-for-me' => "Works for me",
		'out-of-date' => "Out of Date"
	);

    public function initialize(array $config)
    {
        $this->hasMany('Incidents', [
            'dependent' => true
        ]);
    }
/**
 * Retrieves the incident records that are related to the current report
 *
 * @return Array the list of incidents ordered by creation date desc
 */
	public function getIncidents() {
		$incidents = TableRegistry::get('Incidents')->find('all', array(
			'limit' => 50,
			'conditions' => $this->_relatedIncidentsConditions(),
			'order' => 'Incidents.created desc'
		));
        return $incidents;
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
		return TableRegistry::get('Incidents')->find('all', array(
			'conditions' => array(
				'NOT' => array(
					'Incidents.steps is null'
				),
				$this->_relatedIncidentsConditions(),
			),
			'order' => 'Incidents.steps desc'
		));
	}

/**
 * Retrieves the incident records that are related to the current report that
 * that have a different stacktrace
 *
 * @return Array the list of incidents
 */
	public function getIncidentsWithDifferentStacktrace() {
		return TableRegistry::get('Incidents')->find('all', array(
			'fields' => array('DISTINCT Incidents.stackhash', 'Incidents.stacktrace',
					'Incidents.full_report', 'Incidents.exception_type'),
			'conditions' => $this->_relatedIncidentsConditions(),
			'group' => "Incidents.stackhash",
		));
	}

/**
 * Removes a report from a group of related reports
 *
 * @return void
 */
	public function removeFromRelatedGroup($report) {
		$report->related_to = null;
		$this->save($report);

		$rel_report = $this->findByRelatedTo($report->id)->first();
		if ($rel_report) {
			$this->updateAll(
				array("related_to" => $rel_report->id),
				array("related_to" => $report->id)
			);
		}

		// remove all redundant self-groupings
		$this->updateAll(
			array("related_to" => null),
			array("reports.related_to = reports.id")
		);
	}

/**
 * Adds a report to a group of related reports
 *
 * @return void
 */
	public function addToRelatedGroup($report, $related_to) {
		$dup_report = $this->get($related_to);

		if ($dup_report && $dup_report->related_to) {
			$report->related_to = $dup_report->related_to;
		} else {
			$report->related_to = $related_to;
		}
		$this->save($report);
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
		$queryDetails = [
			'fields' => ["$fieldName"],
			'conditions' => [
				'NOT' => [
					"Incidents.$fieldName is null"
				]
			],
			'limit' => $limit,
			'group' => "Incidents.$fieldName"
		];

		if ($related) {
			$queryDetails["conditions"][] = $this->_relatedIncidentsConditions();
		}

		if ($timeLimit) {
			$queryDetails["conditions"][] = [
				'Incidents.created >=' => $timeLimit
			];
		}

		$groupedCount = TableRegistry::get('Incidents')->find("all", $queryDetails);
        $groupedCount->select([
            'count' => $groupedCount->func()->count('*')
        ])->distinct(["$fieldName"])->order('count')->toArray();

		if ($count) {
			$queryDetails['limit'] = null;
			$totalCount = TableRegistry::get('Incidents')->find("all", $queryDetails)->count();
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
		$conditions = array(array('Incidents.report_id = '.$this->id));
        $report = $this->get($this->id);
		if ($report->related_to) { //TODO: fix when fix related reports
			$conditions[] = array('Incidents.report_id = '.
					$report->related_to);
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
        $report = $this->get($this->id);
		if ($report->related_to) { //TODO: fix related to
			$conditions[] = array('related_to' =>
					$report->related_to);
			$conditions[] = array('id' =>
					$report->related_to);
		}
		$conditions = array(array('OR' => $conditions));
		$conditions[] = array("Reports.id !=" => $this->id);
		return $conditions;
	}
}
