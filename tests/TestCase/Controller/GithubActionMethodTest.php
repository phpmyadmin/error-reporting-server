<?php

/**
 * Tests the HTTP methods accepted by the actions changing a report.
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
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function file_get_contents;
use function json_encode;

use const DS;

/**
 * App\Controller\GithubController and App\Controller\ReportsController
 * actions must not accept GET requests, since GET requests are not covered
 * by the CSRF middleware and can be triggered from any other site.
 */
class GithubActionMethodTest extends TestCase
{
    use HttpClientTrait;
    use IntegrationTestTrait;

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

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
        $this->session(['Developer.id' => 1, 'access_token' => 'abc', 'read_only' => false]);
    }

    /**
     * Mock the creation of a comment on a Github issue.
     */
    protected function mockComment(int $issueNumber): void
    {
        $this->mockClientPost(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/issues/'
                . $issueNumber . '/comments',
            $this->newClientResponse(201, [], json_encode([])),
        );
    }

    /**
     * Mock the retrieval of a Github issue.
     */
    protected function mockIssue(int $issueNumber): void
    {
        $this->mockClientGet(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/issues/' . $issueNumber,
            $this->newClientResponse(
                200,
                [],
                file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json'),
            ),
        );
    }

    /**
     * Link report 5 to issue 1387 on Github.
     */
    protected function linkReportToIssue(): void
    {
        $report = $this->Reports->get(5);
        $report->sourceforge_bug_id = 1387;
        $report->status = 'forwarded';
        $this->Reports->save($report);
    }

    public function testLinkIssueRejectsGetRequest(): void
    {
        $this->get('github/link_issue/5?ticket_id=1387');

        $this->assertResponseCode(405);
        $this->assertNull($this->Reports->get(5)->sourceforge_bug_id);
    }

    public function testUnlinkIssueRejectsGetRequest(): void
    {
        $this->linkReportToIssue();

        $this->get('github/unlink_issue/5');

        $this->assertResponseCode(405);
        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
    }

    public function testChangeStateRejectsGetRequest(): void
    {
        $this->get('reports/change_state/1?state=resolved');

        $this->assertResponseCode(405);
        $this->assertEquals('forwarded', $this->Reports->get(1)->status);
    }

    public function testMassActionRejectsGetRequest(): void
    {
        $this->get('reports/mass_action?state=resolved&reports[]=1');

        $this->assertResponseCode(405);
        $this->assertEquals('forwarded', $this->Reports->get(1)->status);
    }

    public function testLinkIssueRejectsPostWithoutCsrfToken(): void
    {
        $this->mockComment(1387);
        $this->mockIssue(1387);

        $this->post('github/link_issue/5', ['ticket_id' => 1387]);

        $this->assertResponseCode(403);
        $this->assertNull($this->Reports->get(5)->sourceforge_bug_id);
    }

    public function testUnlinkIssueRejectsPostWithoutCsrfToken(): void
    {
        $this->linkReportToIssue();
        $this->mockComment(1387);

        $this->post('github/unlink_issue/5');

        $this->assertResponseCode(403);
        $this->assertEquals(1387, $this->Reports->get(5)->sourceforge_bug_id);
    }

    public function testLinkIssueOnlyReadsTheTicketIdFromThePostBody(): void
    {
        $this->enableCsrfToken();

        // The ticket id used to be read from the query string.
        $this->post('github/link_issue/5?ticket_id=1387');

        $this->assertResponseCode(404);
        $this->assertResponseContains('Invalid Ticket ID');
        $this->assertNull($this->Reports->get(5)->sourceforge_bug_id);
    }

    public function testLinkIssuePostWithCsrfTokenLinksTheReport(): void
    {
        $this->enableCsrfToken();
        $this->mockComment(1387);
        $this->mockIssue(1387);

        $this->post('github/link_issue/5', ['ticket_id' => 1387]);

        $this->assertResponseCode(302);
        $report = $this->Reports->get(5);
        $this->assertEquals(1387, $report->sourceforge_bug_id);
        $this->assertEquals('forwarded', $report->status);
    }

    public function testUnlinkIssuePostWithCsrfTokenUnlinksTheReport(): void
    {
        $this->enableCsrfToken();
        $this->linkReportToIssue();
        $this->mockComment(1387);

        $this->post('github/unlink_issue/5');

        $this->assertResponseCode(302);
        $report = $this->Reports->get(5);
        $this->assertNull($report->sourceforge_bug_id);
        $this->assertEquals('new', $report->status);
    }

    public function testChangeStatePostWithCsrfTokenChangesTheState(): void
    {
        $this->enableCsrfToken();

        $this->post('reports/change_state/1', ['state' => 'resolved']);

        $this->assertResponseCode(302);
        $this->assertEquals('resolved', $this->Reports->get(1)->status);
    }

    public function testMassActionPostWithCsrfTokenChangesTheState(): void
    {
        $this->enableCsrfToken();

        $this->post('reports/mass_action', ['state' => 'resolved', 'reports' => [1]]);

        $this->assertResponseCode(302);
        $this->assertEquals('resolved', $this->Reports->get(1)->status);
    }

    public function testViewRendersTheGithubFormsAsPostFormsWithACsrfToken(): void
    {
        // Report 5 is not linked to any issue: the link form is displayed.
        $this->get('/reports/view/5');

        $this->assertResponseCode(200);
        $this->assertResponseRegExp(
            '/<form method="post"[^>]*action="\/github\/link_issue\/5">\s*<div[^>]*>\s*'
                . '<input type="hidden" name="_csrfToken"/',
        );
        $this->assertResponseNotContains('method="GET"');

        // Report 1 is linked to issue 1: the unlink form is displayed.
        $this->get('/reports/view/1');

        $this->assertResponseCode(200);
        $this->assertResponseRegExp(
            '/<form method="post"[^>]*action="\/github\/unlink_issue\/1">\s*<div[^>]*>\s*'
                . '<input type="hidden" name="_csrfToken"/',
        );
        $this->assertResponseNotContains('method="GET"');
    }
}
