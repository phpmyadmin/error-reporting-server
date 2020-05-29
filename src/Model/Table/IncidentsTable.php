<?php

/**
 * An incident a representing a single incident of a submited bug.
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

use Cake\Log\Log;
use Cake\Model\Model;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use function array_merge;
use function array_push;
use function array_slice;
use function count;
use function date;
use function hash_final;
use function hash_init;
use function hash_update;
use function in_array;
use function json_encode;
use function mb_strlen;
use function preg_match;
use function strtotime;
use function time;

/**
 * An incident a representing a single incident of a submited bug.
 */
class IncidentsTable extends Table
{
    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
     * @see Model::$actsAs
     */
    public $actsAs = ['Summarizable'];

    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
     * @see http://book.cakephp.org/2.0/en/models/data-validation.html
     * @see Model::$validate
     */
    public $validate = [
        'pma_version' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'php_version' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'full_report' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'stacktrace' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'browser' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'stackhash' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'user_os' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'locale' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'script_name' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'server_software' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
        'configuration_storage' => [
            'rule' => 'notEmpty',
            'required' => true,
        ],
    ];

    /**
     * @var array
     *
     * @see http://book.cakephp.org/2.0/en/models/associations-linking-models-together.html#belongsto
     * @see Model::$belongsTo
     */

