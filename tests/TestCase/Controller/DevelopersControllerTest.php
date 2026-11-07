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
use PHPUnit\Framework\Attributes\DataProvider;

use function file_get_contents;
use function json_encode;
use function parse_str;
use function parse_url;

use const DS;
use const PHP_URL_QUERY;

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
     * Register the access token, user and permission answers of GitHub.
     */
    protected function mockSuccessfulGithub(string $permission = 'write'): void
    {
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], json_encode(['access_token' => 'abc'])),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(
                200,
                [],
                (string) file_get_contents(TESTS . 'Fixture' . DS . 'user_response.json'),
            ),
        );
        $this->mockClientGet(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot/permission',
            $this->newClientResponse(
                200,
                [],
                json_encode(['permission' => $permission, 'role_name' => $permission]),
            ),
        );
    }

    /**
     * The login stores a fresh state in the session and asks for the minimal scope.
     */
    public function testLoginStoresStateAndRequestsMinimalScope(): void
    {
        $this->session([]);

        $this->get('developers/login');

        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('https://github.com/login/oauth/authorize', $location);
        $this->assertStringContainsString('developers%2Fcallback', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('public_repo', $query['scope']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $query['state']);
        $this->assertSessionHasKey('oauth_state');
        $this->assertSame($query['state'], $_SESSION['oauth_state']);
    }

    /**
     * A callback without a state must not exchange the authorization code.
     */
    public function testCallbackRejectsMissingState(): void
    {
        $tokenExchanges = 0;
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], json_encode(['access_token' => 'abc'])),
            [
                'match' => static function () use (&$tokenExchanges): bool {
                    $tokenExchanges++;

                    return true;
                },
            ],
        );

        $this->session(['last_page' => 'http://localhost/reports/index']);

        $this->get('developers/callback?code=123123123&state=whatever');

        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
        $this->assertSame(0, $tokenExchanges, 'The authorization code must not be exchanged without a state.');
        $this->assertSessionNotHasKey('Developer.id');
        $this->assertFlashMessageContains('not able to verify');
    }

    /**
     * A callback with a state that does not match the session one is rejected.
     */
    public function testCallbackRejectsMismatchedStateAndConsumesIt(): void
    {
        $tokenExchanges = 0;
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], json_encode(['access_token' => 'abc'])),
            [
                'match' => static function () use (&$tokenExchanges): bool {
                    $tokenExchanges++;

                    return true;
                },
            ],
        );

        $this->session(['oauth_state' => 'expected-state']);

        $this->get('developers/callback?code=123123123&state=attacker-state');

        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
        $this->assertSame(0, $tokenExchanges, 'The authorization code must not be exchanged on a state mismatch.');
        $this->assertSessionNotHasKey('Developer.id');
        $this->assertSessionNotHasKey('oauth_state');
        $this->assertFlashMessageContains('not able to verify');
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

        // Case 1.1 Test no access_token in Github response (with last_page not set in session)
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $emptyAccessTokenResponse),
        );
        $this->session(['oauth_state' => 'known-state', 'last_page' => '']);

        $this->get('developers/callback?code=123123123&state=known-state');
        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Case 1.2 Test no access_token in Github response (with last_page set in session)
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $emptyAccessTokenResponse),
        );
        $this->session(
            [
                'oauth_state' => 'known-state',
                'last_page' => 'http://localhost/notifications',
            ],
        );

        $this->get('developers/callback?code=123123123&state=known-state');
        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Case 2. Non successful response code from Github
        $this->cleanupMockResponses();
        $this->mockClientPost(
            'https://github.com/login/oauth/access_token',
            $this->newClientResponse(200, [], $accessTokenResponse),
        );
        $this->mockClientGet(
            'https://api.github.com/user',
            $this->newClientResponse(404, [], $nonSuccessUserResponse),
        );
        $this->session(
            [
                'oauth_state' => 'known-state',
                'last_page' => 'http://localhost/reports/index',
            ],
        );

        $this->get('developers/callback?code=123123123&state=known-state');
        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => '', 'action' => 'index']);

        // Case 3. Successful response code (new user), check whether session variables are init
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
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot/permission',
            $this->newClientResponse(200, [], json_encode(['permission' => 'read', 'role_name' => 'read'])),
        );
        $this->session(['oauth_state' => 'known-state', 'last_page' => '']);

        $this->get('developers/callback?code=123123123&state=known-state');
        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $this->assertSession(3, 'Developer.id');
        $this->assertSession(true, 'read_only');
        $this->assertSession('abc', 'access_token');
        $this->assertSessionNotHasKey('oauth_state');

        $developer = $this->Developers->get(3);
        $this->assertNull($developer->access_token);
        $this->assertEquals('pma-bot@phpmyadmin.net', $developer->email);

        // Case 4. Successful response code (returning user), check whether session variables are init
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
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot/permission',
            $this->newClientResponse(200, [], json_encode(['permission' => 'write', 'role_name' => 'write'])),
        );
        $this->session(['oauth_state' => 'known-state', 'last_page' => null]);

        $this->get('developers/callback?code=123123123&state=known-state');
        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $this->assertSession(3, 'Developer.id');
        $this->assertSession(false, 'read_only');
        $this->assertSession('abc', 'access_token');

        $developer = $this->Developers->get(3);
        $this->assertEquals(1, $developer->has_commit_access);
        $this->assertNull($developer->access_token);
    }

    /**
     * Only the pages of this application can be used as post-login target.
     *
     * @return array<string, array{string, bool}>
     */
    public static function lastPageProvider(): array
    {
        return [
            'external absolute url' => ['https://evil.example/steal', false],
            'protocol relative url' => ['//evil.example/steal', false],
            'url without a host' => ['javascript:alert(1)', false],
            'same host absolute url' => ['http://localhost/notifications', true],
            'relative path' => ['/notifications', true],
        ];
    }

    #[DataProvider('lastPageProvider')]
    public function testCallbackOnlyRedirectsInsideTheApp(string $lastPage, bool $allowed): void
    {
        $this->mockSuccessfulGithub();
        $this->session(['oauth_state' => 'known-state', 'last_page' => $lastPage]);

        $this->get('developers/callback?code=123123123&state=known-state');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        if ($allowed) {
            $this->assertStringContainsString($lastPage, $location);
        } else {
            $this->assertStringNotContainsString('evil.example', $location);
            $this->assertRedirect(['controller' => 'reports', 'action' => 'index']);
        }
    }

    /**
     * Test logout method
     */
    public function testLogout(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);
        $this->enableCsrfToken();

        $this->post('developers/logout');
        $this->assertResponseEmpty();
        $this->assertResponseCode(302);
        $this->assertSession(null, 'Developer.id');
        $this->assertRedirect(['controller' => '', 'action' => 'index']);
    }

    /**
     * A link on another site must not be able to sign the developer out.
     */
    public function testLogoutRefusesGet(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => false]);

        $this->get('developers/logout');

        $this->assertResponseCode(405);
        $this->assertSession(1, 'Developer.id');
    }
}
