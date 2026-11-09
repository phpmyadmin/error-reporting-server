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

use Cake\Core\Configure;
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
use function is_array;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;
use function max;
use function mb_strimwidth;
use function mb_strlen;
use function mb_substr;
use function min;
use function preg_match;
use function preg_replace;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtotime;
use function substr;
use function time;
use function trim;
use function version_compare;

/**
 * An incident a representing a single incident of a submited bug.
 */
class IncidentsTable extends Table
{
    /**
     * @see http://book.cakephp.org/2.0/en/models/behaviors.html#using-behaviors
     * @see Model::$actsAs
     */
    public array $actsAs = ['Summarizable'];

    /**
     * @see http://book.cakephp.org/2.0/en/models/model-attributes.html#validate
     * @see http://book.cakephp.org/2.0/en/models/data-validation.html
     * @see Model::$validate
     */
    public array $validate = [
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
     */
    public array $summarizableFields = [
        'browser',
        'pma_version',
        'php_version',
        'locale',
        'server_software',
        'user_os',
        'script_name',
        'configuration_storage',
    ];

    /** @var array[] */
    public array $filterTimes = [];

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
    public function createIncidentFromBugReport(array|null $bugReport): array
    {
        if ($bugReport === null) {
            return [
                'incidents' => [false],
                'reports' => [],
            ];
        }

        // Old versions are not maintained anymore, their reports are noise.
        $pmaVersion = $bugReport['pma_version'] ?? '';
        if (! self::isSupportedPmaVersion(is_string($pmaVersion) ? $pmaVersion : '')) {
            // The value is written by a client: a newline in it would forge a
            // line of the log, and a long one would fill the disk before the
            // body cap below is applied.
            Log::info(
                'Ignoring a report of the unsupported phpMyAdmin version "'
                . substr((string) preg_replace('/[^\x20-\x7E]/', '?', is_string($pmaVersion) ? $pmaVersion : ''), 0, 40)
                . '".',
                'cron_jobs',
            );

            return [
                'incidents' => [false],
                'reports' => [],
            ];
        }

        $incident_ids = []; // array to hold ids of all the inserted incidents
        $new_report_ids = []; // array to hold ids of all newly created reports

        // The errors of a PHP report are its content: a report which does not
        // carry a list of errors has nothing to store.
        if (
            isset($bugReport['exception_type'])
            && $bugReport['exception_type'] === 'php'
            && (! isset($bugReport['errors']) || ! is_array($bugReport['errors']))
        ) {
            Log::info(
                'Ignoring a PHP report whose errors field is not a list.',
                'cron_jobs',
            );

            return [
                'incidents' => [false],
                'reports' => [],
            ];
        }

        $bugReport = self::capReport($bugReport);

        // Also sanitizes the bug report
        $schematizedIncidents = $this->getSchematizedIncidents($bugReport);
        $incidentsTable = TableRegistry::getTableLocator()->get('Incidents');
        $developerIds = null;
        $reportsTable = TableRegistry::getTableLocator()->get('Reports');
        foreach ($schematizedIncidents as $index => $si) {
            if (isset($si['error_message'])) {
                $si['error_message'] = mb_strimwidth($si['error_message'], 0, 200, '...');
            }

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
                    // The report id is the one the incident was just built
                    // with: reading the row back only to get it was one query
                    // per error, and the developers were read again for every
                    // report instead of once per submission.
                    $developerIds ??= NotificationsTable::developerIds();
                    $savedReportId = (int) $si->get('report_id');
                    if (! NotificationsTable::addNotifications($savedReportId, $developerIds)) {
                        Log::write(
                            'error',
                            'ERRORED: Notification::addNotifications() failed on Report#'
                                . $savedReportId,
                            'alert',
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
     * Read a line number the way an int(11) column can hold it.
     *
     * The value is written by a client: a line of ten digits does not fit, and
     * a strict server refuses the whole insert over it. Both the lookup and the
     * stored value go through here, so they keep agreeing.
     *
     * @param mixed $value The value of the payload
     */
    protected function lineNumber(mixed $value): int
    {
        return max(0, min((int) $this->stringValue($value), self::MAX_LINE_NUMBER));
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
    protected function getClosestReport(array $bugReport, int $index = 0): Entity|null
    {
        if (isset($bugReport['exception_type']) && $bugReport['exception_type'] === 'php') {
            $error = $bugReport['errors'][$index] ?? [];
            if (! is_array($error)) {
                $error = [];
            }

            $location = self::truncate($this->stringValue($error['file'] ?? ''), 65535);
            $linenumber = $this->lineNumber($error['lineNum'] ?? '');
        } else {
            $exception = $bugReport['exception'] ?? [];
            $stack = is_array($exception) && is_array($exception['stack'] ?? null)
                ? $exception['stack']
                : [];
            [$location, $linenumber] =
                    $this->getIdentifyingLocation($stack);
            $location = self::truncate($this->stringValue($location), 65535);
            $linenumber = $this->lineNumber($linenumber);
        }

        return TableRegistry::getTableLocator()->get('Reports')->findByLocationAndLinenumberAndPmaVersion(
            $location,
            $linenumber,
            self::getStrippedPmaVersion($bugReport['pma_version']),
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
        if (isset($bugReport['exception_type']) && $bugReport['exception_type'] === 'php') {
            $error = $bugReport['errors'][$index] ?? [];
            if (! is_array($error)) {
                $error = [];
            }

            $location = self::truncate($this->stringValue($error['file'] ?? ''), 65535);
            $linenumber = $this->lineNumber($error['lineNum'] ?? '');
            $reportDetails = [
                'error_message' => self::truncate($this->stringValue($error['msg'] ?? ''), 150),
                'error_name' => self::truncate($this->stringValue($error['type'] ?? ''), 50),
            ];
            $exception_type = 1;
        } else {
            $exception = $bugReport['exception'] ?? [];
            if (! is_array($exception)) {
                $exception = [];
            }

            $stack = is_array($exception['stack'] ?? null) ? $exception['stack'] : [];
            [$location, $linenumber] =
                $this->getIdentifyingLocation($stack);
            $location = self::truncate($this->stringValue($location), 65535);
            $linenumber = $this->lineNumber($linenumber);

            $reportDetails = [
                'error_message' => self::truncate($this->stringValue($exception['message'] ?? ''), 150),
                'error_name' => self::truncate($this->stringValue($exception['name'] ?? ''), 50),
            ];
            $exception_type = 0;
        }

        $reportDetails = array_merge(
            $reportDetails,
            [
                'status' => 'new',
                'location' => $location,
                'linenumber' => $linenumber,
                'pma_version' => self::getStrippedPmaVersion($bugReport['pma_version']),
                'exception_type' => $exception_type,
            ],
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
            'pma_version' => self::truncate(
                self::getStrippedPmaVersion($this->stringValue($bugReport['pma_version'] ?? '')),
                30,
            ),
            'php_version' => self::truncate(
                $this->getSimpleVersion($this->stringValue($bugReport['php_version'] ?? ''), 2),
                15,
            ),
            'browser' => self::truncate(
                $this->stringValue($bugReport['browser_name'] ?? '') . ' '
                    . $this->getSimpleVersion($this->stringValue($bugReport['browser_version'] ?? ''), 1),
                40,
            ),
            'user_os' => self::truncate($this->stringValue($bugReport['user_os'] ?? ''), 30),
            'locale' => self::truncate($this->stringValue($bugReport['locale'] ?? ''), 30),
            'configuration_storage' => self::truncate(
                $this->stringValue($bugReport['configuration_storage'] ?? ''),
                30,
            ),
            'server_software' => self::truncate(
                $this->getServer($this->stringValue($bugReport['server_software'] ?? '')),
                100,
            ),
            'full_report' => json_encode($bugReport),
        ];

        if (isset($bugReport['exception_type']) && $bugReport['exception_type'] === 'php') {
            $errors = $bugReport['errors'] ?? [];
            if (! is_array($errors)) {
                $errors = [];
            }

            // for each "errors"
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    $error = [];
                }

                $tmpReport = array_merge(
                    $schematizedCommonReport,
                    [
                        'error_name' => self::truncate($this->stringValue($error['type'] ?? ''), 30),
                        'error_message' => self::truncate($this->stringValue($error['msg'] ?? ''), 200),
                        'script_name' => self::truncate($this->stringValue($error['file'] ?? ''), 255),
                        'stacktrace' => json_encode(
                            is_array($error['stackTrace'] ?? null) ? $error['stackTrace'] : [],
                        ),
                        'stackhash' => self::truncate($this->stringValue($error['stackhash'] ?? ''), 32),
                        'exception_type' => 1, // 'php'
                    ],
                );
                array_push($schematizedReports, $tmpReport);
            }
        } else {
            $exception = $bugReport['exception'] ?? [];
            if (! is_array($exception)) {
                $exception = [];
            }

            $stack = is_array($exception['stack'] ?? null) ? $exception['stack'] : [];
            $tmpReport = array_merge(
                $schematizedCommonReport,
                [
                    'error_name' => self::truncate($this->stringValue($exception['name'] ?? ''), 30),
                    'error_message' => self::truncate($this->stringValue($exception['message'] ?? ''), 200),
                    'script_name' => self::truncate($this->stringValue($bugReport['script_name'] ?? ''), 255),
                    'stacktrace' => json_encode($stack),
                    'stackhash' => self::getStackHash($stack),
                    'exception_type' => 0, //'js'
                ],
            );

            if (isset($bugReport['steps'])) {
                $tmpReport['steps'] = $this->stringValue($bugReport['steps']);
            }

            array_push($schematizedReports, $tmpReport);
        }

        return $schematizedReports;
    }

    /**
     * Returns a string usable in a database column for an untrusted value.
     *
     * The fields of a bug report are decoded from a request body, they are not
     * guaranteed to hold the type the schema expects.
     *
     * @param mixed $value the value read from the bug report
     *
     * @return string the value itself, its string representation or '' for a
     *                value which cannot be stored as a string
     */
    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Truncates a value to the length of the database column storing it.
     *
     * @param string $value  the value to store
     * @param int    $length the length of the column, in characters
     *
     * @return string the value, cut to the column length if needed
     */
    public static function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length);
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
            if (! is_array($level)) {
                continue;
            }

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
                            $level['line'] ?? 0,
                        ];
                    }

                    continue;
                }

                return [
                    $level['filename'],
                    $level['line'] ?? 0,
                ];
            }

