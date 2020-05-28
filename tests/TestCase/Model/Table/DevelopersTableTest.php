<?php

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

use Cake\Datasource\Exception\RecordNotFoundException;
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
     * @var DevelopersTable
     */
    public $Developers;

    /**
     * Fixtures.
     *
     * @var array
     */
    public $fixtures = ['app.Developers'];

    /**
     * setUp method
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->Developers = TableRegistry::getTableLocator()->get('Developers');
    }

    /**
     * tearDown method
     */
    public function tearDown(): void
    {
        unset($this->Developers);

        parent::tearDown();
    }

    /**
     * Test saveFromGithub method
     */
    public function testSaveFromGithub(): void
    {
        $i = 0;
        while (1) {
            try {
                $i++;
                $savedDeveloper = $this->Developers->get($i);
            } catch (RecordNotFoundException $e) {
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
