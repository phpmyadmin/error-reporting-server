<?php

/**
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

namespace App\Command;

use App\Model\Table\IncidentsTable;
use App\Model\Table\ReportsTable;
use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

use function json_encode;

/**
 * Stats command.
 */
class StatsCommand extends Command
{
    protected IncidentsTable $Incidents;
    protected ReportsTable $Reports;

    public static function defaultName(): string
    {
        return 'stats';
    }

    public static function getDescription(): string
    {
        return 'Build statistics';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setCommand($this->name)
            ->setDescription('Build stats');
    }

    public function initialize(): void
    {
        parent::initialize();
        $this->Incidents = $this->fetchTable('Incidents');
        $this->Reports = $this->fetchTable('Reports');
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        foreach ($this->Incidents->filterTimes as $filter_string => $filter) {
            foreach ($this->Incidents->summarizableFields as $field) {
                $io->out('processing ' . $filter_string . ':' . $field);
                $entriesWithCount = $this->Reports->
                        getRelatedByField($field, 25, false, false, $filter['limit']);
                $entriesWithCount = json_encode($entriesWithCount);
                Cache::write($field . '_' . $filter_string, $entriesWithCount);
            }

            $query = [
                'group' => 'grouped_by',
                'order' => 'Incidents.created',
            ];
            if (isset($filter['limit'])) {
                $query['conditions'] = [
                    'Incidents.created >=' => $filter['limit'],
                ];
            }

            $downloadStats = $this->Incidents->find('all', $query);
            $downloadStats->select([
                'grouped_by' => $filter['group'],
                'date' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y %T')",
                'count' => $downloadStats->func()->count('*'),
            ]);
            $downloadStats = json_encode($downloadStats->toArray());
            Cache::write('downloadStats_' . $filter_string, $downloadStats);
        }
    }
}
