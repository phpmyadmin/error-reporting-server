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

namespace App\Command;

use App\Forwarding\Sentry;
use App\Forwarding\TrackerThrottledException;
use App\Forwarding\TrackerUnreachableException;
use App\Model\Table\IncidentsTable;
use App\Report;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Throwable;

use function count;
use function date;
use function fclose;
use function fflush;
use function flock;
use function fopen;
use function ftruncate;
use function fwrite;
use function getmypid;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function min;
use function rewind;
use function strlen;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * Sends the reports that were stored but not forwarded to the tracker yet.
 *
 * The forwarding used to run inside the request that stored the report, so a
 * slow tracker delayed the answer to the client and the notification mail. It
 * runs here instead, on the reports that carry no forwarded_at, and it can be
 * started by a cron: a run that finds nothing to do, or that another run is
 * already doing, prints nothing.
 *
 * A report is forwarded from one of its incidents: every incident of a report
 * stores the whole payload, so reading one is enough, and reading them all
 * would send the events of the report once per incident.
 */
class ForwardReportsCommand extends Command
{
    /**
     * Level above the verbose one, where the detail of a report is printed.
     *
     * CakePHP has no VERY_VERBOSE: -vvv is read as -v, so the third level is
     * set by the --debug option instead.
     */
    public const DEBUG_LEVEL = ConsoleIo::VERBOSE + 1;

    /**
     * Only one run at a time, see acquireLock().
     */
    public const LOCK_FILE = 'forward_reports.lock';

    /**
     * Number of reports read at once: the driver buffers a result, so the
     * backlog is drained a batch at a time rather than in one query.
     */
    public const BATCH_SIZE = 100;

    public static function defaultName(): string
    {
        return 'forward_reports';
    }

    public static function getDescription(): string
    {
        return 'Forward the stored reports to the tracker';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setCommand($this->name)
            ->setDescription('Forward the reports that were not forwarded yet to the tracker')
            ->addOption('limit', [
                'help' => 'Largest number of reports to forward, 0 for all of them',
                'default' => '0',
            ])
            ->addOption('debug', [
                'help' => 'Print one line per event, for debugging',
                'boolean' => true,
            ]);
    }

    /** @return int */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        if ($args->getOption('debug')) {
            // A tier of our own: -vvv cannot be told from -v by the framework.
            $io->level(self::DEBUG_LEVEL);
        }

        if (Configure::read('Forwarding.Sentry') === null) {
            // Nothing to forward to: the command is not an error, an
            // installation without a tracker simply runs it for nothing.
            $io->verbose('Forwarding is disabled, nothing to do.');

            return self::CODE_SUCCESS;
        }

        if (! Sentry::hasConfig()) {
            $error = 'The Sentry config needs a base_url, a project_id and a key';
            Log::error($error, 'cron_jobs');
            $io->error($error);

            return self::CODE_ERROR;
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            // Another run holds it, and it is doing the work: not an error, and
            // no output, a cron must stay quiet.
            $io->verbose('Another run is in progress, nothing to do.');

            return self::CODE_SUCCESS;
        }

        if ($lock === false) {
            $error = 'Could not open ' . $this->lockPath() . ' to lock the run';
            Log::error($error, 'cron_jobs');
            $io->error($error);

            return self::CODE_ERROR;
        }

        Sentry::reset();