            if (isset($level['scriptname'])) {
                return [
                    $level['scriptname'],
                    $level['line'] ?? 0,
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
            $matches,
        );
        if ($result) {
            return $matches[0];
        }

        return $versionString;
    }

    /**
     * Largest number of entries of a report that are kept.
     */
    public const MAX_REPORT_ENTRIES = 40;

    /**
     * Longest version this application stores: the width of the column.
     */
    public const MAX_PMA_VERSION_LENGTH = 50;

    /**
     * Largest line number an int(11) column holds.
     */
    public const MAX_LINE_NUMBER = 2147483647;

    /**
     * Keep the beginning of the list of errors and of the stack of a report.
     *
     * A payload carries one entry per error and one frame per call: without
     * this cap a single request stores, renders and forwards thousands of
     * them.
     *
     * @param array<string, mixed> $bugReport The report as it was received
     *
     * @return array<string, mixed> The report, capped
     */
    public static function capReport(array $bugReport): array
    {
        if (
            isset($bugReport['errors']) && is_array($bugReport['errors'])
            && count($bugReport['errors']) > self::MAX_REPORT_ENTRIES
        ) {
            $bugReport['errors'] = array_slice($bugReport['errors'], 0, self::MAX_REPORT_ENTRIES);
        }

        if (isset($bugReport['exception']) && ! is_array($bugReport['exception'])) {
            $bugReport['exception'] = [];
        }

        if (isset($bugReport['exception']['stack'])) {
            if (! is_array($bugReport['exception']['stack'])) {
                $bugReport['exception']['stack'] = [];
            } elseif (count($bugReport['exception']['stack']) > self::MAX_REPORT_ENTRIES) {
                $bugReport['exception']['stack'] = array_slice(
                    $bugReport['exception']['stack'],
                    0,
                    self::MAX_REPORT_ENTRIES,
                );
            }
        }

        return $bugReport;
    }

