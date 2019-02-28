<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
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

use Cake\Model\Model;
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
    public $hasMany = array(
        'Incidents' => array(
            'dependant' => true,
        ),
    );

    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
     * @see http://book.cakephp.org/2.0/en/models/data-validation.html
     * @see Model::$validate
     */
    public $validate = array(
        'error_message' => array(
            'rule' => 'notEmpty',
            'required' => true,
        ),
    );

    /**
     * List of valid finder method options, supplied as the first parameter to find().
     *
     * @var array
     *
     * @see Model::$findMethods
     */
    public $findMethods = array(
        'allDataTable' => true,
        'arrayList' => true,
    );

    /**
     * List of valid finder method options, supplied as the first parameter to find().
     *
     * @var array
     */
    public $status = array(
        'new' => 'New',
        'invalid' => 'Invalid',
        'resolved' => 'Resolved',
        'forwarded' => 'Forwarded',
    );

    public function initialize(array $config)
    {
        $this->hasMany('Incidents', array(
            'dependent' => true,
        ));
    }

    /**
     * Retrieves the incident records that are related to the current report.
     *
     * @return array the list of incidents ordered by creation date desc
     */
    public function getIncidents()
    {
        $incidents = TableRegistry::get('Incidents')->find('all', array(
            'limit' => 50,
            'conditions' => $this->_relatedIncidentsConditions(),
            'order' => 'Incidents.created desc',
        ));

        return $incidents;
    }

    /**
     * Retrieves the report records that are related to the current report.
     *
     * @return array the list of related reports
     */
    public function getRelatedReports()
    {
        return $this->find('all', array(
            'conditions' => $this->_relatedReportsConditions(),
        ));
    }

    /**
     * Retrieves the incident records that are related to the current report that
     * also have a description.
     *
     * @return array the list of incidents ordered by description lenght desc
     */
    public function getIncidentsWithDescription()
    {
        return TableRegistry::get('Incidents')->find('all', array(
            'conditions' => array(
                'NOT' => array(
                    'Incidents.steps is null',
                ),
                $this->_relatedIncidentsConditions(),
            ),
            'order' => 'Incidents.steps desc',
        ));
    }

    /**
     * Retrieves the incident records that are related to the current report that
     * that have a different stacktrace.
     *
     * @return array the list of incidents
     */
    public function getIncidentsWithDifferentStacktrace()
    {
        return TableRegistry::get('Incidents')->find('all', array(
            'fields' => array('DISTINCT Incidents.stackhash', 'Incidents.stacktrace',
                    'Incidents.full_report', 'Incidents.exception_type', ),
            'conditions' => $this->_relatedIncidentsConditions(),
            'group' => 'Incidents.stackhash',
        ));
    }

    /**
     * Removes a report from a group of related reports.
     *
     * @param mixed $report
     */
    public function removeFromRelatedGroup($report)
    {
        $report->related_to = null;
        $this->save($report);

        $rel_report = $this->findByRelatedTo($report->id)->first();
        if ($rel_report) {
            $this->updateAll(
                array('related_to' => $rel_report->id),
                array('related_to' => $report->id)
            );
        }

        // remove all redundant self-groupings
        $this->updateAll(
            array('related_to' => null),
            array('reports.related_to = reports.id')
        );
    }

    /**
     * Adds a report to a group of related reports.
     *
     * @param mixed $report
     * @param mixed $related_to
     */
    public function addToRelatedGroup($report, $related_to)
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
    public function getUrl()
    {
        return Router::url(array('controller' => 'reports', 'action' => 'view',
                $this->id, ), true);
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
     * @param Date   $timeLimit the date at which to start the search
     *
     * @return array the groups with the count of each group and possibly the number
     *               of groups. Ex: array('Apache' => 2) or array(array('Apache' => 2), 1)
     */
    public function getRelatedByField($fieldName, $limit = 10, $count = false,
        $related = true, $timeLimit = null)
    {
        $fieldAlias = "Incidents__$fieldName";
        $queryDetails = array(
            'conditions' => array(
                'NOT' => array(
                    "Incidents.$fieldName is null",
                ),
            ),
            'limit' => $limit,
        );

        if ($related) {
            $queryDetails['conditions'][] = $this->_relatedIncidentsConditions();
        }

        if ($timeLimit) {
            $queryDetails['conditions'][] = array(
                'Incidents.created >=' => $timeLimit,
            );
        }

        $groupedCount = TableRegistry::get('Incidents')->find('all', $queryDetails);

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
                $field = $groupedCount->func()->substring(array(
                    $fieldName => 'literal',
                    '1' => 'literal',
                    "Locate(' ', Incidents.browser)-1" => 'literal',
                    ));
                break;
            case 'server_software':
                // SUBSTRING(server_software, 1, LOCATE('/', Incidents.server_software)-1))
                $field = $groupedCount->func()->substring(array(
                    $fieldName => 'literal', '1' => 'literal',
                    "Locate('/', Incidents.server_software)-1" => 'literal',
                    ));
                break;
            default:
                $field = $fieldName;
        }
        $groupedCount->select(array(
            'count' => $groupedCount->func()->count('*'),
            $fieldAlias => $field,
        ))->group($fieldAlias)->distinct(array("$fieldAlias"))
          ->order('count')->toArray();

        if ($count) {
            $queryDetails['fields'] = array("$fieldName");
            $queryDetails['limit'] = null;
            $queryDetails['group'] = "Incidents.$fieldName";
            $totalCount = TableRegistry::get('Incidents')->find('all', $queryDetails)->count();

            return array($groupedCount, $totalCount);
        }

        return $groupedCount;
    }

    /**
     * Updates the linked reports to a Github issue to newly recieved status.
     *
     * @param string $issueNumber Github Issue number
     * @param string $status      New status to be set
     *
     * @return int Number of Linked reports updated
     */
    public function setLinkedReportStatus($issueNumber, $status)
    {
        $conditions = array(
            'sourceforge_bug_id' => $issueNumber,
        );
        $fields = array(
            'status' => $status,
        );

        return $this->updateAll($fields, $conditions);
    }

    /**
     * returns an array of conditions that would return all related incidents to the
     * current report.
     *
     * @return array the related incidents conditions
     */
    protected function _relatedIncidentsConditions()
    {
        $conditions = array(
            array('Incidents.report_id = ' . $this->id),
        );
        $report = $this->get($this->id);
        if ($report->related_to) { //TODO: fix when fix related reports
            $conditions[] = array('Incidents.report_id = ' . $report->related_to);
        }

        return array('OR' => $conditions);
    }

    /**
     * returns an array of conditions that would return all related reports to the
     * current report.
     *
     * @return array the related reports conditions
     */
    protected function _relatedReportsConditions()
    {
        $conditions = array(array('related_to' => $this->id));
        $report = $this->get($this->id);
        if ($report->related_to) { //TODO: fix related to
            $conditions[] = array('related_to' => $report->related_to);
            $conditions[] = array('id' => $report->related_to);
        }
        $conditions = array(array('OR' => $conditions));
        $conditions[] = array('Reports.id !=' => $this->id);

        return $conditions;
    }
}
