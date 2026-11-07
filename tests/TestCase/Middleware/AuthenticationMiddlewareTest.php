<?php

declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Test\Fixture\DevelopersFixture;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AuthenticationMiddlewareTest extends TestCase
{
    use IntegrationTestTrait;

    public function getFixtures(): array
    {
        return [
            DevelopersFixture::class,
        ];
    }

    public function testAnUnknownControllerDoesNotDestroyTheSession(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => true]);

        $this->get('/does-not-exist/index');

        $this->assertResponseCode(403);

        // Reading the session through its Session object after the request
        // would restart it, and a session restart in CLI resets it, so the
        // session storage is inspected instead.
        $this->assertSame(1, $_SESSION['Developer']['id'] ?? null);
        $this->assertSame(true, $_SESSION['read_only'] ?? null);
    }

    public function testAReadOnlyDeveloperIsStillRedirectedFromAProtectedAction(): void
    {
        $this->session(['Developer.id' => 1, 'read_only' => true]);

        $this->get('/stats');

        $this->assertResponseCode(307);
        $this->assertRedirect('/');
        $this->assertFlashMessage(
            'You need to have commit access on phpmyadmin/phpmyadmin repository on Github.com to do this action (Stats:stats)',
        );
    }

    /**
     * The access is decided from the key the login writes now, and a session
     * that still carries the old derived flag must not override it.
     */
    public function testTheCommitAccessOfTheSessionDecides(): void
    {
        $this->session([
            'Developer.id' => 1,
            'has_commit_access' => true,
            'read_only' => true,
        ]);

        $this->get('/stats');

        $this->assertResponseCode(200);
    }

    public function testARevokedCommitAccessIsRefused(): void
    {
        $this->session([
            'Developer.id' => 1,
            'has_commit_access' => false,
            'read_only' => false,
        ]);

        $this->get('/stats');

        $this->assertResponseCode(307);
        $this->assertRedirect('/');
    }
}