    /**
     * Tells whether a report of this phpMyAdmin version is still accepted.
     *
     * The accepted versions come from the AcceptedReportsVersions key of the
     * configuration, an entry is either a branch like "4.9.*" or a minimum
     * version like ">=5.2". There is no fallback list: a missing or empty key
     * accepts nothing, the application stores what it is told to store.
     *
     * @param string $versionString phpMyAdmin version
     *
     * @return bool true if the version is accepted
     */
    public static function isSupportedPmaVersion(string $versionString): bool
    {
        // Keep the numeric part only, "5.2.4-dev" and "6.0.0-alpha1" are
        // development builds of a released branch.
        $version = preg_replace('/[^0-9.].*$/', '', self::getStrippedPmaVersion($versionString));
        if ($version === null || preg_match('/^\d+(\.\d+)*$/', $version) !== 1) {
            return false;
        }

        // The value that is stored, not its numeric prefix: "5.2.1-" followed by
        // anything passes the version rules with a short prefix and does not fit
        // in the column. Refuse it here rather than let the save fail, or let a
        // server that is not in strict mode truncate it.
        if (strlen(self::getStrippedPmaVersion($versionString)) > self::MAX_PMA_VERSION_LENGTH) {
            return false;
        }

        $accepted = Configure::read('AcceptedReportsVersions');
        if (! is_array($accepted)) {
            return false;
        }

        foreach ($accepted as $rule) {
            if (! is_string($rule)) {
                continue;
            }

            if (str_starts_with($rule, '>=')) {
                if (version_compare($version, trim(substr($rule, 2)), '>=')) {
                    return true;
                }

                continue;
            }

            if (str_ends_with($rule, '.*')) {
                // A branch needs at least a patch number, "4.9" alone is not
                // a released version.
                $branch = substr($rule, 0, -2);
                if ($branch !== '' && str_starts_with($version, $branch . '.')) {
                    return true;
                }

                continue;
            }

            if ($version === $rule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the version string stripped of
     * 'deb', 'ubuntu' and other suffixes
     *
     * @param string $versionString phpMyAdmin version
     *
     * @return string stripped phpMyAdmin version
     */
    public static function getStrippedPmaVersion(string $versionString): string
    {
        // This regex contains a negative lookahead to avoid fedora version suffix
        // Use: https://regex101.com/
        // See tests: IncidentsTableTest::versionsStripping
        $allowedRegexp = '/^((\d+)(\.\d+){0,3}(\-[a-z0-9]+){0,1})(?!\.[a-z]{1,}[0-9]{1,})/';
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
        if (
            preg_match(
                '/(apache\/\d+\.\d+)|(nginx\/\d+\.\d+)|(iis\/\d+\.\d+)'
                . '|(lighttpd\/\d+\.\d+)/i',
                $signature,
                $matches,
            )
        ) {
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
            if (! is_array($level)) {
                continue;
            }

            $elements = [
                'filename',
                'scriptname',
                'line',
                'func',
                'column',
            ];
            foreach ($elements as $element) {
                if (! isset($level[$element]) || ! is_scalar($level[$element])) {
                    continue;
                }

                hash_update($handle, (string) $level[$element]);
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
        // A TEXT column holds 65 535 bytes, not 65 535 characters.
        $stacktraceLength = strlen($si['stacktrace']);
        $fullReportLength = strlen($si['full_report']);

        if ($stacktraceLength <= 65535 && $fullReportLength <= 65535) {
            return;
        }

        // If length of report is longer than
        // what can fit in the table field,
        // we log it and don't save it in the database
        Log::error(
            'Too long data submitted in the incident. The length of stacktrace: '
            . $stacktraceLength . ', the length of bug report: '
            . $fullReportLength . '. The beginning of the incident was: '
            . substr((string) json_encode($si), 0, 1000),
        );

        // add a 'false' to the return array
        array_push($incident_ids, false);
    }
}
