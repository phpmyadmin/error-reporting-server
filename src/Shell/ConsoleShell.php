<?php

/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org).
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      http://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 */

namespace App\Shell;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Log\Log;
use Psy\Shell as PsyShell;

use function class_exists;
use function restore_error_handler;
use function restore_exception_handler;

/**
 * Simple console wrapper around Psy\Shell.
 */
class ConsoleShell extends Command
{
    protected const NAME = 'console';

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
            ->setDescription('Open an interactive console');
    }

    /**
     * Start the shell and interactive console.
     *
     * @return int|void
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        if (! class_exists(PsyShell::class)) {
            $io->err('<error>Unable to load Psy\Shell.</error>');
            $io->err('');
            $io->err('Make sure you have installed psysh as a dependency,');
            $io->err('and that Psy\Shell is registered in your autoloader.');
            $io->err('');
            $io->err('If you are using composer run');
            $io->err('');
            $io->err('<info>$ php composer.phar require --dev psy/psysh</info>');
            $io->err('');

            return 1;
        }

        $io->out('You can exit with <info>`CTRL-C`</info> or <info>`exit`</info>');
        $io->out('');

        Log::drop('debug');
        Log::drop('error');
        $io->setLoggers(false);
        restore_error_handler();
        restore_exception_handler();

        $psy = new PsyShell();
        $psy->run();
    }

    /**
     * Display help for this console.
     *
     * @return ConsoleOptionParser The console option
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $parser = new ConsoleOptionParser($this->name, false);
        $parser->setDescription(
            'This shell provides a REPL that you can use to interact ' .
            'with your application in an interactive fashion. You can use ' .
            'it to run adhoc queries with your models, or experiment ' .
            'and explore the features of CakePHP and your application.' .
            "\n\n" .
            'You will need to have psysh installed for this Shell to work.'
        );

        return $parser;
    }
}
