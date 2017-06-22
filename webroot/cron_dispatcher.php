<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Cron dispatcher
 * The dispatcher used with cron jobs, used to invoke controller actions via CLI.
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

require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Log\Log;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Routing\DispatcherFactory;
use Cake\Routing\Router;

if ((getcwd() == dirname(__FILE__))) {
    // to stop attempts to execute this script directly
    // header() is used because Router::redirect('/') is not working here!!
    Router::redirect('/');
}

if ($argc < 2) {
    die(
        "FATAL ERROR: Not enough arguments!!"
        . "\nCorrect Usage: <php interpreter> webroot/cron_dispatcher.php <target>\n"
    );
}

Log::debug(
    'STARTED: Job "'
        . $argv[1]
        . '" at '
        . date('d-m-Y G:i:s (e)'),
    ['scope' => 'cron_jobs']
);


define('CRON_DISPATCHER', true);
if (PHP_SAPI === 'cli' && $argc == 2) {
    $dispatcher = DispatcherFactory::create();

    $request = new Request($argv[1]);
    $request = $request->addParams(
        Router::parse(
            $request->url,
            ''
        )
    );
    $dispatcher->dispatch(
        $request,
        new Response()
    );
}
else {
    exit;
}

Log::debug(
    'FINISHED: Job "'
        . $argv[1]
        . '" at '
        . date('d-m-Y G:i:s (e)'),
    ['scope' => 'cron_jobs']
);
