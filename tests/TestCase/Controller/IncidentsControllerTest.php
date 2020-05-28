<?php

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use const DS;
use function count;
use function file_get_contents;
use function json_decode;

class IncidentsControllerTest extends IntegrationTestCase
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
        $this->Incidents = TableRegistry::getTableLocator()->get('Incidents');
        //$Session = new SessionComponent(new ComponentRegistry());
        $this->session(['Developer.id' => 1]);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    public function testView(): void
    {
        $this->get('/incidents/view/1');

        $this->assertNotEmpty($this->viewVariable('incident'));
        $this->assertInternalType(
            'array',
            $this->viewVariable('incident')['stacktrace']
        );
        $this->assertInternalType(
            'array',
            $this->viewVariable('incident')['full_report']
        );
    }

    public function testJson(): void
    {
        $this->get('/incidents/json/1');
        $incident = json_decode($this->_response->body(), true);
        $expected = [
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
            'stacktrace' => [['context' => ['test']]],
            'full_report' => [
                'pma_version' => '',
                'php_version' => '',
                'browser_name' => '',
                'browser_version' => '',
                'user_agent_string' => '',
                'server_software' => '',
                'locale' => '',
                'exception' => ['uri' => ''],
                'configuration_storage' => '',
                'microhistory' => '',
            ],
            'report_id' => 1,
            'created' => '2013-08-29T18:10:01+00:00',
            'modified' => '2013-08-29T18:10:01+00:00',
            'exception_type' => null,
        ];

        $this->assertEquals($expected, $incident);
    }

    public function testCreate(): void
    {
        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $bugReportDecoded = json_decode($bugReport, true);
        $this->configRequest(['input' => $bugReport]);
        $this->post('/incidents/create');

        $report = $this->Reports->find(
            'all',
            ['order' => 'Reports.created desc']
        )->all()->first();
        $subject = 'A new report has been submitted '
            . 'on the Error Reporting Server: '
            . $report['id'];
        $this->assertEquals('4.5.4.1', $report['pma_version']);

        //FIXME: test email sending
        // Test notification email
        //$emailContent = Configure::read('test_transport_email');

        //$this->assertEquals(Configure::read('NotificationEmailsFrom'), $emailContent['headers']['From']);
        //$this->assertEquals(Configure::read('NotificationEmailsTo'), $emailContent['headers']['To']);
        //$this->assertEquals($subject, $emailContent['headers']['Subject']);

        //Configure::write('test_transport_email', null);

        $this->post('/incidents/create');

        $report = $this->Reports->find(
            'all',
            ['order' => 'Reports.created desc']
        )->all()->first();
        $this->Reports->id = $report['id'];
        $incidents = $this->Reports->getIncidents();
        $incidents = $incidents->hydrate(false)->toArray();
        $this->assertEquals(2, count($incidents));
        $this->assertEquals(
            $bugReportDecoded['exception']['message'],
            $report['error_message']
        );
        $this->assertEquals(
            $bugReportDecoded['exception']['name'],
            $report['error_name']
        );
        $this->assertEquals(
            $this->Incidents->getStrippedPmaVersion($bugReportDecoded['pma_version']),
            $report['pma_version']
        );

        $this->configRequest(['input' => '']);
        $this->post('/incidents/create');
        $result = json_decode($this->_response->body(), true);
        $this->assertEquals(false, $result['success']);

        // Test invalid Notification email configuration
        Configure::write('NotificationEmailsTo', '');

        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReportDecoded = json_decode($bugReport, true);
        $this->configRequest(['input' => $bugReport]);
        $this->post('/incidents/create');

        $report = $this->Reports->find(
            'all',
            ['order' => 'Reports.created desc']
        )->all()->first();
        $subject = 'A new report has been submitted '
            . 'on the Error Reporting Server: '
            . $report['id'];
        $this->assertEquals('4.5.4.1', $report['pma_version']);

        $emailContent = Configure::read('test_transport_email');

        // Since no email sent
        $this->assertEquals(null, $emailContent);
    }
}
