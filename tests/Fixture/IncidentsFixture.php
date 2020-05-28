<?php
/**
 * IncidentFixture.
 */

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class IncidentsFixture extends TestFixture
{
    /**
     * Imports.
     *
     * @var array
     */
    public $import = ['table' => 'incidents'];

    /**
     * Records.
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'error_name' => 'Lorem ipsum dolor sit amet',
            'error_message' => 'Lorem ipsum dolor sit amet',
            'pma_version' => 'Lorem ipsum dolor sit amet',
            'php_version' => '5.5',
            'browser' => 'Lorem ipsum dolor sit amet',
            'user_os' => 'Lorem ipsum dolor sit amet',
            'locale' => 'Lorem ipsum dolor sit amet',
            'server_software' => 'Lorem ipsum dolor sit amet',
            'stackhash' => 'hash1',
            'configuration_storage' => 'Lorem ipsum dolor sit amet',
            'script_name' => 'Lorem ipsum dolor sit amet',
            'steps' => 'Lorem ipsum dolor sit amet',
            'stacktrace' => '[{"context": ["test"]}]',
            'full_report' => '{"pma_version": "", "php_version": "","browser_name": ""
					, "browser_version": "", "user_agent_string": "", "server_software":
					"", "locale": "", "exception":{"uri":""}, "configuration_storage":"",
					"microhistory":""}',
            'report_id' => 1,
            'created' => '2013-08-29 18:10:01',
            'modified' => '2013-08-29 18:10:01',
        ],
        [
            'id' => 2,
            'error_name' => 'Lorem ipsum dolor sit amet',
            'error_message' => 'Lorem ipsum dolor sit amet',
            'pma_version' => 'Lorem ipsum dolor sit amet',
            'php_version' => '5.3',
            'browser' => 'Lorem ipsum dolor sit amet',
            'user_os' => 'Lorem ipsum dolor sit amet',
            'locale' => 'Lorem ipsum dolor sit amet',
            'server_software' => 'Lorem ipsum dolor sit amet',
            'stackhash' => 'hash4',
            'configuration_storage' => 'Lorem ipsum dolor sit amet',
            'script_name' => 'Lorem ipsum dolor sit amet',
            'steps' => 'Lorem ipsum dolor sit amet',
            'stacktrace' => '[{"context": ["test"]}]',
            'full_report' => '{"pma_version": "1.2"}',
            'report_id' => 4,
            'created' => '2013-08-29 18:10:01',
            'modified' => '2013-08-29 18:10:01',
        ],
        [
            'id' => 3,
            'error_name' => 'Lorem ipsum dolor sit amet',
            'error_message' => 'Lorem ipsum dolor sit amet',
            'pma_version' => 'Lorem ipsum dolor sit amet',
            'php_version' => '5.3',
            'browser' => 'Lorem ipsum dolor sit amet',
            'user_os' => 'Lorem ipsum dolor sit amet',
            'locale' => 'Lorem ipsum dolor sit amet',
            'server_software' => 'Lorem ipsum dolor sit amet',
            'stackhash' => 'hash4',
            'configuration_storage' => 'Lorem ipsum dolor sit amet',
            'script_name' => 'Lorem ipsum dolor sit amet',
            'steps' => null,
            'stacktrace' => '[{"context": ["test"]}]',
            'full_report' => '{"pma_version": "1.2"}',
            'report_id' => 4,
            'created' => '2013-08-29 18:10:00',
            'modified' => '2013-08-29 18:10:00',
        ],

        [
            'id' => 4,
            'error_name' => 'Lorem ipsum dolor sit amet',
            'error_message' => 'Lorem ipsum dolor sit amet',
            'pma_version' => 'Lorem ipsum dolor sit amet',
            'php_version' => '5.3',
            'browser' => 'Lorem ipsum dolor sit amet',
            'user_os' => 'Lorem ipsum dolor sit amet',
            'locale' => 'Lorem ipsum dolor sit amet',
            'server_software' => 'Lorem ipsum dolor sit amet',
            'stackhash' => 'hash3',
            'configuration_storage' => 'Lorem ipsum dolor sit amet',
            'script_name' => 'Lorem ipsum dolor sit amet',
            'steps' => null,
            'stacktrace' => '[{"context": ["test"]}]',
            'full_report' => '{"pma_version": "1.2"}',
            'report_id' => 2,
            'created' => '2013-08-29 18:10:00',
            'modified' => '2013-08-29 18:10:00',
        ],

        [
            'id' => 5,
            'error_name' => 'Lorem ipsum dolor sit amet',
            'error_message' => 'Lorem ipsum dolor sit amet',
            'pma_version' => 'Lorem ipsum dolor sit amet',
            'php_version' => '5.3',
            'browser' => 'Lorem ipsum dolor sit amet',
            'user_os' => 'Lorem ipsum dolor sit amet',
            'locale' => 'Lorem ipsum dolor sit amet',
            'server_software' => 'Lorem ipsum dolor sit amet',
            'stackhash' => 'hash3',
            'configuration_storage' => 'Lorem ipsum dolor sit amet',
            'script_name' => 'Lorem ipsum dolor sit amet',
            'steps' => null,
            'stacktrace' => '[{"context": ["test"]}]',
            'full_report' => '{"pma_version": "1.2"}',
            'report_id' => 5,
            'created' => '2013-08-29 18:10:00',
            'modified' => '2013-08-29 18:10:00',
        ],
    ];
}
