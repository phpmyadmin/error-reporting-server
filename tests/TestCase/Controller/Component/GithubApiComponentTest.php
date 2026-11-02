<?php

/**
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

namespace App\Test\TestCase\Controller\Component;

use App\Controller\Component\GithubApiComponent;
use Cake\Controller\ComponentRegistry;
use Cake\Http\TestSuite\HttpClientTrait;
use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function is_array;
use function json_encode;

/**
 * Tests for the write-access check of the GitHub API component.
 */
class GithubApiComponentTest extends TestCase
{
    use HttpClientTrait;

    private const string PERMISSION_URL
        = 'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot/permission';

    protected GithubApiComponent $GithubApi;

    private ArrayLog $logCapture;

    private mixed $originalErrorLogConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadRoutes();
        $this->GithubApi = new GithubApiComponent(new ComponentRegistry());

        $this->originalErrorLogConfig = Log::getConfig('error');
        Log::drop('error');
        Log::setConfig('error', [
            'className' => ArrayLog::class,
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ]);
        $errorLog = Log::engine('error');
        $this->assertInstanceOf(ArrayLog::class, $errorLog);
        $this->logCapture = $errorLog;
        $this->logCapture->clear();
    }

    public function tearDown(): void
    {
        $this->cleanupMockResponses();

        Log::drop('error');
        if (is_array($this->originalErrorLogConfig)) {
            Log::setConfig('error', $this->originalErrorLogConfig);
        }

        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function writePermissionProvider(): array
    {
        return [
            'admin' => ['admin'],
            'maintain' => ['maintain'],
            'write' => ['write'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function readOnlyPermissionProvider(): array
    {
        return [
            'read' => ['read'],
            'triage' => ['triage'],
            'none' => ['none'],
            'unknown' => ['some-new-role'],
        ];
    }

    /**
     * A collaborator with a writing permission is allowed to commit.
     */
    #[DataProvider('writePermissionProvider')]
    public function testCanCommitToAllowsWritingPermissions(string $permission): void
    {
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(200, [], json_encode(['permission' => $permission, 'role_name' => $permission])),
        );

        $this->assertTrue($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
        $this->assertSame([], $this->logCapture->read());
    }

    /**
     * A collaborator with a read only permission may not commit.
     *
     * The permission endpoint is the only one called: the collaborators endpoint
     * answers 204 for every collaborator, read-only ones included.
     */
    #[DataProvider('readOnlyPermissionProvider')]
    public function testCanCommitToRejectsReadOnlyPermissions(string $permission): void
    {
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(200, [], json_encode(['permission' => $permission, 'role_name' => $permission])),
        );

        $this->assertFalse($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
    }

    /**
     * Role names are accepted when the permission field is not available.
     */
    public function testCanCommitToUsesRoleNameWhenPermissionIsMissing(): void
    {
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(200, [], json_encode(['role_name' => 'write'])),
        );

        $this->assertTrue($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
    }

    /**
     * Any answer that is not a 200 means no access and is logged.
     */
    public function testCanCommitToRejectsAndLogsNonOkAnswers(): void
    {
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(404, [], json_encode(['message' => 'Not Found'])),
        );

        $this->assertFalse($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));

        $logs = $this->logCapture->read();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('404', $logs[0]);
    }

    /**
     * A server error is not mistaken for a granted permission either.
     */
    public function testCanCommitToRejectsServerError(): void
    {
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(500, [], json_encode(['message' => 'Server Error'])),
        );

        $this->assertFalse($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
        $this->assertCount(1, $this->logCapture->read());
    }

    /**
     * The collaborator endpoint must not be used any more.
     */
    public function testCanCommitToDoesNotCallTheCollaboratorEndpoint(): void
    {
        $this->mockClientGet(
            'https://api.github.com/repos/phpmyadmin/phpmyadmin/collaborators/pma-bot',
            $this->newClientResponse(204, [], ''),
        );
        $this->mockClientGet(
            self::PERMISSION_URL,
            $this->newClientResponse(200, [], json_encode(['permission' => 'read'])),
        );

        $this->assertFalse($this->GithubApi->canCommitTo('pma-bot', 'phpmyadmin/phpmyadmin', 'token'));
    }

    /**
     * The authorize url can carry the OAuth state.
     */
    public function testGetRedirectUrlContainsTheState(): void
    {
        $url = $this->GithubApi->getRedirectUrl('public_repo', 'the-state');

        $this->assertStringContainsString('state=the-state', $url);
    }
}
