<?php

namespace App\Shell;

use Cake\Console\Shell;
use Cake\Cache\Cache;

class StatsShell extends Shell
{
    public function initialize()
    {
        parent::initialize();
        $this->loadModel('Incidents');
        $this->loadModel('Reports');
    }
    public function main()
    {
        //Cache::clear(false);
        foreach ($this->Incidents->filterTimes as $filter_string=>$filter) {
            foreach ($this->Incidents->summarizableFields as $field) {
                $this->out("processing " . $filter_string. ":".$field);
                $entriesWithCount = $this->Reports->
                        getRelatedByField($field, 25, false, false, $filter["limit"]);
                $entriesWithCount = json_encode($entriesWithCount);
                Cache::write($field.'_'.$filter_string, $entriesWithCount);
            }
            $query = array(
                'group' => 'grouped_by',
                'order' => 'Incidents.created',
            );
            if(isset($filter["limit"])) {
                $query["conditions"] = array(
                    'Incidents.created >=' => $filter["limit"]
                );
            }
            $downloadStats = $this->Incidents->find('all', $query);
            $downloadStats->select([
                'grouped_by' => $filter["group"],
                'date' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y %T')",
                'count' => $downloadStats->func()->count('*')
            ]);
            $downloadStats = json_encode($downloadStats->toArray());
            Cache::write('downloadStats_'.$filter_string, $downloadStats);
        }
    }
}

