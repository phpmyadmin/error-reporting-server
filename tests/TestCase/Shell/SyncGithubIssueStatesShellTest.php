<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Tests for Sync Github Issue States Shell.
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

use App\Shell\SyncGithubIssueStatesShell;
use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;

/**
 * App\Shell\SyncGithubIssueStatesShell Test Case
 */
class SyncGithubIssueStatesShellTest extends TestCase
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
     * @var \App\Shell\SyncGithubIssueStatesShell
     */
    public $SyncGithubIssueStates;

    public $fixtures = array(
        'app.reports'
    );

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->io = $this->getMockBuilder('Cake\Console\ConsoleIo')->getMock();
        $this->SyncGithubIssueStates = new SyncGithubIssueStatesShell($this->io);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->SyncGithubIssueStates);

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
        $this->SyncGithubIssueStates->initialize();

        $conditions = array(
            'status' => 'resolved'
        );
        $reportsTable = TableRegistry::get('Reports');
        $currentCount = $reportsTable->find('all')->where($conditions)->count();
        $this->assertEquals(0, $currentCount);

        // Run the shell command
        $this->SyncGithubIssueStates->main();

        $newCount = $reportsTable->find('all')->where($conditions)->count();
        $this->assertEquals(1, $newCount);
    }
}
