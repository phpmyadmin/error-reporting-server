<?php

namespace App\Test\TestCase\Controller;

use App\Controller\IncidentsController;
use App\Model\Table\IncidentsTable;
use App\Model\Table\ReportsTable;
use App\Report;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

use function count;
use function file_get_contents;
use function json_decode;
use function str_repeat;

use const DS;
use const INF;

class IncidentsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use EmailTrait;

    protected IncidentsTable $Incidents;
    protected ReportsTable $Reports;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
            ReportsFixture::class,
            IncidentsFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->Incidents = TableRegistry::getTableLocator()->get('Incidents');
        //$Session = new SessionComponent(new ComponentRegistry());
        $this->session(['Developer.id' => 1, 'read_only' => false]);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $this->setupTransports();
    }

    public function testView(): void
    {
        $this->get('/incidents/view/1');
        $this->assertResponseCode(200);

        $this->assertNotEmpty($this->viewVariable('incident'));
        $this->assertIsArray(
            $this->viewVariable('incident')['stacktrace'],
        );
        $this->assertIsArray(
            $this->viewVariable('incident')['full_report'],
        );
    }

    public function testJson(): void
    {
        $this->get('/incidents/json/1');
        $this->assertResponseCode(200);
        $incident = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($incident, 'The incident should be an array !');

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

        $this->assertResponseCode(200);
        $incident = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($incident, 'The incident should be an array !');

        $expected = ['success' => true];
        $this->assertEquals($expected, $incident);

        $report = $this->Reports->find(
            'all',
            order: ['Reports.created desc'],
        )->first();
        $this->assertNotNull($report, 'The report should be created !');

        $subject = 'A new report has been submitted '
            . 'on the Error Reporting Server: '
            . $report['id'];
        $this->assertEquals('5.2.4', $report['pma_version']);

        //FIXME: test email sending
        // Test notification email
        //$emailContent = Configure::read('test_transport_email');

        //$this->assertEquals(Configure::read('NotificationEmailsFrom'), $emailContent['headers']['From']);
        //$this->assertEquals(Configure::read('NotificationEmailsTo'), $emailContent['headers']['To']);
        //$this->assertEquals($subject, $emailContent['headers']['Subject']);

        //Configure::write('test_transport_email', null);

        $this->post('/incidents/create');
        $this->assertResponseCode(200);
        $result = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($result);
        $this->assertSame(true, $result['success']);

        $report = $this->Reports->find(
            'all',
            order: ['Reports.created desc'],
        )->all()->first();
        $incidents = $this->Reports->getIncidents($report['id']);
        $incidents = $incidents->enableHydration(false)->toArray();
        $this->assertEquals(2, count($incidents));
        $this->assertEquals(
            $bugReportDecoded['exception']['message'],
            $report['error_message'],
        );
        $this->assertEquals(
            $bugReportDecoded['exception']['name'],
            $report['error_name'],
        );
        $this->assertEquals(
            IncidentsTable::getStrippedPmaVersion($bugReportDecoded['pma_version']),
            $report['pma_version'],
        );
    }

    public function testCreateInvalidEmptyReport(): void
    {
        $this->configRequest(['input' => '']);
        $this->post('/incidents/create');
        $this->assertResponseCode(200);
        $result = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($result);
        $this->assertSame(false, $result['success']);
    }

    /**
     * A JSON scalar is not a report: it must be refused, not reach the typed
     * parameter of createIncidentFromBugReport().
     *
     * @param string $body A body that decodes to something else than an object
     */
    #[DataProvider('nonObjectBodyProvider')]
    public function testCreateRejectsABodyThatIsNotAnObject(string $body): void
    {
        $reportsBefore = $this->Reports->find()->count();

        $this->configRequest(['input' => $body]);
        $this->post('/incidents/create');

        $this->assertResponseCode(200);
        $this->assertSame(
            ['success' => false],
            json_decode($this->_response->getBody(), true),
        );
        $this->assertSame($reportsBefore, $this->Reports->find()->count());
    }

    /** @return array<string, array{string}> */
    public static function nonObjectBodyProvider(): array
    {
        return [
            'integer' => ['1'],
            'zero' => ['0'],
            'string' => ['"x"'],
            'true' => ['true'],
            'empty array' => ['[]'],
            'empty body' => [''],
        ];
    }

    public function testCreateRejectsAnOversizedBody(): void
    {
        $reportsBefore = $this->Reports->find()->count();
        $incidentsBefore = $this->Incidents->find()->count();

        $this->configRequest(['input' => str_repeat('a', 1048577)]);
        $this->post('/incidents/create');

        $this->assertResponseCode(413);
        $this->assertSame(
            ['success' => false],
            json_decode($this->_response->getBody(), true),
        );
        $this->assertSame($reportsBefore, $this->Reports->find()->count());
        $this->assertSame($incidentsBefore, $this->Incidents->find()->count());
        $this->assertNoMailSent();
    }

    public function testCreateAcceptsABodyAtTheLimit(): void
    {
        // A body of exactly the limit is still parsed, and rejected for its
        // content, not for its size.
        $this->configRequest(['input' => str_repeat('a', 1048576)]);
        $this->post('/incidents/create');

        $this->assertResponseCode(200);
        $this->assertSame(
            ['success' => false],
            json_decode($this->_response->getBody(), true),
        );
    }

    public function testCreateIncidentFromBugReportUANull(): void
    {
        // Test invalid Notification email configuration
        Configure::write('NotificationEmailsTo', '');

        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_ua_null_js.json');
        $bugReportDecoded = json_decode($bugReport, true);
        $this->configRequest(['input' => $bugReport]);
        $this->post('/incidents/create');
        $this->assertResponseCode(200);
        $result = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($result);
        $this->assertSame(true, $result['success']);

        $report = $this->Reports->find(
            'all',
            order: ['Reports.created desc'],
        )->all()->first();
        $subject = 'A new report has been submitted '
            . 'on the Error Reporting Server: '
            . $report['id'];
        $this->assertEquals('5.2.4-dev', $report['pma_version']);

        $this->assertNoMailSent();
    }

    public function testCreateInvalidReport(): void
    {
        // Test invalid Notification email configuration
        Configure::write('NotificationEmailsTo', '');

        $bugReport = file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json');
        $bugReportDecoded = json_decode($bugReport, true);
        $this->configRequest(['input' => $bugReport]);
        $this->post('/incidents/create');
        $this->assertResponseCode(200);
        $result = json_decode($this->_response->getBody(), true);
        $this->assertNotNull($result);
        $this->assertSame(true, $result['success']);

        $report = $this->Reports->find(
            'all',
            order: ['Reports.created desc'],
        )->all()->first();
        $subject = 'A new report has been submitted '
            . 'on the Error Reporting Server: '
            . $report['id'];
        $this->assertEquals('5.2.4', $report['pma_version']);

        $this->assertNoMailSent();
    }

    /**
     * The report that is forwarded must carry the data of the crash.
     *
     * Built with fromObject() from a payload decoded to arrays, every message,
     * culprit and frame came out empty: fromObject() copies the top level only.
     */
    public function testTheReportThatIsForwardedCarriesItsData(): void
    {
        $decoded = (array) json_decode(
            (string) file_get_contents(TESTS . 'Fixture' . DS . 'report_php.json'),
            true,
        );

        $controller = new IncidentsController(new ServerRequest());
        $method = new ReflectionMethod($controller, 'buildReportForForwarding');
        $method->setAccessible(true);
        $report = $method->invoke($controller, IncidentsTable::capReport($decoded));

        $this->assertInstanceOf(Report::class, $report);
        $events = $report->getReports();
        $this->assertNotSame([], $events);
        $this->assertNotSame('', $events[0]['message'], 'the message must not be empty');
        $this->assertNotSame('', $events[0]['culprit'], 'the culprit must not be empty');
        $this->assertNotSame(
            [],
            $events[0]['exception']['values'][0]->stacktrace->frames,
            'the frames must not be empty',
        );
    }

    /**
     * A payload that cannot be encoded is not forwarded at all, rather than
     * forwarded as one empty event.
     */
    public function testAReportThatCannotBeEncodedIsNotForwarded(): void
    {
        $controller = new IncidentsController(new ServerRequest());
        $method = new ReflectionMethod($controller, 'buildReportForForwarding');
        $method->setAccessible(true);

        // INF cannot be encoded by json_encode(), and a client can send it.
        $this->assertNull($method->invoke($controller, ['value' => INF]));
    }
}
