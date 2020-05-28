<?php

namespace App\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use ReflectionMethod;
use const DS;
use function count;
use function file_get_contents;
use function in_array;
use function json_decode;
use function json_encode;

class IncidentsTableTest extends TestCase
{
    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = [
        'app.Notifications',
        'app.Developers',
        'app.Reports',
        'app.Incidents',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->Incidents = TableRegistry::getTableLocator()->get('Incidents');
    }

    public function testGetStackHash(): void
    {
        //$method = new ReflectionMethod('Incident', 'getStackHash');
        //$method->setAccessible(true);

        $stacktrace1 = [
            [
                'filename' => 'file1',
                'line' => 300,
            ],
            [
                'filename' => 'file2',
                'line' => 200,
            ],
        ];

        $stacktrace2 = [
            [
                'line' => 300,
                'filename' => 'file1',
            ],
            [
                'line' => 200,
                'filename' => 'file2',
            ],
        ];

        $result = $this->Incidents->getStackHash($stacktrace1);
        $this->assertEquals('a441639902837d88db25214812c0cd83', $result);

        $result = $this->Incidents->getStackHash($stacktrace2);
        $this->assertEquals('a441639902837d88db25214812c0cd83', $result);
    }

    public function testGetServer(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getServer');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->Incidents,
            'some random/data Apache/2.1.7;other.random/data'
        );
        $this->assertEquals('Apache/2.1', $result);

        $result = $method->invoke(
            $this->Incidents,
            'some random/data Nginx/1.0.7;other.random/data'
        );
        $this->assertEquals('Nginx/1.0', $result);

        $result = $method->invoke(
            $this->Incidents,
            'some random/data Lighttpd/4.6.7;other.random/data'
        );
        $this->assertEquals('Lighttpd/4.6', $result);

