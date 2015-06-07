<?php
/* vim: set noexpandtab sw=2 ts=2 sts=2: */
namespace App\Controller;

use App\Controller\AppController;
use Cake\ORM\TableRegistry;
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


/**
 * Stats controller handling stats preview
 *
 * @package       Server.Controller
 */
class StatsController extends AppController {

	public $uses = array("Report", "Incident", "Notification");

	public $helper = array("Reports");

	public function stats() {
		$filter = $this->_getTimeFilter();
		$relatedEntries = array();
		foreach (TableRegistry::get('Incidents')->summarizableFields as $field) {
			$entriesWithCount = TableRegistry::get('Reports')->
					getRelatedByField($field, 25, false, false, $filter["limit"]);
			$relatedEntries[$field] = $entriesWithCount;
		}
		$this->set("related_entries", $relatedEntries);
		$this->set('columns', TableRegistry::get('Incidents')->summarizableFields);
		$this->set('filter_times', TableRegistry::get('Incidents')->filterTimes);
		$this->set('selected_filter', $this->request->query('filter'));

		$query = array(
			'group' => 'grouped_by',
			'order' => 'Incidents.created',
		);

		if(isset($filter["limit"])) {
			$query["conditions"] = array(
				'Incidents.created >=' => $filter["limit"]
			);
		}

		TableRegistry::get('Incidents')->recursive = -1;
		$downloadStats = TableRegistry::get('Incidents')->find('all', $query);
        $downloadStats->select([
            'grouped_by' => $filter["group"],
            'date' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y %T')",
            'count' => $downloadStats->func()->count('*')
        ]);
		$this->set('download_stats', $downloadStats);
	}

	protected function _getTimeFilter() {
        if ($this->request->query('filter')) {
            $filter = TableRegistry::get('Incidents')->filterTimes[$this->request->query('filter')];
        }
		if (isset($filter)) {
			return $filter;
		} else {
			return TableRegistry::get('Incidents')->filterTimes["all_time"];
		}
	}
}
