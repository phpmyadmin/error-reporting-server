<?php

namespace App\Test\TestCase\Shell;

use Cake\Command\Command;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use phpmock\phpunit\PHPMock;

use function file_get_contents;
use function json_decode;
use function json_encode;

use const DS;

/**
 * App\Shell\SyncGithubIssueStatesShell Test Case
 */
class SyncGithubIssueStatesShellTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use PHPMock;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = ['app.Reports', 'app.Developers', 'app.Notifications'];

    public function setUp(): void
    {
        parent::setUp();
        $this->useCommandRunner();
    }

    /**
     * Test execute method
     */
    public function testExecute(): void
    {
        $Reports = $this->getTableLocator()->get('Reports');
        // Mock functions `curl_exec` and `curl_getinfo` in GithubApiComponent
        // so that we don't actually hit the Github Api
        $curlExecMock = $this->getFunctionMock('\App\Controller\Component', 'curl_exec');
        $curlGetInfoMock = $this->getFunctionMock('\App\Controller\Component', 'curl_getinfo');

        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        $curlExecMock->expects($this->exactly(3))->willReturnOnConsecutiveCalls(
            $issueResponse,
            $issueResponse,
            $issueResponseWithClosed
        );
        $curlGetInfoMock->expects($this->exactly(3))->willReturnOnConsecutiveCalls(
            200,
            200,
            200
        );

        // Fetch all linked reports
        $reports = $Reports->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'NOT' => ['status' => 'resolved'],
                ],
            ]
        );
        $this->assertEquals(3, $reports->count());

        // Run the shell command
        $this->exec('sync_github_issue_states');
        $this->assertExitCode(Command::CODE_SUCCESS);

        // Fetch all linked reports
        $reports = $Reports->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'NOT' => ['status' => 'resolved'],
                ],
            ]
        );
        $this->assertEquals(2, $reports->count());

        // Fetch all closed reports
        $reports = $Reports->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'status' => 'resolved',
                ],
            ]
        );
        $this->assertEquals(1, $reports->count());
        $report5 = $Reports->get(4);
        $this->assertEquals('resolved', $report5->status);
    }
}
