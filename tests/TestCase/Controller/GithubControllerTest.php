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

use App\Controller\GithubController;
use App\Model\Table\ReportsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use phpmock\phpunit\PHPMock;
use ReflectionMethod;

use function file_get_contents;
use function json_decode;
use function json_encode;
use function substr_count;

use const CONFIG;
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
            ],
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
            'Flash.flash.0.message',
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
            ],
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
        $this->post('github/link_issue/123', ['ticket_id' => 1]);
        $this->assertResponseCode(404);
        $this->assertResponseContains('The report does not exist.');

        // Case 1.2 Test with an invalid ticketId
        $this->post('github/link_issue/5', ['ticket_id' => '']);
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
        $this->post(
            'github/link_issue/5',
            ['ticket_id' => 9999999],
        );
        $this->assertResponseCode(302);

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(null, $report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
        $this->assertSession(
            'Bug Issue not found on Github. Are you sure the issue number is correct? '
            . 'Please check and try again!',
            'Flash.flash.0.message',
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
        $this->post(
            'github/link_issue/5',
            ['ticket_id' => 1387],
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
        $this->post(
            'github/link_issue/5',
            ['ticket_id' => 1387],
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
        $this->post('github/unlink_issue/123');
        $this->assertResponseCode(404);
        $this->assertResponseContains('The report does not exist.');

        // Case 1.2 Test unlinked with an already unlinked issue
        $this->post('github/unlink_issue/5');
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
        $this->post(
            'github/unlink_issue/5',
        );
        $this->assertResponseCode(302);

        $this->enableRetainFlashMessages();
        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
        $this->assertSession(
            'Unhandled response code received: 401',
            'Flash.flash.0.message',
        );
        $this->cleanupMockResponses();

        $this->mockClientPost(
            'https://api.github.com/repos/' . $repoPath . '/issues/1387/comments',
            $this->newClientResponse(201, [], $commentResponse),
        );
        // Case 3. Test submission of form with successful Github response
        $this->post(
            'github/unlink_issue/5',
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

    /**
     * The issue body names its report with a named route: load them, since a
     * unit test does not go through the application.
     */
    protected function loadAppRoutes(): void
    {
        $builder = Router::createRouteBuilder('/');
        $routes = require CONFIG . 'routes.php';
        $this->assertIsCallable($routes);
        $routes($builder);
    }

    /**
     * The body of the issue is what reaches GitHub: the escaping has to be
     * applied where the body is built, not only in the helpers it calls.
     *
     * Reverting getReportDescriptionText() to the raw values re-opens the
     * injection, so this asserts on its output.
     */
    public function testTheIssueBodyCarriesNoLiveMarkup(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $report = $this->Reports->get(1)->toArray();
        $report['description'] = $payload . "\n```\n<b>x</b>";
        $report['error_name'] = $payload . ' | injected';
        $report['error_message'] = $payload;
        $report['pma_version'] = '<https://evil.test>';

        $this->loadAppRoutes();

        $controller = new GithubController(new ServerRequest());
        $method = new ReflectionMethod($controller, 'getReportDescriptionText');
        $method->setAccessible(true);
        $body = (string) $method->invoke($controller, 1, $report);

        $this->assertStringNotContainsString($payload, $body, 'no raw tag reaches the issue');
        $this->assertStringNotContainsString('<b>', $body);
        $this->assertStringNotContainsString('<https://evil.test>', $body, 'no autolink either');
        // The value is still there, escaped: the report is not silently emptied.
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $body);
        // And the pipe did not add a column to the table.
        $this->assertStringNotContainsString('| &lt;img src=x onerror=alert(1)&gt; | injected', $body);
    }

    /**
     * A report is written by a client without an account: its values must not
     * break out of the Markdown of the issue body.
     */
    public function testATableCellStaysInItsColumn(): void
    {
        $controller = new GithubController(new ServerRequest());
        $cell = new ReflectionMethod($controller, 'tableCell');
        $cell->setAccessible(true);

        $this->assertSame(
            'Error \\| injected',
            $cell->invoke($controller, 'Error | injected'),
            'a pipe must not add a column',
        );
        $this->assertSame(
            'one line',
            $cell->invoke($controller, "one\nline"),
            'a line break must not end the row',
        );
        $this->assertStringNotContainsString('@someone', $cell->invoke($controller, 'hi @someone'));
    }

    /**
     * GitHub renders raw HTML in a comment, in a cell or in a fenced block:
     * a tag has to be escaped, and an autolink has to be broken.
     */
    public function testAMarkupValueIsNotRendered(): void
    {
        $controller = new GithubController(new ServerRequest());
        $cell = new ReflectionMethod($controller, 'tableCell');
        $cell->setAccessible(true);
        $fenced = new ReflectionMethod($controller, 'fencedText');
        $fenced->setAccessible(true);

        foreach (['<img src=x onerror=alert(1)>', '<https://evil.test>', '<b>bold</b>'] as $value) {
            foreach ([$cell, $fenced] as $method) {
                $out = (string) $method->invoke($controller, $value);
                $this->assertStringNotContainsString('<', $out, 'no tag and no autolink survives');
            }
        }
    }

    public function testAFreeTextCannotCloseItsFence(): void
    {
        $controller = new GithubController(new ServerRequest());
        $fenced = new ReflectionMethod($controller, 'fencedText');
        $fenced->setAccessible(true);

        $text = (string) $fenced->invoke($controller, "before\n```\n<img src=x>\nafter");

        $this->assertSame(2, substr_count($text, '```'), 'only the fence of the block remains');
        $this->assertStringNotContainsString('<img', $text);
        $this->assertStringNotContainsString('@someone', (string) $fenced->invoke($controller, '@someone hi'));
    }
}
