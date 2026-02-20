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

use App\Model\Table\ReportsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use phpmock\phpunit\PHPMock;

use function file_get_contents;
use function json_decode;
use function json_encode;

use const DS;

/**
 * App\Controller\GithubController Test Case
 */
class GithubControllerTest extends TestCase
{
    use PHPMock;
    use IntegrationTestTrait;
    use HttpClientTrait;

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
        $this->session(['Developer.id' => 1, 'access_token' => 'abc', 'read_only' => false]);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $this->enableCsrfToken();
    }

    /**
     * Test create_issue method
     */
    public function testCreateIssue(): void
    {
        $repoPath = 'phpmyadmin/phpmyadmin';

        // Case 1. Test with an invalid reportId
        $this->get('github/create_issue/123');
        $this->assertResponseError();
        $this->assertResponseContains('The report does not exist.');

        // Case 2. Test form with valid reportId
        $this->get('github/create_issue/5');
        $this->assertResponseCode(200);
        $this->assertResponseContains('Lorem ipsum dolor sit amet'); // Description

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues',
            $this->newClientResponse(403, [], json_encode(['message' => 'Unauthorised'])),
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
        $this->assertResponseCode(200);
        $this->cleanupMockResponses();

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->assertSession(
            'Unauthorised access to Github. github credentials may be out of date.'
            . ' Please check and try again later.',
            'Flash.flash.0.message'
        );

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues',
            $this->newClientResponse(201, [], $issueResponse),
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
        $this->assertResponseCode(302);

        $report = $this->Reports->get(5);
        $this->assertEquals(1347, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
    }

    /**
     * Test link_issue method
     */
    public function testLinkIssue(): void
    {
        $repoPath = 'phpmyadmin/phpmyadmin';

        // Case 1.1 Test with an invalid reportId
        $this->get('github/link_issue/123?ticket_id=1');
        $this->assertResponseCode(404);
        $this->assertResponseContains('The report does not exist.');

        // Case 1.2 Test with an invalid ticketId
        $this->get('github/link_issue/5?ticket_id=');
        $this->assertResponseCode(404);
        $this->assertResponseContains('Invalid Ticket ID');

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/9999999/comments',
            $this->newClientResponse(404, [], json_encode(['message' => 'Not found'])),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/9999999',
            $this->newClientResponse(404, [], json_encode(['message' => 'Not found'])),
        );

        // Case 2. Test submission of form with unsuccessful github response
        $this->get(
            'github/link_issue/5?ticket_id=9999999'
        );
        $this->assertResponseCode(302);

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->assertSession(
            'Bug Issue not found on Github. Are you sure the issue number is correct? '
            . 'Please check and try again!',
            'Flash.flash.0.message'
        );
        $this->cleanupMockResponses();

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387/comments',
            $this->newClientResponse(201, [], json_encode([])),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387',
            $this->newClientResponse(200, [], $issueResponse),
        );
        // Case 3. Test submission of form with successful Github response (with issue open)
        $this->get(
            'github/link_issue/5?ticket_id=1387'
        );
        $this->assertResponseCode(302);

        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
        $this->cleanupMockResponses();

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387/comments',
            $this->newClientResponse(201, [], json_encode([])),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387',
            $this->newClientResponse(200, [], $issueResponseWithClosed),
        );
        // Case 4. Test submission of form with successful Github response (with issue closed)
        $this->get(
            'github/link_issue/5?ticket_id=1387'
        );
        $this->assertResponseCode(302);

        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('resolved', $report->status);
        $this->cleanupMockResponses();
    }

    /**
     * Test unlink_issue method
     */
    public function testUnlinkIssue(): void
    {
        $repoPath = 'phpmyadmin/phpmyadmin';

        // Case 1.1 Test with an invalid reportId
        $this->get('github/unlink_issue/123');
        $this->assertResponseCode(404);
        $this->assertResponseContains('The report does not exist.');

        // Case 1.2 Test unlinked with an already unlinked issue
        $this->get('github/unlink_issue/5');
        $this->assertResponseCode(404);
        $this->assertResponseContains('Invalid Ticket ID');

        $commentResponse = file_get_contents(TESTS . 'Fixture' . DS . 'comment_response.json');

        // Link the report before trying to unlink
        $report = $this->Reports->get(5);
        $report->sourceforge_bug_id = 1387;
        $report->status = 'forwarded';
        $this->Reports->save($report);

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387/comments',
            $this->newClientResponse(401, [], json_encode(['message' => 'Unauthorised access'])),
        );
        // Case 2. Test submission of form with unsuccessful and unexpected github response
        $this->get(
            'github/unlink_issue/5'
        );
        $this->assertResponseCode(302);

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
        $this->assertSession(
            'Unhandled response code received: 401',
            'Flash.flash.0.message'
        );
        $this->cleanupMockResponses();

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387/comments',
            $this->newClientResponse(201, [], $commentResponse),
        );
        // Case 3. Test submission of form with successful Github response
        $this->get(
            'github/unlink_issue/5'
        );
        $this->assertResponseCode(302);

        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->cleanupMockResponses();
    }

    /**
     * Test sync_issue_status method
     */
    public function testSyncIssueStatus(): void
    {
        $repoPath = 'phpmyadmin/phpmyadmin';

        // Test via web interface
        Configure::write('CronDispatcher', false);
        $this->enableRetainFlashMessages();
        $this->get('github/sync_issue_status');
        $this->assertResponseCode(302);
        $this->assertRedirect('/');

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/1',
            $this->newClientResponse(401, [], json_encode(['message' => 'Unauthorised access'])),
        );

        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/2',
            $this->newClientResponse(200, [], $issueResponse),
        );

        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/4',
            $this->newClientResponse(200, [], $issueResponseWithClosed),
        );

        // Test via cli (i.e. the CronDispatcher setting should be defined)
        Configure::write('CronDispatcher', true);
        $this->get('github/sync_issue_status');
        $this->assertResponseCode(200);

        // 401
        $report = $this->Reports->get(1);
        $this->assertEquals('forwarded', $report->status);
        $this->assertEquals(1, $report->sourceforge_bug_id);

        // 200 (open state)
        $report = $this->Reports->get(2);
        $this->assertEquals('forwarded', $report->status);
        $this->assertEquals(2, $report->sourceforge_bug_id);

        // 200 (closed state)
        $report = $this->Reports->get(4);
        $this->assertEquals('resolved', $report->status);
        $this->assertEquals(4, $report->sourceforge_bug_id);
    }
}
