<?php

namespace App\Test\TestCase\Shell;

use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Http\TestSuite\HttpClientTrait;
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
    use HttpClientTrait;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
            ReportsFixture::class,
        ];
    }

    /**
     * Test execute method
     */
    public function testExecute(): void
    {
        $repoPath = 'phpmyadmin/phpmyadmin';
        $Reports = $this->getTableLocator()->get('Reports');
        $issueResponse = file_get_contents(TESTS . 'Fixture' . DS . 'issue_response.json');
        $decodedResponse = json_decode($issueResponse, true);
        $decodedResponse['state'] = 'closed';
        $issueResponseWithClosed = json_encode($decodedResponse);

        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/1',
            $this->newClientResponse(200, [], $issueResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/2',
            $this->newClientResponse(200, [], $issueResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/' . $repoPath . '/issues/4',
            $this->newClientResponse(200, [], $issueResponseWithClosed),
        );

        // Fetch all linked reports
        $reports = $Reports->find(
            'all',
            conditions: [
                'sourceforge_bug_id IS NOT NULL',
                'NOT' => ['status' => 'resolved'],
            ],
        );
        $this->assertEquals(3, $reports->count());

        // Run the shell command
        $this->exec('sync_github_issue_states');
        $this->assertExitCode(Command::CODE_SUCCESS);

        // Fetch all linked reports
        $reports = $Reports->find(
            'all',
            conditions: [
                'sourceforge_bug_id IS NOT NULL',
                'NOT' => ['status' => 'resolved'],
            ],
        );
        $this->assertEquals(2, $reports->count());

        // Fetch all closed reports
        $reports = $Reports->find(
            'all',
            conditions: [
                'sourceforge_bug_id IS NOT NULL',
                'status' => 'resolved',
            ],
        );
        $this->assertEquals(1, $reports->count());
        $report5 = $Reports->get(4);
        $this->assertEquals('resolved', $report5->status);
    }
}
