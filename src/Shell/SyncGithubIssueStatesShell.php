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

use App\Application;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use const PHP_SAPI;
use function date;

/**
 * Sync Github issue states Shell.
 */
class SyncGithubIssueStatesShell extends Command
{
    protected const NAME = 'sync_github_issue_states';

    /**
     * The name of this command.
     *
     * @var string
     */
    protected $name = self::NAME;

    public static function defaultName(): string
    {
        return self::NAME;
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setCommand($this->name)
            ->setDescription('Sync GitHub issues states');
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

        $server = new Application('');
        $server->handle($request);

        Log::debug(
            'FINISHED: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)'),
            ['scope' => 'cron_jobs']
        );
    }
}
