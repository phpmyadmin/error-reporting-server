<?php

namespace App\Test\TestCase\Shell;

use App\Shell\SyncGithubIssueStatesShell;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use phpmock\phpunit\PHPMock;
use PHPUnit_Framework_MockObject_MockObject;
use const DS;
use function file_get_contents;
use function json_decode;
use function json_encode;

/**
 * App\Shell\SyncGithubIssueStatesShell Test Case
 */
class SyncGithubIssueStatesShellTest extends TestCase
{
    use PHPMock;

    /**
     * ConsoleIo mock
     *
     * @var ConsoleIo|PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var SyncGithubIssueStatesShell
     */
    public $SyncGithubIssueStates;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = ['app.Reports'];

    /**
     * setUp method
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->SyncGithubIssueStates = new SyncGithubIssueStatesShell($this->io);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    /**
     * tearDown method
     */
    public function tearDown(): void
    {
        unset($this->SyncGithubIssueStates);

        parent::tearDown();
    }

    /**
     * Test main method
     */
    public function testMain(): void
    {
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
        $reports = $this->Reports->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'NOT' => ['status' => 'resolved'],
                ],
            ]
        );
        $this->assertEquals(3, $reports->count());

        $this->SyncGithubIssueStates->main();

        // Fetch all linked reports
        $reports = $this->Reports->find(
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
        $reports = $this->Reports->find(
            'all',
            [
                'conditions' => [
                    'sourceforge_bug_id IS NOT NULL',
                    'status' => 'resolved',
                ],
            ]
        );
        $this->assertEquals(1, $reports->count());
        $report5 = $this->Reports->get(4);
        $this->assertEquals('resolved', $report5->status);
    }
}
