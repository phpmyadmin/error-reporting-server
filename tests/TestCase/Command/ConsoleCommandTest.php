<?php

/**
 * Tests for the Console command.
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

namespace App\Test\TestCase\Command;

use App\Command\ConsoleCommand;
use Cake\Command\Command;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function spl_autoload_functions;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function str_starts_with;

/**
 * App\Command\ConsoleCommand Test Case
 */
class ConsoleCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function testDefaultName(): void
    {
        $this->assertSame('console', ConsoleCommand::defaultName());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('Simple console wrapper around Psy\Shell', ConsoleCommand::getDescription());
    }

    public function testGetOptionParser(): void
    {
        $command = new ConsoleCommand();

        $parser = $command->getOptionParser();

        $this->assertSame(
            'This shell provides a REPL that you can use to interact '
            . 'with your application in an interactive fashion. You can use '
            . 'it to run adhoc queries with your models, or experiment '
            . 'and explore the features of CakePHP and your application.' . "\n\n"
            . 'You will need to have psysh installed for this Shell to work.',
            $parser->getDescription(),
        );
    }

    public function testBuildOptionParser(): void
    {
        $command = new class extends ConsoleCommand {
            public function buildParserForTest(ConsoleOptionParser $parser): ConsoleOptionParser
            {
                return $this->buildOptionParser($parser);
            }
        };
        $command->setName('cake console');

        $parser = $command->buildParserForTest(new ConsoleOptionParser('console', false));

        $this->assertSame('cake console', $command->getName());
        $this->assertSame('cake console', $parser->getCommand());
        $this->assertSame('Open an interactive console', $parser->getDescription());
    }

    public function testHelpIsRegistered(): void
    {
        $this->exec('console --help');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('This shell provides a REPL that you can use to interact');
        $this->assertOutputContains('You will need to have psysh installed for this Shell to work.');
        $this->assertOutputContains('Usage:');
    }

    public function testExecuteWithoutPsyShellInstalled(): void
    {
        // Hide the Psy namespace from the autoloaders so that the command
        // behaves as if psysh was not installed.
        $autoloaders = spl_autoload_functions();
        foreach ($autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        spl_autoload_register(static function (string $class) use ($autoloaders): void {
            if (str_starts_with($class, 'Psy\\')) {
                return;
            }

            foreach ($autoloaders as $autoloader) {
                $autoloader($class);
            }
        });

        try {
            $this->exec('console');
        } finally {
            foreach (spl_autoload_functions() as $autoloader) {
                spl_autoload_unregister($autoloader);
            }

            foreach ($autoloaders as $autoloader) {
                spl_autoload_register($autoloader);
            }
        }

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputNotContains('You can exit with');
        $this->assertErrorContains('Unable to load Psy\Shell.');
        $this->assertErrorContains('Make sure you have installed psysh as a dependency,');
        $this->assertErrorContains('and that Psy\Shell is registered in your autoloader.');
        $this->assertErrorContains('If you are using composer run');
        $this->assertErrorContains('$ php composer.phar require --dev psy/psysh');
    }
}
