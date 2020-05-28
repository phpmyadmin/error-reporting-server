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

use App\Shell\StatsShell;
use Cake\Cache\Cache;
use Cake\Console\ConsoleIo;
use Cake\TestSuite\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * App\Shell\StatsShell Test Case
 */
class StatsShellTest extends TestCase
{
    /**
     * ConsoleIo mock
     *
     * @var ConsoleIo|PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var StatsShell
     */
    public $Stats;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = ['app.Incidents'];

    /**
     * setUp method
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->Stats = new StatsShell($this->io);
    }

    /**
     * tearDown method
     */
    public function tearDown(): void
    {
        unset($this->Stats);

        parent::tearDown();
    }

    /**
     * Test main method
     */
    public function testMain(): void
    {
        // Call intialize method to load the models
        $this->Stats->initialize();

        // Clear the existing cache
        Cache::clear(false);

        // Run the shell command
        $this->Stats->main();

        foreach ($this->Stats->Incidents->filterTimes as $filter_string => $filter) {
            foreach ($this->Stats->Incidents->summarizableFields as $field) {
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
