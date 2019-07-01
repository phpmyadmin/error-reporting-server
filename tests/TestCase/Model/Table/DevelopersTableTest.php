<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Tests for Developer Table model
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
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\DevelopersTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\DevelopersTable Test Case
 */
class DevelopersTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\DevelopersTable
     */
    public $Developers;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.Developers'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Developers = TableRegistry::get('Developers');
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Developers);

        parent::tearDown();
    }

    /**
     * Test saveFromGithub method
     *
     * @return void
     */
    public function testSaveFromGithub()
    {
        $i = 0;
        while (1) {
            try {
                $i++;
                $savedDeveloper = $this->Developers->get($i);
            } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
                break;
            }
        }

        $nextId = $i;

        $githubInfo = [
            'login' => 'pma-bot',
            'id' => 1231231,
            'gravatar_id' => '',
            'url' => 'https://api.github.com/users/pma-bot',
            'name' => 'PMA BOT',
            'email' => 'pma-bot@phpmyadmin.net',
            'has_commit_access' => 0,
        ];
        $developer = $this->Developers->newEntity();
        $access_token = 'abc';

        $this->Developers->saveFromGithub($githubInfo, $access_token, $developer);

        $savedDeveloper = $this->Developers->get($nextId);
        $this->assertNotEquals(null, $savedDeveloper);
        $this->assertEquals(1231231, $savedDeveloper->github_id);
        $this->assertEquals('PMA BOT', $savedDeveloper->full_name);
        $this->assertEquals('pma-bot@phpmyadmin.net', $savedDeveloper->email);
        $this->assertEquals(false, $savedDeveloper->has_commit_access);

        $updatedGithubInfo = [
            'login' => 'pma-bot',
            'id' => 1231231,
            'gravatar_id' => '',
            'url' => 'https://api.github.com/users/pma-bot',
            'name' => 'PMA BOT',
            'email' => 'pma-bot@phpmyadmin.net',
            'has_commit_access' => 1,// changed
        ];

        $this->Developers->saveFromGithub($updatedGithubInfo, $access_token, $developer);

        $savedDeveloper = $this->Developers->get($nextId);
        $this->assertNotEquals(null, $savedDeveloper);
        $this->assertEquals(1231231, $savedDeveloper->github_id);
        $this->assertEquals('PMA BOT', $savedDeveloper->full_name);
        $this->assertEquals('pma-bot@phpmyadmin.net', $savedDeveloper->email);
        $this->assertEquals(true, $savedDeveloper->has_commit_access);
    }
}
