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

namespace App\Test\TestCase\Security;

use App\Mailer\Transport\TestTransport;
use App\Model\Table\IncidentsTable;
use App\Model\Table\ReportsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\Mailer\TransportFactory;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function Cake\Core\h;
use function file_get_contents;
use function json_decode;
use function json_encode;

use const DS;

/**
 * Every field of a report is written by an unauthenticated client and is
 * printed back in the developer pages and in the notification mail.
 */
class ReportOutputEscapingTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Payload that runs when it is written as HTML, either by the renderer of a
     * page or by the innerHTML that DataTables uses for a cell.
     */
    protected const PAYLOAD = '<img src=x onerror=alert(1)>';

    protected ReportsTable $Reports;

    protected IncidentsTable $Incidents;

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

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $this->Incidents = TableRegistry::getTableLocator()->get('Incidents');
        $this->session(['Developer.id' => 1, 'read_only' => false]);
    }

    /**
     * Store the payload in every field a client controls.
     */
    protected function storeHostileReport(): int
    {
        $report = $this->Reports->get(1);
        $report->patch([
            'error_name' => self::PAYLOAD,
            'error_message' => self::PAYLOAD,
            'location' => self::PAYLOAD,
            'pma_version' => self::PAYLOAD,
        ]);
        $this->assertNotFalse($this->Reports->save($report));

        $incident = $this->Incidents->find('all', order: ['Incidents.id desc'])->firstOrFail();
        $incident->patch([
            'report_id' => $report->get('id'),
            'browser' => self::PAYLOAD,
            'server_software' => self::PAYLOAD,
            'user_os' => self::PAYLOAD,
            'script_name' => self::PAYLOAD,
        ]);
        $this->assertNotFalse($this->Incidents->save($incident));

        return (int) $report->get('id');
    }

    protected function assertBodyIsEscaped(string $what): void
    {
        $body = (string) $this->_response->getBody();

        // The value has to be there, escaped: asserting that the payload is
        // absent would also pass on a page that renders nothing at all.
        $this->assertStringContainsString(
            h(self::PAYLOAD),
            $body,
            $what . ' does not print the values of the report.',
        );
        $this->assertStringNotContainsString(
            self::PAYLOAD,
            $body,
            $what . ' prints the payload of a report as HTML.',
        );
    }

    /**
     * The reports and the notifications tables read their rows as JSON and
     * DataTables writes every cell but the first with innerHTML, so the values
     * have to be escaped before they are sent.
     */
    public function testTheReportsDataTableEscapesTheRows(): void
    {
        $this->storeHostileReport();

        $this->get('/reports/data_tables?sEcho=1&iDisplayLength=25');

        $this->assertResponseOk();
        $this->assertBodyIsEscaped('The reports table');
    }

    public function testTheNotificationsDataTableEscapesTheRows(): void
    {
        $this->storeHostileReport();

        $this->get('/notifications/data_tables?sEcho=1&iDisplayLength=25');

        $this->assertResponseOk();
        $this->assertBodyIsEscaped('The notifications table');
    }

    /**
     * The filter selects of the reports page are filled with the distinct
     * values of the table.
     */
    public function testTheReportsIndexEscapesTheFilterLists(): void
    {
        $this->storeHostileReport();

        $this->get('/reports');

        $this->assertResponseOk();
        $this->assertBodyIsEscaped('The reports page');
    }

    /**
     * The report page prints the report, its incidents and the tag widgets that
     * ReportsHelper::entriesFromIncidents() builds.
     */
    public function testTheReportPageEscapesTheReportAndTheTags(): void
    {
        $id = $this->storeHostileReport();

        $this->get('/reports/view/' . $id);

        $this->assertResponseOk();
        $this->assertBodyIsEscaped('The report page');
    }

    /**
     * The incident page prints the browser, the operating system and the
     * software of the server that sent the report.
     */
    public function testTheIncidentPageEscapesTheClientFields(): void
    {
        $this->storeHostileReport();

        // A marker of its own: the payload of the report is printed by the
        // same page, so a shared value would hide a field that is not printed.
        $marker = '<img src=x onerror=alert(2)>';
        $incident = $this->Incidents->find('all', order: ['Incidents.id desc'])->firstOrFail();
        $incident->patch(['browser' => $marker]);
        $this->assertNotFalse($this->Incidents->save($incident));

        $this->get('/incidents/view/' . $incident->get('id'));
        $body = (string) $this->_response->getBody();

        $this->assertResponseOk();
        $this->assertStringContainsString(
            h($marker),
            $body,
            'The incident page does not print the browser of the incident.',
        );
        $this->assertStringNotContainsString(
            $marker,
            $body,
            'The incident page prints the browser of the incident as HTML.',
        );
    }

    /**
     * The notification mail is an HTML document built from the same fields.
     * Send a report through the public endpoint to render the real template,
     * with the extra values the controller computes.
     */
    public function testTheNotificationMailEscapesTheReport(): void
    {
        TransportFactory::setConfig('test_transport', ['className' => TestTransport::class]);
        Configure::write('NotificationEmailsTransport', 'test_transport');
        Configure::write('NotificationEmailsTo', 'reports@example.invalid');
        Configure::write('NotificationEmailsFrom', 'reports@example.invalid');
        Configure::delete('test_transport_email');

        $bugReport = json_decode(
            file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json'),
            true,
        );
        $bugReport['exception']['name'] = self::PAYLOAD;
        $bugReport['exception']['message'] = self::PAYLOAD;
        $bugReport['exception']['useragent'] = self::PAYLOAD;

        $this->configRequest(['input' => json_encode($bugReport)]);
        $this->post('/incidents/create');
        $this->assertResponseOk();

        $mail = Configure::read('test_transport_email');
        $this->assertIsArray($mail, 'The notification mail was not sent.');
        $this->assertStringNotContainsString(
            self::PAYLOAD,
            $mail['message'],
            'The notification mail prints the payload of a report as HTML.',
        );
    }
}
