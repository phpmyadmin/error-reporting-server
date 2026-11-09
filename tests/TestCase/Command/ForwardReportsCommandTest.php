<?php

/**
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

namespace App\Test\TestCase\Command;

use App\Command\ForwardReportsCommand;
use App\Model\Table\IncidentsTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\IncidentsFixture;
use App\Test\Fixture\NotificationsFixture;
use App\Test\Fixture\ReportsFixture;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DS;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;
use const TMP;

/**
 * The occurrences are forwarded by a command, not by the request that stored
 * them, and every occurrence is sent so that the tracker keeps showing an issue
 * as still happening.
 */
class ForwardReportsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected IncidentsTable $Incidents;

    protected ForwardReportsCommand $command;

    protected string|null $responseDirectory = null;

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

        $this->Incidents = TableRegistry::getTableLocator()->get('Incidents');
        $this->command = new ForwardReportsCommand();
    }

    public function tearDown(): void
    {
        Configure::delete('Forwarding.Sentry');

        if ($this->responseDirectory !== null) {
            @unlink($this->responseDirectory . DS . 'response.json');
            @rmdir($this->responseDirectory);
            $this->responseDirectory = null;
        }

        parent::tearDown();
    }

    /**
     * Point the forwarding at a local file that answers like the tracker.
     *
     * The real cURL runs, so the url Sentry builds becomes a query string that
     * is ignored while the local file is read: no connection is opened.
     */
    protected function enableForwarding(): void
    {
        $this->responseDirectory = sys_get_temp_dir() . DS . 'ers-fwd-' . bin2hex(random_bytes(6));
        mkdir($this->responseDirectory);
        file_put_contents($this->responseDirectory . DS . 'response.json', '{"id":""}');

        Configure::write('Forwarding.Sentry', [
            'base_url' => 'file://' . $this->responseDirectory . DS . 'response.json?',
            'project_id' => 1,
            'key' => 'k',
        ]);
    }

    /**
     * Point the forwarding at a file that does not exist, so the send fails.
     */
    protected function enableBrokenForwarding(): void
    {
        Configure::write('Forwarding.Sentry', [
            'base_url' => 'file://' . sys_get_temp_dir() . DS . 'ers-missing-'
                . bin2hex(random_bytes(6)) . '.json?',
            'project_id' => 1,
            'key' => 'k',
        ]);
    }

    /**
     * Give every incident the payload of a real fixture.
     */
    protected function storePayload(string $fixture): void
    {
        $payload = (string) file_get_contents(TESTS . 'Fixture' . DS . $fixture);
        foreach ($this->Incidents->find() as $incident) {
            $incident->set('full_report', $payload);
            $this->assertNotFalse($this->Incidents->save($incident));
        }
    }

    /**
     * Call a protected method of the command.
     *
     * @param string            $method The method
     * @param array<int, mixed> $args   Its arguments
     *
     * @return mixed Its answer
     */
    protected function callCommand(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->command, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->command, ...$args);
    }

    protected function countForwarded(): int
    {
        return $this->Incidents->find()->where(['forwarded_at IS NOT' => null])->count();
    }

    public function testTheCommandDoesNothingWhenForwardingIsDisabled(): void
    {
        Configure::delete('Forwarding.Sentry');
        $this->storePayload('report_js.json');

        $this->exec('forward_reports -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Forwarding is disabled');
        $this->assertSame(0, $this->countForwarded(), 'nothing must be marked when nothing was sent');
    }

    public function testTheCommandForwardsAndMarksTheIncidents(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertSame(
            $this->Incidents->find()->count(),
            $this->countForwarded(),
            'every incident of the fixture must be forwarded and marked',
        );
    }

    /**
     * Every occurrence is sent, not only the first one: that is what keeps an
     * issue in the tracker showing that it still happens.
     */
    public function testEveryOccurrenceOfAReportIsForwarded(): void
    {
        $this->assertSame(
            2,
            $this->Incidents->find()->where(['report_id' => 4])->count(),
            'the report 4 carries two incidents',
        );

        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        foreach ($this->Incidents->find()->where(['report_id' => 4]) as $incident) {
            $this->assertNotNull(
                $incident->get('forwarded_at'),
                'the incident ' . $incident->get('id') . ' must have been forwarded',
            );
        }
    }

    /**
     * The stored payload is the whole submission: each incident must send its
     * own error only, or a report of N errors would send N events N times.
     */
    public function testThePayloadOfAnIncidentCarriesItsOwnErrorOnly(): void
    {
        $errors = [
            ['msg' => 'first', 'type' => 'Error', 'file' => '/a.php', 'line' => 1, 'stackTrace' => []],
            ['msg' => 'second', 'type' => 'Error', 'file' => '/b.php', 'line' => 2, 'stackTrace' => []],
        ];
        $payload = (string) json_encode([
            'pma_version' => '5.2.0',
            'exception_type' => 'php',
            'errors' => $errors,
        ]);

        $incidents = $this->Incidents->find()->orderBy(['Incidents.id' => 'ASC'])->limit(2)->all()->toArray();
        $incidents[0]->set('full_report', $payload);
        $incidents[0]->set('script_name', '/a.php');
        $incidents[0]->set('linenumber', 1);
        $incidents[1]->set('full_report', $payload);
        $incidents[1]->set('script_name', '/b.php');
        $incidents[1]->set('linenumber', 2);

        $first = json_decode((string) $this->callCommand('payloadForIncident', [$payload, $incidents[0]]), true);
        $second = json_decode((string) $this->callCommand('payloadForIncident', [$payload, $incidents[1]]), true);

        $this->assertCount(1, $first['errors'], 'one error per incident');
        $this->assertSame('first', $first['errors'][0]['msg']);
        $this->assertCount(1, $second['errors'], 'one error per incident');
        $this->assertSame('second', $second['errors'][0]['msg']);
    }

    /**
     * A JavaScript report carries a single error: its payload is sent as it is.
     */
    public function testAReportWithoutErrorListIsSentAsItIs(): void
    {
        $payload = (string) file_get_contents(TESTS . 'Fixture' . DS . 'report_js.json');
        $incident = $this->Incidents->find()->orderBy(['Incidents.id' => 'ASC'])->firstOrFail();

        $this->assertSame(
            $payload,
            $this->callCommand('payloadForIncident', [$payload, $incident]),
        );
    }

    public function testAHealthyRunIsSilent(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertOutputEmpty('a healthy run must print nothing on stdout');
        $this->assertErrorEmpty('a healthy run must print nothing on stderr');
    }

    public function testTheCommandIsSilentWhenThereIsNothingToDo(): void
    {
        $this->enableForwarding();
        $this->Incidents->updateAll(['forwarded_at' => '2024-01-01 00:00:00'], []);

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertOutputEmpty();
        $this->assertErrorEmpty();
    }

    public function testTheVerboseFlagPrintsOneLinePerIncident(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Incident 1:');
        $this->assertOutputContains('Forwarded 5 incident(s), 0 failed.');
        // The event ids are the detail of --debug, not of -v.
        $this->assertOutputNotContains('  event ');
    }

    public function testTheDebugFlagPrintsTheEventIds(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --debug');

        $this->assertExitSuccess();
        $this->assertOutputRegExp('/^\s*event [0-9a-f]{32}$/m');
    }

    public function testTheLimitBoundsTheRun(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --limit 2');

        $this->assertExitSuccess();
        $this->assertSame(2, $this->countForwarded(), 'only two incidents must be marked');
    }

    public function testASecondRunDoesNotOverlap(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $handle = fopen(TMP . ForwardReportsCommand::LOCK_FILE, 'c');
        $this->assertNotFalse($handle);
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'the test must hold the lock');

        try {
            $this->exec('forward_reports');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $this->assertExitSuccess();
        $this->assertOutputEmpty('a run that found the lock held must print nothing');
        $this->assertSame(0, $this->countForwarded(), 'nothing must be forwarded while the lock is held');
    }

    /**
     * A refused incident is the one thing a run reports on stderr, since it
     * stays pending and is retried: the tracker's own error is what alerts.
     */
    public function testARefusedIncidentKeepsStdoutEmpty(): void
    {
        $this->enableBrokenForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --limit 1');

        $this->assertExitSuccess();
        $this->assertOutputEmpty('the progress stays on stdout only with -v');
        $this->assertErrorContains('cURL error');
        $this->assertSame(0, $this->countForwarded(), 'a refused incident stays pending');
    }

    public function testAnIncompleteConfigurationIsAnError(): void
    {
        Configure::write('Forwarding.Sentry', ['base_url' => 'file:///tmp/']);

        $this->exec('forward_reports');

        $this->assertExitError();
        $this->assertErrorContains('needs a base_url, a project_id and a key');
    }
}
