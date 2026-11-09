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
use App\Model\Table\ReportsTable;
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

        if (! $this->isConfigComplete(Configure::read('Forwarding.Sentry'))) {
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

        try {
            return $this->forwardPendingReports((int) $args->getOption('limit'), $io);
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Forward every pending report, up to the limit when one is given.
     *
     * @param int       $limit Largest number of reports, 0 for all of them
     * @param ConsoleIo $io    The console
     *
     * @return int The exit code: a report the tracker refused is logged and
     *             stays pending, which is not a failure of the run itself
     */
    protected function forwardPendingReports(int $limit, ConsoleIo $io): int
    {
        $reports = $this->getReportsTable();

        $sent = 0;
        $failed = 0;
        foreach ($this->findPendingReports($limit) as $report) {
            $payload = $this->findPayload($report);

            if ($payload === null) {
                // A report without incident has nothing to forward, and never
                // will: mark it so the query stops reading it.
                $this->markForwarded($reports, $report, $io, 'has no incident');

                continue;
            }

            if ($this->forward($payload, (int) $report->get('id'), $io)) {
                $this->markForwarded($reports, $report, $io);
                $sent++;

                continue;
            }

            $failed++;
        }

        $io->verbose('Forwarded ' . $sent . ' report(s), ' . $failed . ' failed.');

        return self::CODE_SUCCESS;
    }

    /**
     * Forward the payload of a report.
     *
     * @param string    $payload  The payload, as it was stored
     * @param int       $reportId The report it belongs to
     * @param ConsoleIo $io       The console
     *
     * @return bool True when the tracker took it
     */
    protected function forward(string $payload, int $reportId, ConsoleIo $io): bool
    {
        try {
            $eventIds = Sentry::process(Report::fromString($payload));
        } catch (Throwable $e) {
            // The report stays unforwarded: the next run tries again, which is
            // the point of the column.
            Log::error(
                'Could not forward the report ' . $reportId . ': ' . $e->getMessage(),
                'cron_jobs',
            );
            $io->error('Report ' . $reportId . ': ' . $e->getMessage());

            return false;
        }

        $io->verbose(
            'Report ' . $reportId . ': ' . strlen($payload) . ' bytes, '
            . count($eventIds) . ' event(s)',
        );
        foreach ($eventIds as $eventId) {
            $io->out('  event ' . $eventId, 1, self::DEBUG_LEVEL);
        }

        return true;
    }

    /**
     * The reports that were not forwarded yet.
     *
     * @param int $limit Largest number of reports to read, 0 for all of them
     *
     * @return SelectQuery<EntityInterface> The reports, oldest first
     */
    protected function findPendingReports(int $limit): SelectQuery
    {
        $query = $this->getReportsTable()->find()
            ->where(['Reports.forwarded_at IS' => null])
            ->orderBy(['Reports.id' => 'ASC']);

        if ($limit > 0) {
            $query = $query->limit($limit);
        }

        return $query;
    }

    /**
     * The payload of a report, read from its first incident.
     *
     * Every incident of a report stores the whole payload, so one of them is
     * enough: reading them all would send the events of the report once per
     * incident.
     *
     * @param EntityInterface $report The report
     *
     * @return string|null The payload, null when the report has no incident
     */
    protected function findPayload(EntityInterface $report): string|null
    {
        $incident = TableRegistry::getTableLocator()->get('Incidents')->find()
            ->where(['Incidents.report_id' => $report->get('id')])
            ->orderBy(['Incidents.id' => 'ASC'])
            ->first();

        if (! $incident instanceof EntityInterface) {
            return null;
        }

        $payload = $incident->get('full_report');

        return is_string($payload) && $payload !== '' ? $payload : null;
    }

    /**
     * Remember that the report is done, whatever the outcome.
     *
     * @param ReportsTable    $reports The table
     * @param EntityInterface $report  The report
     * @param ConsoleIo       $io      The console
     * @param string          $reason  Why it is done without being sent
     */
    protected function markForwarded(
        ReportsTable $reports,
        EntityInterface $report,
        ConsoleIo $io,
        string $reason = '',
    ): void {
        // One UPDATE by key: the report is already stored, and nothing else on
        // the row changes.
        $reports->updateAll(
            ['forwarded_at' => date('Y-m-d H:i:s')],
            ['id' => $report->get('id')],
        );

        if ($reason === '') {
            return;
        }

        $io->verbose('Report ' . $report->get('id') . ' skipped: it ' . $reason);
    }

    /**
     * The configuration is complete enough to send anything.
     *
     * @param mixed $config The Forwarding.Sentry value
     */
    protected function isConfigComplete(mixed $config): bool
    {
        if (! is_array($config)) {
            return false;
        }

        foreach (['base_url', 'project_id', 'key'] as $key) {
            if (($config[$key] ?? '') === '') {
                return false;
            }
        }

        return true;
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
    protected function getReportsTable(): ReportsTable
    {
        // The table locator resolves the class from the name, which the
        // analysis already knows.
        return TableRegistry::getTableLocator()->get('Reports');
    }
}
