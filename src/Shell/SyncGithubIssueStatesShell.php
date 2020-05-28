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

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Http\ServerRequest;
use Cake\Http\Response;
use Cake\Routing\DispatcherFactory;
use Cake\Routing\Router;

/**
 * Sync Github issue states Shell.
 */
class SyncGithubIssueStatesShell extends Shell
{
    public function initialize()
    {
        parent::initialize();
    }

    public function main()
    {
        Log::debug(
            'STARTED: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)'),
            ['scope' => 'cron_jobs']
        );


        Configure::write('CronDispatcher', true);
        if (PHP_SAPI === 'cli') {
            $dispatcher = DispatcherFactory::create();

            $request = new ServerRequest('github/sync_issue_status');
            $request = $request->addParams(
                Router::parse(
                    $request->getPath(),
                    ''
                )
            );
            $dispatcher->dispatch(
                $request,
                new Response()
            );
        } else {
            exit;
        }

        Log::debug(
            'FINISHED: Job "'
                . 'github/sync_issue_status'
                . '" at '
                . date('d-m-Y G:i:s (e)'),
            ['scope' => 'cron_jobs']
        );
    }
}
