<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

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
use Cake\TestSuite\TestCase;
use Cake\Cache\Cache;

/**
 * App\Shell\StatsShell Test Case
 */
class StatsShellTest extends TestCase
{

    /**
     * ConsoleIo mock
     *
     * @var \Cake\Console\ConsoleIo|\PHPUnit_Framework_MockObject_MockObject
     */
    public $io;

    /**
     * Test subject
     *
     * @var \App\Shell\StatsShell
     */
    public $Stats;

    public $fixtures = [
        'app.Incidents'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->Stats = new StatsShell($this->io);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Stats);

        parent::tearDown();
    }

    /**
     * Test main method
     *
     * @return void
     */
    public function testMain()
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
