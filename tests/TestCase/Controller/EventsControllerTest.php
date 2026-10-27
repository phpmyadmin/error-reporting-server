<?php

namespace App\Test\TestCase\Controller;

use App\Model\Table\ReportsTable;
use App\Test\Fixture\ReportsFixture;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function hash_hmac;
use function json_encode;

class EventsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected ReportsTable $Reports;

    public function getFixtures(): array
    {
        return [
            ReportsFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->Reports = TableRegistry::getTableLocator()->get('Reports');
    }

    /**
     * Build the headers of a delivery, signed the way GitHub signs it.
     *
     * @param string                $payload Body of the delivery
     * @param array<string, string> $replace Headers replacing the default ones
     *
     * @return array<string, string>
     */
    protected function deliveryHeaders(string $payload, array $replace = []): array
    {
        return $replace + [
            'User-Agent' => 'GitHub-Hookshot-abcdef',
            'X-GitHub-Event' => 'issues',
            'X-Hub-Signature' => 'sha1=' . hash_hmac(
                'sha1',
                $payload,
                (string) Configure::read('GithubWebhookSecret'),
            ),
        ];
    }

    /**
     * Send a delivery of an issue event.
     *
     * @param array<string, mixed>  $event   Content of the "issue" event
     * @param array<string, string> $replace Headers replacing the default ones
     */
    protected function deliverIssueEvent(array $event, array $replace = []): void
    {
        $payload = json_encode($event);
        $this->configRequest(['headers' => $this->deliveryHeaders($payload, $replace)]);
        $this->post('/events', $payload);
    }

    public function testIndex(): void
    {
        /* Test case 1 */
        // Invalid User Agent
        $this->configRequest([
            'headers' => ['User-Agent' => 'Invalid-GitHub-Hookshot-abcdef'],
        ]);
        $this->post('/events');
        $this->assertResponseCode(403);

        /* Test case 2 */
        // Invalid Event Type
        $this->configRequest([
            'headers' => [
                'User-Agent' => 'GitHub-Hookshot-abcdef',
                'X-GitHub-Event' => 'anything-except-issues',
            ],
        ]);
        $this->post('/events');
        $this->assertResponseCode(400);

        /* Test case 3 */
        // Invalid Hash in headers
        $this->deliverIssueEvent(
            ['action' => 'closed', 'issue' => ['number' => 4]],
            ['X-Hub-Signature' => 'sha1=89db05030cf4c1fbcfb4d590deda30fa40a247ed'],
        );
        $this->assertResponseCode(401);

        /* Test case 4 */
        // Invalid issues action
        $this->deliverIssueEvent(
            ['action' => 'anything-invalid', 'issue' => ['number' => 4]],
        );
        $this->assertResponseCode(204);

        /* Test case 5 */
        // Event for an unlinked issue
        $this->deliverIssueEvent(
            ['action' => 'closed', 'issue' => ['number' => 1234]],
        );
        $this->assertResponseCode(204);

        // Prepare for testcase
        $report = $this->Reports->get(4);
        $report->set('status', 'resolved');
        $this->Reports->save($report);

        /* Test case 6 */
        // Event 'opened' for a linked issue
        $this->deliverIssueEvent(
            ['action' => 'opened', 'issue' => ['number' => 4]],
        );
        $this->assertResponseCode(201);
        $this->assertSame('forwarded', $this->Reports->get(4)->get('status'));

        /* Test case 7 */
        // Event 'closed' for a linked issue
        $this->deliverIssueEvent(
            ['action' => 'closed', 'issue' => ['number' => 4]],
        );
        $this->assertResponseCode(201);
        $this->assertSame('resolved', $this->Reports->get(4)->get('status'));

        // Prepare for testcase
        $report = $this->Reports->get(4);
        $report->set('status', 'resolved');
        $this->Reports->save($report);

        /* Test case 8 */
        // Event 'reopened' for a linked issue
        $this->deliverIssueEvent(
            ['action' => 'reopened', 'issue' => ['number' => 4]],
        );
        $this->assertResponseCode(201);
        $this->assertSame('forwarded', $this->Reports->get(4)->get('status'));

        /* Test case 9 */
        // Event 'ping'
        $this->configRequest([
            'headers' => $this->deliveryHeaders('', ['X-GitHub-Event' => 'ping']),
        ]);
        $this->post('/events');
        $this->assertResponseCode(200);
    }
}
