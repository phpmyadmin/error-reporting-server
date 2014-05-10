<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
/**
 * Stats controller handling stats preview
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) phpMyAdmin project (http://www.phpmyadmin.net)
 * @package       Server.Controller
 * @link          http://www.phpmyadmin.net
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Sanitize', 'Utility');
App::uses('AppController', 'Controller');

/**
 * Stats controller handling stats preview
 *
 * @package       Server.Controller
 */
class StatsController extends AppController {

	public $uses = array("Report", "Incident");

	public $helper = array("Reports");

	public function stats() {
		$filter = $this->_getTimeFilter();
		$relatedEntries = array();
		foreach ($this->Incident->summarizableFields as $field) {
			$entriesWithCount = $this->Report->
					getRelatedByField($field, 25, false, false, $filter["limit"]);
			$relatedEntries[$field] = $entriesWithCount;
		}
		$this->set("related_entries", $relatedEntries);
		$this->set('columns', $this->Incident->summarizableFields);
		$this->set('filter_times', $this->Incident->filterTimes);
		$this->set('selected_filter', $this->request->query('filter'));

		$query = array(
			'fields' => array(
				"DATE_FORMAT(Incident.created, '%a %b %d %Y %T') as date",
				$filter["group"],
				'count(*) as count'
			),
			'group' => 'grouped_by',
			'order' => 'Incident.created',
		);

		if(isset($filter["limit"])) {
			$query["conditions"] = array(
				'Incident.created >=' => $filter["limit"]
			);
		}

		$this->Incident->recursive = -1;
		$downloadStats = $this->Incident->find('all', $query);

		$this->set('download_stats', $downloadStats);
	}

	protected function _getTimeFilter() {
		$filter = $this->Incident->filterTimes[$this->request->query('filter')];
		if (isset($filter)) {
			return $filter;
		} else {
			return $this->Incident->filterTimes["all_time"];
		}
	}
}
