<?php

/**
 * Tests for Github Controller actions
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

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestCase;
use phpmock\phpunit\PHPMock;
use const DS;
use function file_get_contents;
use function json_decode;
use function json_encode;

/**
 * App\Controller\GithubController Test Case
 */
class GithubControllerTest extends IntegrationTestCase
{
    use PHPMock;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = [
        'app.Reports',
        'app.Developers',
        'app.Incidents',
        'app.Notifications',
    ];

    public function setUp(): void
    {
        $this->session(['Developer.id' => 1, 'access_token' => 'abc']);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    /**
     * Test create_issue method
     */
    public function testCreateIssue(): void
    {
        // Mock functions `curl_exec` and `curl_getinfo` in GithubApiComponent
        // so that we don't actually hit the Github Api
        $curlExecMock = $this->getFunctionMock('\App\Controller\Component', 'curl_exec');
        $curlGetInfoMock = $this->getFunctionMock('\App\Controller\Component', 'curl_getinfo');

        // Case 1. Test with an invalid reportId
        $this->get('github/create_issue/123');
        $this->assertResponseContains('Invalid report');

        // Case 2. Test form with valid reportId
        $this->get('github/create_issue/5');
        $this->assertResponseContains('Lorem ipsum dolor sit amet'); // Description

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');

        // Github unsuccessful response followed by successful response
        $curlExecMock->expects($this->exactly(2))->willReturnOnConsecutiveCalls(
            $issueResponse,
            $issueResponse
        );
        $curlGetInfoMock->expects($this->exactly(2))->willReturnOnConsecutiveCalls(
            403,
            201
        );

        // Case 3. Test submission of form with unsuccessful github response
        $this->post(
            'github/create_issue/5',
            [
                'summary' => 'Error testing',
                'milestone' => '3.8',
                'description' => 'Lorem ipsum dolor sit amet',
                'labels' => 'test-pma',
            ]
        );

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->assertSession(
            'Unauthorised access to Github. github credentials may be out of date.'
            . ' Please check and try again later.',
            'Flash.flash.0.message'
        );

        // Case 4. Test submission of form with successful Github response
        $this->post(
            'github/create_issue/5',
            [
                'summary' => 'Error testing',
                'milestone' => '3.8',
                'description' => 'Lorem ipsum dolor sit amet',
                'labels' => 'test-pma',
            ]
        );

        $report = $this->Reports->get(5);
        $this->assertEquals(1347, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
    }

    /**
     * Test link_issue method
     */
    public function testLinkIssue(): void
    {
        // Mock functions `curl_exec` and `curl_getinfo` in GithubApiComponent
        // so that we don't actually hit the Github Api
        $curlExecMock = $this->getFunctionMock('\App\Controller\Component', 'curl_exec');
        $curlGetInfoMock = $this->getFunctionMock('\App\Controller\Component', 'curl_getinfo');

        // Case 1.1 Test with an invalid reportId
        $this->get('github/link_issue/123?ticket_id=1');
        $this->assertResponseContains('Invalid report');

        // Case 1.2 Test with an invalid ticketId
        $this->get('github/link_issue/5?ticket_id=');
        $this->assertResponseContains('Invalid Ticket ID');

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        // Github response unsuccessful followed by successful (open) and successful (closed)
        $curlExecMock->expects($this->exactly(5))->willReturnOnConsecutiveCalls(
            $issueResponse,
            $issueResponse,
            $issueResponse,
            $issueResponseWithClosed,
            $issueResponseWithClosed
        );
        $curlGetInfoMock->expects($this->exactly(5))->willReturnOnConsecutiveCalls(
            404,
            201,
            200,
            201,
            200
        );

        // Case 2. Test submission of form with unsuccessful github response
        $this->get(
            'github/link_issue/5?ticket_id=9999999'
        );

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->assertSession(
            'Bug Issue not found on Github. Are you sure the issue number is correct? '
            . 'Please check and try again!',
            'Flash.flash.0.message'
        );

        // Case 3. Test submission of form with successful Github response (with issue open)
        $this->get(
            'github/link_issue/5?ticket_id=1387'
        );

        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);

        // Case 4. Test submission of form with successful Github response (with issue closed)
        $this->get(
            'github/link_issue/5?ticket_id=1387'
        );

        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('resolved', $report->status);
    }

    /**
     * Test unlink_issue method
     */
    public function testUnlinkIssue(): void
    {
        // Mock functions `curl_exec` and `curl_getinfo` in GithubApiComponent
        // so that we don't actually hit the Github Api
        $curlExecMock = $this->getFunctionMock('\App\Controller\Component', 'curl_exec');
        $curlGetInfoMock = $this->getFunctionMock('\App\Controller\Component', 'curl_getinfo');

        // Case 1.1 Test with an invalid reportId
        $this->get('github/unlink_issue/123');
        $this->assertResponseContains('Invalid report');

        // Case 1.2 Test unlinked with an already unlinked issue
        $this->get('github/unlink_issue/5');
        $this->assertResponseContains('Invalid Ticket ID');

        $commentResponse = file_get_contents(TESTS . 'Fixture' . DS . 'comment_response.json');

        // Github response unsuccessful followed by successful
        $curlExecMock->expects($this->exactly(2))->willReturnOnConsecutiveCalls(
            json_encode(['message' => 'Unauthorised access']),
            $commentResponse
        );
        $curlGetInfoMock->expects($this->exactly(2))->willReturnOnConsecutiveCalls(
            401,
            201
        );

        // Link the report before trying to unlink
        $report = $this->Reports->get(5);
        $report->sourceforge_bug_id = 1387;
        $report->status = 'forwarded';
        $this->Reports->save($report);

        // Case 2. Test submission of form with unsuccessful and unexpected github response
        $this->get(
            'github/unlink_issue/5'
        );

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
        $this->assertSession(
            'Unhandled response code received: 401',
            'Flash.flash.0.message'
        );

        // Case 3. Test submission of form with successful Github response
        $this->get(
            'github/unlink_issue/5'
        );

        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
    }

    /**
     * Test sync_issue_status method
     */
    public function testSyncIssueStatus(): void
    {
        // Mock functions `curl_exec` and `curl_getinfo` in GithubApiComponent
        // so that we don't actually hit the Github Api
        $curlExecMock = $this->getFunctionMock('\App\Controller\Component', 'curl_exec');
        $curlGetInfoMock = $this->getFunctionMock('\App\Controller\Component', 'curl_getinfo');

        // Test via web interface
        Configure::write('CronDispatcher', false);
        $this->enableRetainFlashMessages();
        $this->get('github/sync_issue_status');
        $this->assertRedirect('/');

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        // Github response unsuccessful followed by two successful responses
        $curlExecMock->expects($this->exactly(3))->willReturnOnConsecutiveCalls(
            json_encode(['message' => 'Unauthorised access']),
            $issueResponse,
            $issueResponseWithClosed
        );
        $curlGetInfoMock->expects($this->exactly(3))->willReturnOnConsecutiveCalls(
            401,
            200,
            200
        );

        // Test via cli (i.e. the CronDispatcher setting should be defined)
        Configure::write('CronDispatcher', true);
        $this->get('github/sync_issue_status');

        // 401
        $report = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report->status);

        // 200 (open state)
        $report = $this->Reports->get(2);
        $this->assertEquals('forwarded', $report->status);

        // 200 (closed state)
        $report = $this->Reports->get(4);
        $this->assertEquals('resolved', $report->status);
    }
}
