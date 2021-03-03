<?php

/**
 * Sync Github issue states Shell.
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

namespace App\Shell;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\HttpApplicationInterface;
use Cake\Http\Server;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use const PHP_SAPI;
use function date;

/**
 * Sync Github issue states Shell.
 */
class SyncGithubIssueStatesShell extends Command
{
    /**
     * The application that is being dispatched.
     *
     * @var HttpApplicationInterface
     */
    protected $app;

    /**
     * Constructor
     *
     * @param HttpApplicationInterface $app The test case to run.
     */
    public function __construct(HttpApplicationInterface $app)
    {
        $this->app = $app;
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        Log::debug(
            'STARTED: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)'),
            ['scope' => 'cron_jobs']
        );

        Configure::write('CronDispatcher', true);
        if (PHP_SAPI !== 'cli') {
            exit;
        }

        $request = new ServerRequest([
            'url' => '/github/sync_issue_status',
            'params' => [
                'controller' => 'Github',
                'action' => 'sync_issue_status',
            ],
        ]);

        $server = new Server($this->app);
        $server->run($request);

        Log::debug(
            'FINISHED: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)'),
            ['scope' => 'cron_jobs']
        );
    }
}
