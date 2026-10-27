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

namespace App\Test\TestCase\Controller;

use App\Model\Table\IncidentsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function file_get_contents;
use function json_decode;
use function json_encode;

use const DS;

/**
 * The incidents are ingested from an unauthenticated endpoint and printed in
 * the developer pages, the payload of a report must not be printed as HTML.
 */
class IncidentsXssTest extends TestCase
{
    use IntegrationTestTrait;

    protected IncidentsTable $Incidents;

    /**
     * Payload that breaks out of a text node.
     */
    protected string $payload = '<script>alert("xss")</script>';

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
        $this->session(['Developer.id' => 1, 'read_only' => false]);
    }

    /**
     * Ingest a report through the public endpoint with the payload in every
     * field that is printed by the incident page.
     */
    protected function ingestReportWithPayload(): void
    {
        $bugReport = json_decode(
            file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json'),
            true,
        );

        // error_name and error_message are read from the exception block.
        $bugReport['exception']['name'] = $this->payload;
        $bugReport['exception']['message'] = $this->payload;
        $bugReport['exception']['useragent'] = $this->payload;
        $bugReport['steps'] = $this->payload;
        $bugReport['script_name'] = $this->payload;
        $bugReport['user_os'] = $this->payload;
        $bugReport['server_software'] = $this->payload;
        $bugReport['locale'] = $this->payload;

        $this->configRequest(['input' => json_encode($bugReport)]);
        $this->post('/incidents/create');
        $this->assertResponseCode(200);
        $this->assertSame(['success' => true], json_decode((string) $this->_response->getBody(), true));
    }

    /**
     * The incident page prints the values of the report, a script tag in one of
     * them must be escaped.
     */
    public function testTheIncidentPageEscapesTheReportValues(): void
    {
        $this->ingestReportWithPayload();

        $incident = $this->Incidents->find('all', order: ['Incidents.id desc'])->firstOrFail();

        $this->get('/incidents/view/' . $incident->get('id'));
        $this->assertResponseCode(200);

        $body = (string) $this->_response->getBody();
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $body);
        $this->assertStringContainsString(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $body,
        );
    }

    /**
     * The description of the report is printed in a <pre> block, it must be
     * escaped as well.
     */
    public function testTheDescriptionIsEscaped(): void
    {
        $this->ingestReportWithPayload();

        $incident = $this->Incidents->find('all', order: ['Incidents.id desc'])->firstOrFail();

        $this->get('/incidents/view/' . $incident->get('id'));
        $body = (string) $this->_response->getBody();

        $this->assertStringNotContainsString('<pre>' . "\n" . '        <script>', $body);
    }
}
