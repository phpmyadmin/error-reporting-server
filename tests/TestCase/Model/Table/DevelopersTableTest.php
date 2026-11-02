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

use App\Model\Table\DevelopersTable;
use App\Test\Fixture\DevelopersFixture;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\DevelopersTable Test Case
 */
class DevelopersTableTest extends TestCase
{
    protected DevelopersTable $Developers;

    public function getFixtures(): array
    {
        return [
            DevelopersFixture::class,
        ];
    }

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
                // The developer that exists has to be the one of that id, the
                // exception of get() is what tells that the id is free.
                $this->assertSame($i, $savedDeveloper->get('id'));
            } catch (RecordNotFoundException) {
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
        $developer = $this->Developers->newEmptyEntity();

        $this->Developers->saveFromGithub($githubInfo, $developer);

        $savedDeveloper = $this->Developers->get($nextId);
        $this->assertNotEquals(null, $savedDeveloper);
        $this->assertEquals(1231231, $savedDeveloper->github_id);
        $this->assertEquals('PMA BOT', $savedDeveloper->full_name);
        $this->assertEquals('pma-bot@phpmyadmin.net', $savedDeveloper->email);
        $this->assertEquals(false, $savedDeveloper->has_commit_access);
        $this->assertNull($savedDeveloper->access_token, 'The access token must not be stored in plain text.');

        $updatedGithubInfo = [
            'login' => 'pma-bot',
            'id' => 1231231,
            'gravatar_id' => '',
            'url' => 'https://api.github.com/users/pma-bot',
            'name' => 'PMA BOT',
            'email' => 'pma-bot@phpmyadmin.net',
            'has_commit_access' => 1,// changed
        ];

        $this->Developers->saveFromGithub($updatedGithubInfo, $developer);

        $savedDeveloper = $this->Developers->get($nextId);
        $this->assertNotEquals(null, $savedDeveloper);
        $this->assertEquals(1231231, $savedDeveloper->github_id);
        $this->assertEquals('PMA BOT', $savedDeveloper->full_name);
        $this->assertEquals('pma-bot@phpmyadmin.net', $savedDeveloper->email);
        $this->assertEquals(true, $savedDeveloper->has_commit_access);
    }

    /**
     * The login does not ask for user:email anymore, so GitHub leaves the key
     * out when the address of the developer is private.
     */
    public function testSaveFromGithubWithoutAnEmail(): void
    {
        $developer = $this->Developers->newEmptyEntity();

        $id = $this->Developers->saveFromGithub([
            'name' => 'Private Email',
            'gravatar_id' => 'abc',
            'id' => 4242,
            'has_commit_access' => false,
        ], $developer);

        $this->assertNotNull($id);
        $this->assertNull($this->Developers->get($id)->get('email'));
    }
}
