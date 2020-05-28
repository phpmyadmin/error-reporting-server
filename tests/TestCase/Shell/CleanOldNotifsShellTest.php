<?php

/**
 * Tests for Clean Old Notifications Shell.
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

use App\Shell\CleanOldNotifsShell;
use Cake\Console\ConsoleIo;
use Cake\TestSuite\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * App\Shell\CleanOldNotifsShell Test Case
 */
class CleanOldNotifsShellTest extends TestCase
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
     * @var CleanOldNotifsShell
     */
    public $CleanOldNotifs;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = [
        'app.Notifications',
        'app.Developers',
    ];

    /**
     * setUp method
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->CleanOldNotifs = new CleanOldNotifsShell($this->io);
    }

    /**
     * tearDown method
     */
    public function tearDown(): void
    {
        unset($this->CleanOldNotifs);

        parent::tearDown();
    }

    /**
     * Test main method
     */
    public function testMain(): void
    {
        // Call intialize method to load the models
        $this->CleanOldNotifs->initialize();

        $conditions = ['developer_id' => 1];
        $currentNotificationCount
            = $this->CleanOldNotifs->Notifications->find('all')->where($conditions)->count();
        $this->assertEquals(2, $currentNotificationCount);

        // Run shell command
        $this->CleanOldNotifs->main();

        $newNotificationCount
            = $this->CleanOldNotifs->Notifications->find('all')->where($conditions)->count();
        $this->assertEquals(0, $newNotificationCount);

        // Run shell command to generate zero notifications error
        $this->CleanOldNotifs->main();
    }
}
