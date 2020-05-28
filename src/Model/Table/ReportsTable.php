<?php

/**
 * Report model representing a group of incidents.
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

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Model\Model;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * A report a representing a group of incidents.
 */
class ReportsTable extends Table
{
    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/associations-linking-models-together.html#hasmany
     * @see Cake::Model::$hasMany
     */
    public $hasMany = [
        'Incidents' => ['dependant' => true],
    ];

    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
     * @see http://book.cakephp.org/2.0/en/models/data-validation.html
     * @see Model::$validate
     */
    public $validate = [
        'error_message' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
    ];

    /**
     * List of valid finder method options, supplied as the first parameter to find().
     *
     * @var array
     *
     * @see Model::$findMethods
     */
    public $findMethods = [
        'allDataTable' => true,
        'arrayList' => true,
    ];

    /**
     * List of valid finder method options, supplied as the first parameter to find().
     *
     * @var array
     */
    public $status = [
        'new' => 'New',
        'invalid' => 'Invalid',
        'resolved' => 'Resolved',
        'forwarded' => 'Forwarded',
    ];

    public function initialize(array $config): void
    {
        $this->hasMany('Incidents', ['dependent' => true]);
    }

    /**
     * Retrieves the incident records that are related to the current report.
     *
     * @return Query the list of incidents ordered by creation date desc
     */
    public function getIncidents(): Query
    {
        return TableRegistry::getTableLocator()->get('Incidents')->find('all', [
            'limit' => 50,
            'conditions' => $this->relatedIncidentsConditions(),
            'order' => 'Incidents.created desc',
        ]);
    }

    /**
     * Retrieves the report records that are related to the current report.
     *
     * @return Query the list of related reports
     */
    public function getRelatedReports(): Query
    {
        return $this->find('all', [
            'conditions' => $this->relatedReportsConditions(),
        ]);
    }

    /**
     * Retrieves the incident records that are related to the current report that
     * also have a description.
     *
     * @return Query the list of incidents ordered by description lenght desc
     */
    public function getIncidentsWithDescription(): Query
    {
        return TableRegistry::getTableLocator()->get('Incidents')->find('all', [
            'conditions' => [
                'NOT' => ['Incidents.steps is null'],
                $this->relatedIncidentsConditions(),
            ],
            'order' => 'Incidents.steps desc',
        ]);
    }

    /**
     * Retrieves the incident records that are related to the current report that
     * that have a different stacktrace.
     *
     * @return Query the list of incidents
     */
    public function getIncidentsWithDifferentStacktrace(): Query
    {
        return TableRegistry::getTableLocator()->get('Incidents')->find('all', [
            'fields' => [
                'DISTINCT Incidents.stackhash',
                'Incidents.stacktrace',
                'Incidents.full_report',
                'Incidents.exception_type',
            ],
            'conditions' => $this->relatedIncidentsConditions(),
            'group' => 'Incidents.stackhash',
        ]);
    }

    /**
     * Removes a report from a group of related reports.
     *
     * @param EntityInterface $report The report instance
     * @return void Nothing
     */
    public function removeFromRelatedGroup(EntityInterface $report): void
    {
        $report->related_to = null;
        $this->save($report);

        $rel_report = $this->findByRelatedTo($report->id)->first();
        if ($rel_report) {
            $this->updateAll(
                ['related_to' => $rel_report->id],
                ['related_to' => $report->id]
            );
        }

        // remove all redundant self-groupings
        $this->updateAll(
            ['related_to' => null],
            ['reports.related_to = reports.id']
        );
    }

    /**
     * Adds a report to a group of related reports.
     *
     * @param EntityInterface $report     The report instance
     * @param int             $related_to The report Id
     * @return void Nothing
     */
    public function addToRelatedGroup(EntityInterface $report, int $related_to): void
    {
        $dup_report = $this->get($related_to);

        if ($dup_report && $dup_report->related_to) {
            $report->related_to = $dup_report->related_to;
        } else {
            $report->related_to = $related_to;
        }
        $report->status = $dup_report->status;

        $this->save($report);
    }

    /**
     * Returns the full url to the current report.
     *
     * @return string url
     */
    public function getUrl(): string
    {
        return Router::url(['controller' => 'reports', 'action' => 'view',
            $this->id,
        ], true);
    }