        try {
            return $this->forwardPendingIncidents((int) $args->getOption('limit'), $io);
        } finally {
            Sentry::reset();
            $this->releaseLock($lock);
        }
    }

    /**
     * Forward the pending incidents, a batch at a time, up to the limit.
     *
     * Read in batches: the query is buffered by the driver, so reading every
     * pending incident at once would hold the whole backlog in memory, and a
     * backlog is exactly what this command exists to drain.
     *
     * @param int       $limit Largest number of incidents, 0 for all of them
     * @param ConsoleIo $io    The console
     *
     * @return int The exit code: a report the tracker refused is logged and
     *             stays pending, which is not a failure of the run itself
     */
    protected function forwardPendingIncidents(int $limit, ConsoleIo $io): int
    {
        $sent = 0;
        $failed = 0;
        $attempted = 0;
        $lastId = 0;

        while (true) {
            $size = self::BATCH_SIZE;
            if ($limit > 0) {
                $size = min($size, $limit - $attempted);
                if ($size <= 0) {
                    break;
                }
            }

            $batch = $this->findPendingIncidents($lastId, $size);
            $read = 0;
            foreach ($batch as $incident) {
                $read++;
                $attempted++;
                $lastId = max($lastId, (int) $incident->get('id'));

                // An incident that is not marked stays pending for the next
                // run, and its id is already behind the cursor: it is not read
                // twice by this run, which would otherwise never end.
                try {
                    $this->forwardIncident($incident, $io) ? $sent++ : $failed++;
                } catch (TrackerUnreachableException | TrackerThrottledException $e) {
                    // Nothing more will reach the tracker in this run.
                    Log::error('Stopping the run: ' . $e->getMessage(), 'cron_jobs');
                    $io->error($e->getMessage());
                    $io->verbose('Forwarded ' . $sent . ' incident(s) before stopping.');

                    return self::CODE_ERROR;
                }
            }

            if ($read < $size) {
                break;
            }
        }

        $io->verbose('Forwarded ' . $sent . ' incident(s), ' . $failed . ' failed.');

        return self::CODE_SUCCESS;
    }

    /**
     * Forward one incident, and mark it when the tracker took it.
     *
     * @param EntityInterface $incident The incident
     * @param ConsoleIo       $io       The console
     *
     * @return bool True when the incident is done with
     */
    protected function forwardIncident(EntityInterface $incident, ConsoleIo $io): bool
    {
        $incidents = $this->getIncidentsTable();
        $incidentId = (int) $incident->get('id');

        try {
            $stored = $incident->get('full_report');
            if (! is_string($stored) || $stored === '') {
                // Nothing will ever be forwarded for this incident.
                $this->markForwarded($incidents, $incident, $io, 'carries no report');

                return true;
            }

            // The payload of this occurrence only: sending the whole submission
            // once per occurrence is how the same error reaches the tracker
            // again and again under new ids.
            $payload = $this->payloadForIncident($stored, $incident);
            if ($payload === null) {
                Log::error(
                    'The incident ' . $incidentId . ' has no error in the payload it was built from',
                    'cron_jobs',
                );
                $io->verbose('Incident ' . $incidentId . ' skipped: no matching error in its payload');
                $this->markForwarded($incidents, $incident, $io, 'has no error in its payload');

                return true;
            }

            if (! $this->forward($payload, $incidentId, $io)) {
                return false;
            }

            $this->markForwarded($incidents, $incident, $io);

            return true;
        } catch (TrackerUnreachableException | TrackerThrottledException $e) {
            // These two do not concern one incident: asking again in this run
            // costs the timeout of every event and drains nothing.
            throw $e;
        } catch (Throwable $e) {
            // One incident must not stop the drain: a deadlock, a lost
            // connection, anything the forward itself does not catch.
            Log::error(
                'Could not forward the incident ' . $incidentId . ': ' . $e->getMessage(),
                'cron_jobs',
            );
            $io->verbose('Incident ' . $incidentId . ' failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Forward one incident, from the error it was built from.
     *
     * The stored payload is the whole submission, which carries one entry per
     * error: send the entry of this incident only, or every occurrence would
     * replay every error of the report into the tracker.
     *
     * @param string    $payload    The payload to send
     * @param int       $incidentId The incident it belongs to
     * @param ConsoleIo $io         The console
     *
     * @return bool True when the tracker took it
     */
    protected function forward(string $payload, int $incidentId, ConsoleIo $io): bool
    {
        try {
            $eventIds = Sentry::process(Report::fromString($payload));
        } catch (Throwable $e) {
            // The incident stays unforwarded: the next run tries again, which
            // is the point of the column.
            Log::error(
                'Could not forward the incident ' . $incidentId . ': ' . $e->getMessage(),
                'cron_jobs',
            );
            // An incident the tracker refuses stays pending and is retried on
            // every run, so this is logged once per attempt, to the cron_jobs
            // log: that is the trail, and a cron may mail it while the
            // incident is stuck. The console echo of it is what -v adds.
            $io->verbose('Incident ' . $incidentId . ' refused: ' . $e->getMessage());

            return false;
        }

        $io->verbose(
            'Incident ' . $incidentId . ': ' . strlen($payload) . ' bytes, '
            . count($eventIds) . ' event(s)',
        );
        foreach ($eventIds as $eventId) {
            $io->out('  event ' . $eventId, 1, self::DEBUG_LEVEL);
        }

        return true;
    }

    /**
     * Narrow the stored payload to the error of one incident.
     *
     * The stored payload is the whole submission, and it carries one entry per
     * error. Sending it whole for every incident of a report would send every
     * error once per occurrence, with a fresh id each time: the tracker cannot
     * tell those apart and counts them again.
     *
     * @param string          $payload  The payload of the submission
     * @param EntityInterface $incident The incident to send
     *
     * @return string|null The payload that carries that error only, null when
     *                     the error it was built from cannot be found
     */
    protected function payloadForIncident(string $payload, EntityInterface $incident): string|null
    {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! isset($decoded['errors']) || ! is_array($decoded['errors'])) {
            // A JavaScript report carries a single error: there is nothing to
            // narrow, and the incident is that report.
            return $payload;
        }

        $error = $this->findErrorOfIncident($decoded['errors'], $incident);
        if ($error === null) {
            return null;
        }

        $decoded['errors'] = [$error];

        return (string) json_encode($decoded);
    }

    /**
     * The entry of the payload that built this incident.
     *
     * The three fields compared here are the three the ingest copies from the
     * error into the incident, cut to the width of their column, so the
     * comparison is exact. Two errors that differ in none of them are the same
     * error as far as anything can tell, and sending either is the same.
     *
     * @param array<int, mixed> $errors   The errors of the payload
     * @param EntityInterface   $incident The incident
     *
     * @return array<string, mixed>|null The entry, null when there is none
     */
    protected function findErrorOfIncident(array $errors, EntityInterface $incident): array|null
    {
        $file = (string) $incident->get('script_name');
        $name = (string) $incident->get('error_name');
        $message = (string) $incident->get('error_message');

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            if (IncidentsTable::truncate((string) ($error['file'] ?? ''), 255) !== $file) {
                continue;
            }

            if (IncidentsTable::truncate((string) ($error['type'] ?? ''), 30) !== $name) {
                continue;
            }

            if (IncidentsTable::truncate((string) ($error['msg'] ?? ''), 200) !== $message) {
                continue;
            }

            return $error;
        }

        return null;
    }

    /**
     * The incidents that were not forwarded yet, after the ones already read.
     *
     * @param int $lastId The id of the last incident that was read
     * @param int $size   Largest number of incidents to read
     *
     * @return SelectQuery<EntityInterface> The incidents, oldest first
     */
    protected function findPendingIncidents(int $lastId, int $size): SelectQuery
    {
        // The cursor is the id rather than an offset: an incident that stays
        // pending does not shift the batch, and the index on forwarded_at
        // serves the filter while InnoDB appends the primary key to it, so the
        // order needs no filesort either.
        return $this->getIncidentsTable()->find()
            ->where(['Incidents.forwarded_at IS' => null, 'Incidents.id >' => $lastId])
            ->orderBy(['Incidents.id' => 'ASC'])
            ->limit($size);
    }

    /**
     * Remember that this run is done with the incident.
     *
     * Only called once the tracker took it, or when there is nothing to send:
     * an incident that was refused is left pending on purpose, for the next run.
     *
     * @param IncidentsTable  $incidents The table
     * @param EntityInterface $incident  The incident
     * @param ConsoleIo       $io        The console
     * @param string          $reason    Why it is done without being sent
     */
    protected function markForwarded(
        IncidentsTable $incidents,
        EntityInterface $incident,
        ConsoleIo $io,
        string $reason = '',
    ): void {
        // One UPDATE by key: the incident is already stored, and nothing else
        // on the row changes.
        $incidents->updateAll(
            ['forwarded_at' => date('Y-m-d H:i:s')],
            ['id' => $incident->get('id')],
        );

        if ($reason === '') {
            return;
        }

        $io->verbose('Incident ' . $incident->get('id') . ' skipped: it ' . $reason);
    }

    /**
     * Take the lock that keeps two runs from overlapping.
     *
     * The lock is a flock() on a file, so the kernel releases it if the process
     * dies: there is no stale lock to clear, and no time to keep.
     *
     * @return resource|false|null The handle, false when the file cannot be
     *                             opened, null when another run holds it
     */
    protected function acquireLock()
    {
        $path = $this->lockPath();
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        // Whoever reads the file sees who holds it.
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) getmypid());
        fflush($handle);

        return $handle;
    }

    /**
     * Release the lock taken by acquireLock().
     *
     * @param resource $handle The handle
     */
    protected function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * The path of the lock file.
     */
    protected function lockPath(): string
    {
        return TMP . self::LOCK_FILE;
    }

    /**
     * The reports table.
     */
    protected function getIncidentsTable(): IncidentsTable
    {
        // The table locator resolves the class from the name, which the
        // analysis already knows.
        return TableRegistry::getTableLocator()->get('Incidents');
    }
}
