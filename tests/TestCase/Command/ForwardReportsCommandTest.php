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
use App\Model\Table\ReportsTable;
use App\Report;
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
 * The report is forwarded by a command, not by the request that stored it.
 */
class ForwardReportsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected ReportsTable $Reports;

    protected IncidentsTable $Incidents;

    protected ForwardReportsCommand $command;

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
     * Directory the fake tracker answers from, removed on tear down.
     */
    protected string|null $responseDirectory = null;

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
            'base_url' => 'file://' . sys_get_temp_dir() . DS . 'ers-missing-' . bin2hex(random_bytes(6)) . '.json?',
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

    public function testTheCommandDoesNothingWhenForwardingIsDisabled(): void
    {
        Configure::delete('Forwarding.Sentry');
        $this->storePayload('report_js.json');

        $this->exec('forward_reports -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Forwarding is disabled');
        $this->assertSame(
            0,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'no report must be marked when nothing was sent',
        );
    }

    public function testTheCommandForwardsAndMarksTheReport(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --limit 1');

        $this->assertExitSuccess();
        $this->assertSame(
            1,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'the forwarded report must be marked',
        );
    }

    /**
     * A report is read once, whatever the number of its incidents: every
     * incident of a report stores the whole payload.
     */
    public function testAReportIsPendingOnceWhateverItsIncidentCount(): void
    {
        // The report 4 of the fixture carries two incidents.
        $this->assertSame(2, $this->Incidents->find()->where(['report_id' => 4])->count());

        $pending = $this->callCommand('findPendingReports', [100]);

        $this->assertSame(4, $pending->count(), 'the fixture holds 4 reports');
    }

    public function testThePayloadIsReadFromTheFirstIncidentOfTheReport(): void
    {
        $payload = $this->callCommand('findPayload', [$this->Reports->get(4)]);

        $this->assertIsString($payload);
        $this->assertNotSame('', $payload);
    }

    /**
     * The payload of a report is read from the incident, and it must carry its
     * data: read with fromObject() from the decoded arrays, every message,
     * culprit and frame came out empty.
     */
    public function testThePayloadThatIsForwardedCarriesItsData(): void
    {
        $this->storePayload('report_php.json');

        $payload = $this->callCommand('findPayload', [$this->Reports->get(4)]);
        $this->assertIsString($payload);

        $events = Report::fromString($payload)->getReports();
        $this->assertNotSame([], $events);
        $this->assertNotSame('', $events[0]['message'], 'the message must not be empty');
        $this->assertNotSame('', $events[0]['culprit'], 'the culprit must not be empty');
        $this->assertNotSame(
            [],
            $events[0]['exception']['values'][0]->stacktrace->frames,
            'the frames must not be empty',
        );
    }

    public function testAReportAlreadyForwardedIsNotSentAgain(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --limit 1');
        $marked = $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count();
        $this->assertSame(1, $marked);

        $this->exec('forward_reports --limit 1');

        $this->assertSame(
            2,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'the second run must take the next report, not the one already sent',
        );
    }

    public function testAFailedForwardLeavesTheReportForTheNextRun(): void
    {
        // A base url that cannot be read: the send throws.
        $this->enableBrokenForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports --limit 1');

        // The run completed: the report stays pending and the next run retries
        // it, which must not make cron complain about a crash.
        $this->assertExitSuccess();
        $this->assertErrorContains('Report');
        $this->assertSame(
            0,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'a failed report must stay pending, the next run retries it',
        );
    }

    /**
     * A healthy run is silent: cron must not mail the output of every run.
     */
    public function testTheCommandIsSilentByDefault(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertOutputEmpty('a healthy run must print nothing on stdout');
        $this->assertErrorEmpty('a healthy run must print nothing on stderr');
        $this->assertSame(
            4,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'every pending report must still have been forwarded',
        );
    }

    public function testTheCommandIsSilentWhenThereIsNothingToDo(): void
    {
        $this->enableForwarding();
        $this->Reports->updateAll(['forwarded_at' => '2024-01-01 00:00:00'], []);

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertOutputEmpty();
        $this->assertErrorEmpty();
    }

    /**
     * The report is forwarded but nothing is printed, so -q changes nothing.
     */
    public function testTheQuietFlagKeepsTheRunSilent(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports -q');

        $this->assertExitSuccess();
        $this->assertOutputEmpty();
    }

    public function testTheVerboseFlagPrintsOneLinePerReport(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Report 1: 4628 bytes, 1 event(s)');
        $this->assertOutputContains('Forwarded 4 report(s), 0 failed.');
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

    /**
     * Everything pending is forwarded in one run: no cap by default.
     */
    public function testTheCommandForwardsEveryPendingReport(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');

        $this->assertExitSuccess();
        $this->assertSame(
            $this->Reports->find()->count(),
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'every report of the fixture must be done with',
        );
    }

    /**
     * A second run must not start while the first holds the lock: it prints
     * nothing and is not an error.
     */
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
        $this->assertSame(
            0,
            $this->Reports->find()->where(['forwarded_at IS NOT' => null])->count(),
            'nothing must be forwarded while another run holds the lock',
        );
    }

    public function testTheLockIsReleasedAfterARun(): void
    {
        $this->enableForwarding();
        $this->storePayload('report_js.json');

        $this->exec('forward_reports');
        $this->assertExitSuccess();

        // The next run must be able to take it.
        $handle = fopen(TMP . ForwardReportsCommand::LOCK_FILE, 'c');
        $this->assertNotFalse($handle);
        $this->assertTrue(
            flock($handle, LOCK_EX | LOCK_NB),
            'the lock must be free once the run returned',
        );
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * A configuration that cannot send anything is a failure of the run, unlike
     * a report the tracker refused.
     */
    public function testAnIncompleteConfigurationIsAnError(): void
    {
        Configure::write('Forwarding.Sentry', ['base_url' => 'file:///tmp/']);

        $this->exec('forward_reports');

        $this->assertExitError();
        $this->assertErrorContains('needs a base_url, a project_id and a key');
    }
}
