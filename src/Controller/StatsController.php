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

use App\Model\Table\IncidentsTable;
use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;

use function array_key_exists;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * Stats controller handling stats preview.
 */
class StatsController extends AppController
{
    protected IncidentsTable $Incidents;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * @return void Nothing
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->viewBuilder()->setHelpers(['Reports']);
        $this->Incidents = $this->fetchTable('Incidents');
    }

    public function stats(): void
    {
        $filter = $this->getTimeFilter();
        $relatedEntries = [];
        $filter_string = $this->getFilterName();

        $entriesWithCount = [];
        //Cache::clear();
        foreach ($this->Incidents->summarizableFields as $field) {
            $entriesWithCount = Cache::read($field . '_' . $filter_string);
            if ($entriesWithCount === null) {
                $entriesWithCount = TableRegistry::getTableLocator()->get('Reports')->
                        getRelatedByField($field, 25, false, null, $filter['limit']);
                $entriesWithCount = json_encode($entriesWithCount);
                Cache::write($field . '_' . $filter_string, $entriesWithCount);
            }

            $relatedEntries[$field] = json_decode($entriesWithCount, true);
        }

        $this->set('related_entries', $relatedEntries);
        $this->set('columns', $this->Incidents->summarizableFields);
        $this->set('filter_times', $this->Incidents->filterTimes);
        // No filter at all keeps no option selected, any other value selects
        // the filter actually used (which is all_time for an unknown one).
        $this->set(
            'selected_filter',
            $this->request->getQuery('filter') === null ? null : $filter_string,
        );

        $query = [
            'group' => 'grouped_by',
            'order' => 'Incidents.created',
            'conditions' => [],
        ];

        if (isset($filter['limit'])) {
            $query['conditions'] = [
                'Incidents.created >=' => $filter['limit'],
            ];
        }

        $downloadStats = Cache::read('downloadStats_' . $filter_string);
        if ($downloadStats === null) {
            $downloadStats = $this->Incidents->find(
                'all',
                group: $query['group'],
                order: $query['order'],
                conditions: $query['conditions'],
            );
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
     * Returns the name of the selected time filter.
     *
     * Any value which is not one of the known filters falls back to the all
     * time filter, so that it can be used in a cache key.
     *
     * @return string the name of the filter
     */
    protected function getFilterName(): string
    {
        $filterName = $this->request->getQuery('filter');
        if (! is_string($filterName) || ! array_key_exists($filterName, $this->Incidents->filterTimes)) {
            return 'all_time';
        }

        return $filterName;
    }

    /**
     * Returns the selected time filter.
     *
     * @return array<mixed> the filter with its label, limit and group
     */
    protected function getTimeFilter(): array
    {
        return $this->Incidents->filterTimes[$this->getFilterName()];
    }
}
