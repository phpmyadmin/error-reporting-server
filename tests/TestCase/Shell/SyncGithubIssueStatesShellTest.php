<?php
namespace App\Test\TestCase\Shell;

use App\Shell\SyncGithubIssueStatesShell;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Shell\SyncGithubIssueStatesShell Test Case
 */
class SyncGithubIssueStatesShellTest extends TestCase
{

    use \phpmock\phpunit\PHPMock;

    /**
     * ConsoleIo mock
     *
     * @var \Cake\Console\ConsoleIo|\PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var \App\Shell\SyncGithubIssueStatesShell
     */
    public $SyncGithubIssueStates;

    public $fixtures = [
        'app.Reports',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->SyncGithubIssueStates = new SyncGithubIssueStatesShell($this->io);
        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SyncGithubIssueStates);

        parent::tearDown();
    }

    /**
     * Test main method
     *
     * @return void
     */
    public function testMain()
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
                    'NOT' => [
                        'status' => 'resolved',
                    ]
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
                    'NOT' => [
                        'status' => 'resolved',
                    ]
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
                    'status' => 'resolved'
                ],
            ]
        );
        $this->assertEquals(1, $reports->count());
        $report5 = $this->Reports->get(4);
        $this->assertEquals('resolved', $report5->status);
    }
}