    /**
     * groups related incidents by distinct values of a field. It may also provide
     * the number of groups, whether to only include incidents that are related
     * to the current report and also to only limit the search to incidents
     * submited after a certain date.
     *
     * @param string $fieldName the name of the field to group by
     * @param int    $limit     the max number of groups to return
     * @param bool   $count     whether to return the number of distinct groups
     * @param bool   $related   whether to limit the search to only related incidents
     * @param string $timeLimit the date at which to start the search
     *
     * @return array|object the groups with the count of each group and possibly the number
     *               of groups. Ex: array('Apache' => 2) or array(array('Apache' => 2), 1)
     */
    public function getRelatedByField(
        string $fieldName,
        int $limit = 10,
        bool $count = false,
        bool $related = true,
        ?string $timeLimit = null
    ) {
        $fieldAlias = 'Incidents__' . $fieldName;
        $queryDetails = [
            'conditions' => [
                'NOT' => ['Incidents.' . $fieldName . ' is null'],
            ],
            'limit' => $limit,
        ];

        if ($related) {
            $queryDetails['conditions'][] = $this->relatedIncidentsConditions();
        }

        if ($timeLimit) {
            $queryDetails['conditions'][] = ['Incidents.created >=' => $timeLimit];
        }

        $groupedCount = TableRegistry::getTableLocator()->get('Incidents')->find('all', $queryDetails);

        /* Ommit version number in case of browser and server_software fields.
         * In case of browser field, version number is seperated by space,
         * for example,'FIREFOX 47', hence split the value using space.
         * In case of server_software field, version number is seperated by /
         * for example, 'nginx/1.7', hence split the value using /.
         * See http://book.cakephp.org/3.0/en/orm/query-builder.html#using-sql-functionsp://book.cakephp.org/3.0/en/orm/query-builder.html#using-sql-functions
         * for how to use Sql functions with cake
         */
        switch ($fieldName) {
            case 'browser':
                // SUBSTRING(browser, 1, LOCATE(' ', Incidents.browser)-1))
                $field = $groupedCount->func()->substring([
                    $fieldName => 'literal',
                    '1' => 'literal',
                    "Locate(' ', Incidents.browser)-1" => 'literal',
                ]);
                break;
            case 'server_software':
                // SUBSTRING(server_software, 1, LOCATE('/', Incidents.server_software)-1))
                $field = $groupedCount->func()->substring([
                    $fieldName => 'literal',
                    '1' => 'literal',
                    "Locate('/', Incidents.server_software)-1" => 'literal',
                ]);
                break;
            default:
                $field = $fieldName;
        }
        $groupedCount->select([
            'count' => $groupedCount->func()->count('*'),
            $fieldAlias => $field,
        ])->group($fieldAlias)->distinct(['' . $fieldAlias . ''])
          ->order('count')->toArray();

        if ($count) {
            $queryDetails['fields'] = ['' . $fieldName . ''];
            $queryDetails['limit'] = null;
            $queryDetails['group'] = 'Incidents.' . $fieldName;
            $totalCount = TableRegistry::getTableLocator()->get('Incidents')->find('all', $queryDetails)->count();

            return [
                $groupedCount,
                $totalCount,
            ];
        }

        return $groupedCount;
    }

    /**
     * Updates the linked reports to a Github issue to newly received status
     *
     * @param string $issueNumber Github Issue number
     * @param string $status      New status to be set
     *
     * @return int Number of Linked reports updated
     */
    public function setLinkedReportStatus(string $issueNumber, string $status): int
    {
        $conditions = ['sourceforge_bug_id' => $issueNumber];
        $fields = ['status' => $status];

        return $this->updateAll($fields, $conditions);
    }

    /**
     * returns an array of conditions that would return all related incidents to the
     * current report.
     *
     * @return array the related incidents conditions
     */
    protected function relatedIncidentsConditions(): array
    {
        $conditions = [
            ['Incidents.report_id = ' . $this->id],
        ];
        $report = $this->get($this->id);
        if ($report->related_to) { //TODO: fix when fix related reports
            $conditions[] = ['Incidents.report_id = ' . $report->related_to];
        }

        return ['OR' => $conditions];
    }

    /**
     * returns an array of conditions that would return all related reports to the
     * current report.
     *
     * @return array the related reports conditions
     */
    protected function relatedReportsConditions(): array
    {
        $conditions = [['related_to' => $this->id]];
        $report = $this->get($this->id);
        if ($report->related_to) { //TODO: fix related to
            $conditions[] = ['related_to' => $report->related_to];
            $conditions[] = ['id' => $report->related_to];
        }
        $conditions = [['OR' => $conditions]];
        $conditions[] = ['Reports.id !=' => $this->id];

        return $conditions;
    }
}
