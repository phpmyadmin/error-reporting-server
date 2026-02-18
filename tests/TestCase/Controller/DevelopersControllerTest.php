<?php

/**
 * Tests for Developers Controller
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

namespace App\Test\TestCase\Controller;

use App\Model\Table\DevelopersTable;
use App\Test\Fixture\DevelopersFixture;
use App\Test\Fixture\NotificationsFixture;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use phpmock\phpunit\PHPMock;

use function file_get_contents;
use function json_encode;

use const DS;

/**
 * App\Controller\DevelopersController Test Case
 */
class DevelopersControllerTest extends TestCase
{
    use PHPMock;
    use IntegrationTestTrait;
    use HttpClientTrait;

    protected DevelopersTable $Developers;

    public function getFixtures(): array
    {
        return [
            NotificationsFixture::class,
            DevelopersFixture::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->Developers = TableRegistry::getTableLocator()->get('Developers');
    }

    /**
     * Test login method
     */
    public function testLogin(): void
    {
        // Empty session during initiation
        $this->session([]);

        $this->get('developers/login');
        $this->assertRedirectContains('https://github.com/login/oauth/authorize');
        $this->assertRedirectContains('developers%2Fcallback');
    }

    /**
     * Test callback method
     */
    public function testCallback(): void
    {
        $accessTokenResponse = json_encode(['access_token' => 'abc']);
        $emptyAccessTokenResponse = json_encode(['access_token' => null]);

        $nonSuccessUserResponse = json_encode(['message' => 'Unauthorized access']);
        $userResponse = file_get_contents(TESTS . 'Fixture' . DS . 'user_response.json');

        // Data for 1.1
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(401, [], $emptyAccessTokenResponse),
        );
        // Case 1.1 Test no access_token in Github response (with last_page not set in session)
        // So, empty the session
        $this->session([]);

        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $emptyAccessTokenResponse),
        );
        $this->get('developers/callback/?code=123123123');
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Data for 1.2
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $accessTokenResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(404, [], $nonSuccessUserResponse),
        );
        // Case 1.2 Test no access_token in Github response (with last_page set in session)
        $this->session(
            [
                'last_page' => [
                    'controller' => 'notifications',
                    'action' => 'index',
                ],
            ]
        );

        $this->get('developers/callback/?code=123123123');
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Data for 2.
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $accessTokenResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(404, [], $nonSuccessUserResponse),
        );
        // Case 2. Non successful response code from Github
        $this->session(
            [
                'last_page' => [
                    'controller' => 'reports',
                    'action' => 'index',
                ],
            ]
        );
        $this->get('developers/callback/?code=123123123');
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Data for 3.
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $accessTokenResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(200, [], $userResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot',
            $this->newClientResponse(200, [], json_encode([])),
        );

        // Case 3. Successful response code (new user), check whether session variables are init
        $this->get('developers/callback/?code=123123123');
        $this->assertSession(3, 'Developer.id');
        $this->assertSession(true, 'read_only');
        $this->assertSession('abc', 'access_token');

        $developer = $this->Developers->get(3);
        $this->assertEquals('abc', $developer->access_token);
        $this->assertEquals('pma-bot@phpmyadmin.net', $developer->email);

        // Data for 4.
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $accessTokenResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(200, [], $userResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot',
            $this->newClientResponse(204, [], json_encode([])),
        );
        // Case 4. Successful response code (returning user)
        // check whether session variables are init
        $this->session(['last_page' => null]);

        $this->get('developers/callback/?code=123123123');
        $this->assertSession(3, 'Developer.id');
        $this->assertSession(false, 'read_only');
        $this->assertSession('abc', 'access_token');

        $developer = $this->Developers->get(3);
        $this->assertEquals(1, $developer->has_commit_access);
    }

    /**
     * Test logout method
     */
    public function testLogout(): void
    {
        $this->session(['Developer.id' => 1]);

        $this->get('developers/logout');
        $this->assertSession(null, 'Developer.id');
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
    }
}
