<?php

/**
 * Tests for Stats Shell.
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

namespace App\Test\TestCase\Shell;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Shell\StatsShell Test Case
 */
class StatsShellTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Test execute method
     */
    public function testExecute(): void
    {
        // Clear the existing cache
        Cache::clear();

        // Run the shell command
        $this->exec('stats');
        $this->assertExitCode(Command::CODE_SUCCESS);
        $Incidents = $this->getTableLocator()->get('Incidents');
        foreach ($Incidents->filterTimes as $filter_string => $filter) {
            foreach ($Incidents->summarizableFields as $field) {
                // Make sure all the fields are covered
                $this->assertNotEquals(
                    false,
                    Cache::read($field . '_' . $filter_string)
                );
            }

            // Make sure download stats value stored
            $this->assertNotEquals(
                false,
                Cache::read('downloadStats_' . $filter_string)
            );
        }
    }
}
