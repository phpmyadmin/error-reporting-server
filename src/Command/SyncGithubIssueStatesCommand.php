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

use App\Application;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\Log\Log;

use function date;

use const PHP_SAPI;

class SyncGithubIssueStatesCommand extends Command
{
    public static function defaultName(): string
    {
        return 'sync_github_issue_states';
    }

    public static function getDescription(): string
    {
        return 'Sync Github issue states';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setCommand($this->name)
            ->setDescription('Sync GitHub issues states');
    }

    /**
     * @return int
     */
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

        $session = Session::create();
        $session->write('Developer.id', 1);
        $request = new ServerRequest([
            'url' => '/github/sync_issue_status',
            'params' => [
                'controller' => 'Github',
                'action' => 'sync_issue_status',
            ],
            'session' => $session,
        ]);

        $server = new Application('');
        $response = $server->handle($request);
        if ($response->getStatusCode() === 200) {
            Log::debug(
                'FINISHED: Job "'
                    . 'github/sync_issue_status'
                    . '" at '
                    . date('d-m-Y G:i:s (e)'),
                ['scope' => 'cron_jobs']
            );

            return self::CODE_SUCCESS;
        }

        Log::error(
            'ERROR: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)')
                . ' response code: ' . $response->getStatusCode(),
            ['scope' => 'cron_jobs']
        );

        return self::CODE_ERROR;
    }
}