    /**
     * The fields which are summarized in the report page with charts and are also
     * used in the overall stats and charts for the website.
     *
     * @var array
     */
    public $summarizableFields = [
        'browser',
        'pma_version',
        'php_version',
        'locale',
        'server_software',
        'user_os',
        'script_name',
        'configuration_storage',
    ];

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->filterTimes = [
            'all_time' => [
                'label' => 'All Time',
                'limit' => null,
                'group' => "DATE_FORMAT(Incidents.created, '%m %Y')",
            ],
            'day' => [
                'label' => 'Last Day',
                'limit' => date('Y-m-d', strtotime('-1 day')),
                'group' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y %H')",
            ],
            'week' => [
                'label' => 'Last Week',
                'limit' => date('Y-m-d', strtotime('-1 week')),
                'group' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y')",
            ],
            'month' => [
                'label' => 'Last Month',
                'limit' => date('Y-m-d', strtotime('-1 month')),
                'group' => "DATE_FORMAT(Incidents.created, '%a %b %d %Y')",
            ],
            'year' => [
                'label' => 'Last Year',
                'limit' => date('Y-m-d', strtotime('-1 year')),
                'group' => "DATE_FORMAT(Incidents.created, '%b %u %Y')",
            ],
        ];
    }

    /**
     * creates an incident/report record given a raw bug report object.
     *
     * This gets a decoded bug report from the submitted json body. This has not
     * yet been santized. It either adds it as an incident to another report or
     * creates a new report if nothing matches.
     *
     * @param array|null $bugReport the bug report being submitted
     *
     * @return array of:
     *          1. array of inserted incident ids. If the report/incident was not
     *               correctly saved, false is put in it place.
     *          2. array of newly created report ids. If no new report was created,
     *               an empty array is returned
     */
    public function createIncidentFromBugReport(?array $bugReport): array
    {
        if ($bugReport === null) {
            return [
                'incidents' => [false],
                'reports' => [],
            ];
        }
        $incident_ids = [];    // array to hold ids of all the inserted incidents
        $new_report_ids = []; // array to hold ids of all newly created reports

        // Avoid storing too many errors from single report
        if (isset($bugReport['errors']) && count($bugReport['errors']) > 40) {
            $bugReport['errors'] = array_slice($bugReport['errors'], 0, 40);
        }
        if (isset($bugReport['exception']['stack']) && count($bugReport['exception']['stack']) > 40) {
            $bugReport['exception']['stack'] = array_slice($bugReport['exception']['stack'], 0, 40);
        }

        // Also sanitizes the bug report
        $schematizedIncidents = $this->getSchematizedIncidents($bugReport);
        $incidentsTable = TableRegistry::getTableLocator()->get('Incidents');
        $reportsTable = TableRegistry::getTableLocator()->get('Reports');
        foreach ($schematizedIncidents as $index => $si) {
            // find closest report. If not found, create a new report.
            $closestReport = $this->getClosestReport($bugReport, $index);
            if ($closestReport) {
                $si['report_id'] = $closestReport['id'];
                $si = $incidentsTable->newEntity($si);
                $si->created = date('Y-m-d H:i:s', time());
                $si->modified = date('Y-m-d H:i:s', time());

                $this->logLongIncidentSubmissions($si, $incident_ids);
                if (in_array(false, $incident_ids)) {
                    break;
                }
            } else {
                // no close report. Create a new report.
                $report = $this->getReportDetails($bugReport, $index);

                $this->logLongIncidentSubmissions($si, $incident_ids);
                if (in_array(false, $incident_ids)) {
                    break;
                }

                $report = $reportsTable->newEntity($report);
                $report->created = date('Y-m-d H:i:s', time());
                $report->modified = date('Y-m-d H:i:s', time());
                $reportsTable->save($report);

                $si['report_id'] = $report->id;
                $new_report_ids[] = $report->id;
                $si = $incidentsTable->newEntity($si);
                $si->created = date('Y-m-d H:i:s', time());
                $si->modified = date('Y-m-d H:i:s', time());
            }

            $isSaved = $incidentsTable->save($si);
            if ($isSaved) {
                array_push($incident_ids, $si->id);
                if (! $closestReport) {
                    // add notifications entry
                    $tmpIncident = $incidentsTable->findById($si->id)->all()->first();
                    if (! TableRegistry::getTableLocator()->get('Notifications')->addNotifications((int) $tmpIncident['report_id'])) {
                        Log::write(
                            'error',
                            'ERRORED: Notification::addNotifications() failed on Report#'
                                . $tmpIncident['report_id'],
                            'alert'
                        );
                    }
                }
            } else {
                array_push($incident_ids, false);
            }
        }

        return [
            'incidents' => $incident_ids,
            'reports' => $new_report_ids,
        ];
    }

    /**
     * retrieves the closest report to a given bug report.
     *
     * it checks for another report with the same line number, filename and
     * pma_version
     *
     * @param array $bugReport the bug report being checked
     *                         Integer $index: for php exception type
     * @param int   $index     The report index
     *
     * @return Entity|null the first similar report or null
     */
    protected function getClosestReport(array $bugReport, int $index = 0): ?Entity
    {
        if (isset($bugReport['exception_type'])
            && $bugReport['exception_type'] === 'php'
        ) {
            $location = $bugReport['errors'][$index]['file'];
            $linenumber = $bugReport['errors'][$index]['lineNum'];
        } else {
            [$location, $linenumber] =
                    $this->getIdentifyingLocation($bugReport['exception']['stack']);
        }

        return TableRegistry::getTableLocator()->get('Reports')->findByLocationAndLinenumberAndPmaVersion(
            $location,
            $linenumber,
            $this->getStrippedPmaVersion($bugReport['pma_version'])
        )->all()->first();
    }

    /**
     * creates the report data from an incident that has no related report.
     *
     * @param array $bugReport the bug report the report record is being created for
     *                         Integer $index: for php exception type
     * @param int   $index     The report index
     *
     * @return array an array with the report fields can be used with Report->save
     */
    protected function getReportDetails(array $bugReport, int $index = 0): array
    {
        if (isset($bugReport['exception_type'])
            && $bugReport['exception_type'] === 'php'
        ) {
            $location = $bugReport['errors'][$index]['file'];
            $linenumber = $bugReport['errors'][$index]['lineNum'];
            $reportDetails = [
                'error_message' => $bugReport['errors'][$index]['msg'],
                'error_name' => $bugReport['errors'][$index]['type'],
            ];
            $exception_type = 1;
        } else {
            [$location, $linenumber] =
                $this->getIdentifyingLocation($bugReport['exception']['stack']);

            $reportDetails = [
                'error_message' => $bugReport['exception']['message'],
                'error_name' => $bugReport['exception']['name'],
            ];
            $exception_type = 0;
        }

        $reportDetails = array_merge(
            $reportDetails,
            [
                'status' => 'new',
                'location' => $location,
                'linenumber' => $linenumber ?? 0,
                'pma_version' => $this->getStrippedPmaVersion($bugReport['pma_version']),
                'exception_type' => $exception_type,
            ]
        );

        return $reportDetails;
    }

    /**
     * creates the incident data from the submitted bug report.
     *
     * @param array $bugReport the bug report the report record is being created for
     *
     * @return array an array of schematized incident.
     *               Can be used with Incident->save
     */
    protected function getSchematizedIncidents(array $bugReport): array
    {
        //$bugReport = Sanitize::clean($bugReport, array('escape' => false));
        $schematizedReports = [];
        $schematizedCommonReport = [
            'pma_version' => $this->getStrippedPmaVersion($bugReport['pma_version']),
            'php_version' => $this->getSimpleVersion($bugReport['php_version'], 2),
            'browser' => $bugReport['browser_name'] . ' '
                    . $this->getSimpleVersion($bugReport['browser_version'], 1),
            'user_os' => $bugReport['user_os'],
            'locale' => $bugReport['locale'],
            'configuration_storage' => $bugReport['configuration_storage'],
            'server_software' => $this->getServer($bugReport['server_software']),
            'full_report' => json_encode($bugReport),
        ];

        if (isset($bugReport['exception_type'])
            && $bugReport['exception_type'] === 'php'
        ) {
            // for each "errors"
            foreach ($bugReport['errors'] as $error) {
                $tmpReport = array_merge(
                    $schematizedCommonReport,
                    [
                        'error_name' => $error['type'],
                        'error_message' => $error['msg'],
                        'script_name' => $error['file'],
                        'stacktrace' => json_encode($error['stackTrace']),
                        'stackhash' => $error['stackhash'],
                        'exception_type' => 1,         // 'php'
                    ]
                );
                array_push($schematizedReports, $tmpReport);
            }
        } else {
            $tmpReport = array_merge(
                $schematizedCommonReport,
                [
                    'error_name' => $bugReport['exception']['name'],
                    'error_message' => $bugReport['exception']['message'],
                    'script_name' => $bugReport['script_name'],
                    'stacktrace' => json_encode($bugReport['exception']['stack']),
                    'stackhash' => $this->getStackHash($bugReport['exception']['stack']),
                    'exception_type' => 0,     //'js'
                ]
            );

            if (isset($bugReport['steps'])) {
                $tmpReport['steps'] = $bugReport['steps'];
            }
            array_push($schematizedReports, $tmpReport);
        }

        return $schematizedReports;
    }

    /**
     * Gets the identifiying location info from a stacktrace.
     *
     * This is used to skip stacktrace levels that are within the error reporting js
     * files that sometimes appear in the stacktrace but are not related to the bug
     * report
     *
     * returns two things in an array:
     * - the first element is the filename/scriptname of the error
     * - the second element is the linenumber of the error
     *
     * @param array $stacktrace the stacktrace being examined
     *
     * @return array an array with the filename/scriptname and linenumber of the
     *               error
     */
    protected function getIdentifyingLocation(array $stacktrace): array
    {
        $fallback = [
            'UNKNOWN',
            0,
        ];
        foreach ($stacktrace as $level) {
            if (isset($level['filename'])) {
                // ignore unrelated files that sometimes appear in the error report
                if ($level['filename'] === 'tracekit/tracekit.js') {
                    continue;
                }

                if ($level['filename'] === 'error_report.js') {
                    // in case the error really is in the error_report.js file save it for
                    // later
                    if ($fallback[0] === 'UNKNOWN') {
                        $fallback = [
                            $level['filename'],
                            $level['line'],
                        ];
                    }
                    continue;
                }

                return [
                    $level['filename'],
                    $level['line'],
                ];
            }

            if (isset($level['scriptname'])) {
                return [
                    $level['scriptname'],
                    $level['line'],
                ];
            }
            continue;
        }

        return $fallback;
    }

    /**
     * Gets a part of a version string according to the specified version Length.
     *
     * @param string $versionString the version string
     * @param string $versionLength the number of version components to return. eg
     *                              1 for major version only and 2 for major and
     *                              minor version
     *
     * @return string the major and minor version part
     */
    protected function getSimpleVersion(string $versionString, string $versionLength): string
    {
        $versionLength = (int) $versionLength;
        if ($versionLength < 1) {
            $versionLength = 1;
        }
        /* modify the re to accept a variable number of version components. I
         * atleast take one component and optionally get more components if need be.
         * previous code makes sure that the $versionLength variable is a positive
         * int
         */
        $result = preg_match(
            '/^(\d+\.){' . ($versionLength - 1) . '}\d+/',
            $versionString,
            $matches
        );
        if ($result) {
            return $matches[0];
        }

        return $versionString;
    }

    /**
     * Returns the version string stripped of
     * 'deb', 'ubuntu' and other suffixes
     *
     * @param string $versionString phpMyAdmin version
     *
     * @return string stripped phpMyAdmin version
     */
    public function getStrippedPmaVersion(string $versionString): string
    {
        $allowedRegexp = '/^(\d+)(\.\d+){0,3}(\-.*)?/';
        $matches = [];

        // Check if $versionString matches the regexp
        // and store the matched strings
        if (preg_match($allowedRegexp, $versionString, $matches)) {
            return $matches[0];
        }

        // If $versionString does not match the regexp at all,
        // leave it as it is
        return $versionString;
    }

    /**
     * Gets the server name and version from the server signature.
     *
     * @param string $signature the server signature
     *
     * @return string the server name and version or UNKNOWN
     */
    protected function getServer(string $signature): string
    {
        if (preg_match(
            '/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)'
                . '|(lighttpd\/\d+\.\d+)/i',
            $signature,
            $matches
        )) {
            return $matches[0];
        }

        return 'UNKNOWN';
    }

    /**
     * returns the hash pertaining to a stacktrace.
     *
     * @param array $stacktrace the stacktrace in question
     *
     * @return string the hash string of the stacktrace
     */
    public function getStackHash(array $stacktrace): string
    {
        $handle = hash_init('md5');
        foreach ($stacktrace as $level) {
            $elements = [
                'filename',
                'scriptname',
                'line',
                'func',
                'column',
            ];
            foreach ($elements as $element) {
                if (! isset($level[$element])) {
                    continue;
                }
                hash_update($handle, $level[$element]);
            }
        }

        return hash_final($handle);
    }

    /**
     * Checks the length of stacktrace and full_report
     * and logs if it is greater than what it can hold
     *
     * @param object $si           submitted incident
     * @param array  $incident_ids incident IDs
     * @return void Nothing
     */
    private function logLongIncidentSubmissions($si, array &$incident_ids): void// phpcs:ignore SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
    {
        $stacktraceLength = mb_strlen($si['stacktrace']);
        $fullReportLength = mb_strlen($si['full_report']);
        $errorMessageLength = mb_strlen($si['error_message']);

        if ($stacktraceLength <= 65535
            && $fullReportLength <= 65535
            && $errorMessageLength <= 200 // length of field in 'incidents' table
        ) {
            return;
        }

        // If length of report is longer than
        // what can fit in the table field,
        // we log it and don't save it in the database
        Log::error(
            'Too long data submitted in the incident. The length of stacktrace: '
            . $stacktraceLength . ', the length of bug report: '
            . $fullReportLength . ', the length of error message: '
            . $errorMessageLength . '. The full incident reported was as follows: '
            . json_encode($si)
        );

        // add a 'false' to the return array
        array_push($incident_ids, false);
    }
}
