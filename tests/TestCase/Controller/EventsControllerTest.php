<?php

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;

class EventsControllerTest extends IntegrationTestCase
{
    public $fixtures = array('app.reports');

    public function setUp()
    {
        $this->Reports = TableRegistry::get('Reports');
    }

    public function testIndex()
    {

        /* Test case 1 */
        // Invalid User Agent
        $this->configRequest([
            'headers' => ['User-Agent' => 'Invalid-GitHub-Hookshot-abcdef']
        ]);
        $this->post('/events');
        $this->assertResponseCode(403);

        /* Test case 2 */
        // Invalid Event Type
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'anything-except-issues'
            ]
        ]);
        $this->post('/events');
        $this->assertResponseCode(400);

        /* Test case 3 */
        // Invalid Hash in headers
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=89db05030cf4c1fbcfb4d590deda30fa40a247ed'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'closed',
                    'issue' => array(
                        'number' => 4
                    )
                )
            )
        );
        $this->assertResponseCode(401);

        /* Test case 4 */
        // Invalid issues action
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'anything-invalid',
                    'issue' => array(
                        'number' => 4
                    )
                )
            )
        );
        $this->assertResponseCode(204);

        /* Test case 5 */
        // Event for an unlinked issue
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'closed',
                    'issue' => array(
                        'number' => 1234
                    )
                )
            )
        );
        $this->assertResponseCode(204);

        // Prepare for testcase
        $report = $this->Reports->get(4);
        $report->status = 'resolved';
        $this->Reports->save($report);

        /* Test case 6 */
        // Event 'opened' for a linked issue
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'opened',
                    'issue' => array(
                        'number' => 4
                    )
                )
            )
        );
        $this->assertResponseCode(201);
        $report = $this->Reports->get(4);
        $this->assertEquals($report->status, 'forwarded');


        /* Test case 7 */
        // Event 'closed' for a linked issue
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'closed',
                    'issue' => array(
                        'number' => 4
                    )
                )
            )
        );
        $this->assertResponseCode(201);
        $report = $this->Reports->get(4);
        $this->assertEquals($report->status, 'resolved');

        // Prepare for testcase
        $report = $this->Reports->get(4);
        $report->status = 'resolved';
        $this->Reports->save($report);

        /* Test case 8 */
        // Event 'reopened' for a linked issue
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'issues',
                'X-Hub-Signature' => 'sha1=ddcf5dadbbb716e43da25344989dde547c6c3a03'
            ]
        ]);
        $this->post(
            '/events',
            json_encode(
                array(
                    'action' => 'reopened',
                    'issue' => array(
                        'number' => 4
                    )
                )
            )
        );
        $this->assertResponseCode(201);
        $report = $this->Reports->get(4);
        $this->assertEquals($report->status, 'forwarded');
    }
}
