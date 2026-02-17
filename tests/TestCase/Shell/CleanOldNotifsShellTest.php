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

use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\NotificationsFixture;
use Cake\Command\Command;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Shell\CleanOldNotifsShell Test Case
 */
class CleanOldNotifsShellTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
        ];
    }

    /**
     * Test execute method
     */
    public function testExecute(): void
    {
        $Notifications = $this->getTableLocator()->get('Notifications');

        $conditions = ['developer_id' => 1];
        $currentNotificationCount
            = $Notifications->find('all')->where($conditions)->count();
        $this->assertEquals(2, $currentNotificationCount);

        // Run the shell command
        $this->exec('clean_old_notifs');
        $this->assertExitCode(Command::CODE_SUCCESS);

        $newNotificationCount
            = $Notifications->find('all')->where($conditions)->count();
        $this->assertEquals(0, $newNotificationCount);

        // Run shell command to generate zero notifications error
        $this->exec('clean_old_notifs');
        $this->assertExitCode(Command::CODE_SUCCESS);
    }
}
