<?php

/**
 * Stats controller handling stats preview.
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;
use function json_decode;
use function json_encode;

/**
 * Stats controller handling stats preview.
 */
class StatsController extends AppController
{
    /** @var string[] */
    public $uses = [
        'Report',
        'Incident',
        'Notification',
    ];

    /** @var string[] */
    public $helper = ['Reports'];

    public function stats(): void
    {
        $filter = $this->getTimeFilter();
        $relatedEntries = [];
        $filter_string = $this->request->query('filter');
        if (! $filter_string) {
            $filter_string = 'all_time';
        }
        $entriesWithCount = [];
        //Cache::clear(false);
        foreach (TableRegistry::getTableLocator()->get('Incidents')->summarizableFields as $field) {
            $entriesWithCount = Cache::read($field . '_' . $filter_string);
            if ($entriesWithCount === false) {
                $entriesWithCount = TableRegistry::getTableLocator()->get('Reports')->
                        getRelatedByField($field, 25, false, false, $filter['limit']);
                $entriesWithCount = json_encode($entriesWithCount);
                Cache::write($field . '_' . $filter_string, $entriesWithCount);
            }
            $relatedEntries[$field] = json_decode($entriesWithCount, true);
        }
        $this->set('related_entries', $relatedEntries);
        $this->set('columns', TableRegistry::getTableLocator()->get('Incidents')->summarizableFields);
        $this->set('filter_times', TableRegistry::getTableLocator()->get('Incidents')->filterTimes);
        $this->set('selected_filter', $this->request->query('filter'));

        $query = [
            'group' => 'grouped_by',
            'order' => 'Incidents.created',
        ];

        if (isset($filter['limit'])) {
            $query['conditions'] = [
                'Incidents.created >=' => $filter['limit'],
            ];
        }

        TableRegistry::getTableLocator()->get('Incidents')->recursive = -1;
        $downloadStats = Cache::read('downloadStats_' . $filter_string);
        if ($downloadStats === false) {
            $downloadStats = TableRegistry::getTableLocator()->get('Incidents')->find('all', $query);
            $downloadStats->select([
                'grouped_by' => $filter['group'],
                'date' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y %T')",
                'count' => $downloadStats->func()->count('*'),
            ]);
            $downloadStats = json_encode($downloadStats->toArray());
            Cache::write('downloadStats_' . $filter_string, $downloadStats);
        }
        $this->set('download_stats', json_decode($downloadStats, true));
    }

    /**
     * @return mixed I am not sure about the type
     */
    protected function getTimeFilter()
    {
        if ($this->request->query('filter')) {
            $filter = TableRegistry::getTableLocator()->get('Incidents')->filterTimes[$this->request->query('filter')];
        }
        if (isset($filter)) {
            return $filter;
        }

        return TableRegistry::getTableLocator()->get('Incidents')->filterTimes['all_time'];
    }
}