        $result = $method->invoke(
            $this->Incidents,
            'some random/data DoesNotExist/3.5;other.random/data'
        );
        $this->assertEquals('UNKNOWN', $result);
    }

    public function testGetSimpleVersion(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getSimpleVersion');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->Incidents,
            '15.3.12.17',
            1
        );
        $this->assertEquals('15', $result);

        $result = $method->invoke(
            $this->Incidents,
            '15.3.12.17',
            2
        );
        $this->assertEquals('15.3', $result);

        $result = $method->invoke(
            $this->Incidents,
            '15.3.12.17',
            3
        );
        $this->assertEquals('15.3.12', $result);

        $result = $method->invoke(
            $this->Incidents,
            '15.3.12.17',
            'wrong argument'
        );
        $this->assertEquals('15', $result);

        $result = $method->invoke(
            $this->Incidents,
            '15.3.12.17',
            -1
        );
        $this->assertEquals('15', $result);
    }

    public function testGetIdentifyingLocation(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getIdentifyingLocation');
        $method->setAccessible(true);

        $stacktrace = [
            [
                'filename' => 'file1',
                'line' => 300,
            ],
            [
                'filename' => 'file2',
                'line' => 200,
            ],
        ];

        $stacktrace_script = [
            [
                'scriptname' => 'script1',
                'line' => 300,
            ],
            [
                'filename' => 'file2',
                'line' => 200,
            ],
        ];

        $stacktrace_uri = [
            [
                'uri' => 'sql.php',
                'line' => 5,
            ],
            [
                'filename' => 'file2',
                'line' => 200,
            ],
        ];

        $result = $method->invoke(
            $this->Incidents,
            $stacktrace_script
        );
        $this->assertEquals(['script1', 300], $result);

        $result = $method->invoke(
            $this->Incidents,
            $stacktrace
        );
        $this->assertEquals(['file1', 300], $result);

        $stacktrace[0]['filename'] = 'tracekit/tracekit.js';
        $result = $method->invoke(
            $this->Incidents,
            $stacktrace
        );
        $this->assertEquals(['file2', 200], $result);

        $stacktrace[0]['filename'] = 'error_report.js';
        $result = $method->invoke(
            $this->Incidents,
            $stacktrace
        );
        $this->assertEquals(['file2', 200], $result);

        $stacktrace[1] = null;
        $result = $method->invoke(
            $this->Incidents,
            $stacktrace
        );
        $this->assertEquals(['error_report.js', 300], $result);

        $result = $method->invoke(
            $this->Incidents,
            $stacktrace_uri
        );
        $this->assertEquals(['file2', 200], $result);
    }

    public function testGetSchematizedIncident(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getSchematizedIncidents');
        $method->setAccessible(true);

        // Case-1: JavaScript Report
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        $result = $method->invoke(
            $this->Incidents,
            $bugReport
        );

        $expected = [
            [
                'pma_version' => '4.5.4.1',
                'php_version' => '5.2',
                'steps' => '<script>test steps',
                'error_message' => 'a is not defined',
                'error_name' => 'ReferenceError',
                'browser' => 'FIREFOX 17',
                'user_os' => 'Windows',
                'locale' => 'en',
                'script_name' => 'tbl_relation.php',
                'configuration_storage' => 'enabled',
                'server_software' => 'NginX/1.17',
                'stackhash' => '9db5408094f1e76ef7161b7bbf3ddfe4',
                'full_report' => json_encode($bugReport),
                'stacktrace' => json_encode($bugReport['exception']['stack']),
                'exception_type' => 0,
            ],
        ];

        $this->assertEquals($expected, $result);

        // Case-2: php Report
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReport = json_decode($bugReport, true);

        $result = $method->invoke(
            $this->Incidents,
            $bugReport
        );

        $expected = [
            [
                'pma_version' => '4.5.4.1',
                'php_version' => '5.5',
                'error_message' => 'Undefined variable: haha',
                'error_name' => 'Notice',
                'browser' => 'CHROME 27',
                'user_os' => 'Linux',
                'locale' => 'en',
                'script_name' => './libraries/Config.class.php',
                'configuration_storage' => 'disabled',
                'server_software' => 'Apache/2.4',
                'stackhash' => '5063bbe81a2daa6a6ad39c5cd315701c',
                'full_report' => json_encode($bugReport),
                'stacktrace' => json_encode($bugReport['errors'][0]['stackTrace']),
                'exception_type' => 1,
            ],
            [
                'pma_version' => '4.5.4.1',
                'php_version' => '5.5',
                'error_message' => 'Undefined variable: hihi',
                'error_name' => 'Notice',
                'browser' => 'CHROME 27',
                'user_os' => 'Linux',
                'locale' => 'en',
                'script_name' => './libraries/Util.class.php',
                'configuration_storage' => 'disabled',
                'server_software' => 'Apache/2.4',
                'stackhash' => 'e911a21765eae766463612e033773716',
                'full_report' => json_encode($bugReport),
                'stacktrace' => json_encode($bugReport['errors'][1]['stackTrace']),
                'exception_type' => 1,
            ],
            [
                'pma_version' => '4.5.4.1',
                'php_version' => '5.5',
                'error_message' => 'Undefined variable: hehe',
                'error_name' => 'Notice',
                'browser' => 'CHROME 27',
                'user_os' => 'Linux',
                'locale' => 'en',
                'script_name' => './index.php',
                'configuration_storage' => 'disabled',
                'server_software' => 'Apache/2.4',
                'stackhash' => '37848b23bdd6e737273516b9575fe407',
                'full_report' => json_encode($bugReport),
                'stacktrace' => json_encode($bugReport['errors'][2]['stackTrace']),
                'exception_type' => 1,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetReportDetails(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getReportDetails');
        $method->setAccessible(true);

        // case-1: JavaScript BugReport
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        $result = $method->invoke(
            $this->Incidents,
            $bugReport
        );

        $expected = [
            'error_message' => 'a is not defined',
            'error_name' => 'ReferenceError',
            'status' => 'new',
            'location' => 'error.js',
            'linenumber' => (int) 312,
            'pma_version' => '4.5.4.1',
            'exception_type' => 0,
        ];

        $this->assertEquals($expected, $result);

        // case-2: php BugReport
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReport = json_decode($bugReport, true);

        $result = $method->invoke(
            $this->Incidents,
            $bugReport,
            1
        );

        $expected = [
            'error_message' => 'Undefined variable: hihi',
            'error_name' => 'Notice',
            'status' => 'new',
            'location' => './libraries/Util.class.php',
            'linenumber' => (int) 557,
            'pma_version' => '4.5.4.1',
            'exception_type' => 1,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetClosestReport(): void
    {
        $method = new ReflectionMethod($this->Incidents, 'getClosestReport');
        $method->setAccessible(true);

        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        $returnedReport = null;

        $result = $method->invoke(
            $this->Incidents,
            $bugReport
        );

        $this->assertEquals($returnedReport, $result);
    }

    public function testCreateIncidentFromBugReport(): void
    {
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        // Case-1: 'js' report

        // Case-1.1: closest report = null
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(1, count($result['incidents']));

        // One new report added (no closest report found)
        $this->assertEquals(1, count($result['reports']));

        // Case-1.2: closest report = some report.
        // Previously(in Case-1.1) inserted Reports serve as ClosestReports.
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(1, count($result['incidents']));

        // No new report added (closest report found)
        $this->assertEquals(0, count($result['reports']));

        $incident = $this->Incidents->get($result['incidents'][0]);
        // check the incident has been reported under the same 'Report'
        $result = TableRegistry::getTableLocator()->get('Incidents')->find('all', ['conditions' => ['report_id = ' . $incident->report_id]]);
        $result = $result->hydrate(false)->toArray();
        $this->assertEquals(2, count($result));

        // Case-2: for 'php' reports
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReport = json_decode($bugReport, true);
        // Case-2.1: closest report = null.
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(3, count($result['incidents']));

        // Three (one for each incident) new report added (no closest report found)
        $this->assertEquals(3, count($result['reports']));

        // Case-2.2: closest report = some report.
        // Previously(in Case-2.1) inserted Reports serve as ClosestReports.
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(3, count($result['incidents']));

        // No new report added (closest report found)
        $this->assertEquals(0, count($result['reports']));

        // check the incidents have been reported under the same 'Report's
        $incident = $this->Incidents->get($result['incidents'][0]);
        $result = TableRegistry::getTableLocator()->get('Incidents')->find('all', ['conditions' => ['report_id = ' . $incident->report_id]]);
        $result = $result->hydrate(false)->toArray();
        $this->assertEquals(2, count($result));

        // Case 3.1: Long PHP report submission
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReport = json_decode($bugReport, true);

        // Forcefully inflate the report by inflating $bugReport['errors']
        for ($i = 0; $i < 2000; $i++) {
            $bugReport['errors'][] = $bugReport['errors'][0];
        }
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(40, count($result['incidents']));
        // No new report added (closest report found)
        $this->assertEquals(0, count($result['reports']));

        // Case 3.2: Long JS report submission
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        // Forcefully inflate the report by inflating $bugReport['exception']['stack']
        for ($i = 0; $i < 1500; $i++) {
            $bugReport['exception']['stack'][] = $bugReport['exception']['stack'][0];
        }
        $result = $this->Incidents->createIncidentFromBugReport($bugReport);
        $this->assertEquals(1, count($result['incidents']));
        // No new report added (closest report found)
        $this->assertEquals(0, count($result['reports']));

        // Case 3.3: Long error_message in PHP report submission
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReport = json_decode($bugReport, true);

        // Forcefully inflate error message
        for ($i = 0; $i < 6; $i++) {
            $bugReport['errors'][0]['msg'] .= $bugReport['errors'][0]['msg'];
        }

        $result = $this->Incidents->createIncidentFromBugReport($bugReport);

        $this->assertEquals(true, in_array(false, $result['incidents']));

        // Case 3.4: Long error_message in JS report submission
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReport = json_decode($bugReport, true);

        // Forcefully inflate error message
        for ($i = 0; $i < 8; $i++) {
            $bugReport['exception']['message'] .= $bugReport['exception']['message'];
        }

        $result = $this->Incidents->createIncidentFromBugReport($bugReport);

        $this->assertEquals(true, in_array(false, $result['incidents']));
    }

    /**
     * @dataProvider versionsStripping
     * @param string $version  The version
     * @param string $expected The expected version
     */
    public function testStripversion(string $version, string $expected): void
    {
        $this->assertEquals($expected, $this->Incidents->getStrippedPmaVersion($version));
    }

    public function versionsStripping(): array
    {
        return [
            [
                '1.2.3',
                '1.2.3',
            ],
            [
                '1.2.3-rc1',
                '1.2.3-rc1',
            ],
            [
                '4.1-dev',
                '4.1-dev',
            ],
            [
                '4.1.6deb0ubuntu1ppa1',
                '4.1.6',
            ],
            [
                '4.2.3deb1.trusty~ppa.1',
                '4.2.3',
            ],
            [
                '4.2.9deb0.1',
                '4.2.9',
            ],
            [
                '4.5.4.1deb2ubuntu2',
                '4.5.4.1',
            ],
        ];
    }
}
