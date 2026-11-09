<?php

declare(strict_types=1);

namespace App\Test\TestCase\Middleware;

use App\Middleware\AuthenticationMiddleware;
use App\Test\Fixture\DevelopersFixture;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

use function time;

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

    /**
     * An answer that cannot be read must not keep the access forever: past the
     * stale limit the developer is read-only until GitHub answers again.
     */
    public function testAnAccessThatCannotBeCheckedExpires(): void
    {
        $this->session([
            'Developer.id' => 1,
            'has_commit_access' => true,
            'has_commit_access_at' => time() - AuthenticationMiddleware::ACCESS_MAX_STALE - 60,
            'access_checked_at' => 1,// long overdue, so the check runs
        ]);

        $this->get('/stats');

        // The denial destroys the session, so the code is the observable: it is
        // a 307 only because the flag was turned to false before the decision.
        $this->assertResponseCode(307);
    }

    /**
     * Before the stale limit, what the session knew is kept: the API may simply
     * be down, which is not a revocation.
     */
    public function testAnAccessThatCannotBeCheckedIsKeptForAWhile(): void
    {
        $this->session([
            'Developer.id' => 1,
            'has_commit_access' => true,
            'has_commit_access_at' => time(),
            'access_checked_at' => 1,
        ]);

        $this->get('/stats');

        $this->assertResponseCode(200);
        $this->assertSession(true, 'has_commit_access');
    }

    /**
     * The access is asked again every half day: a token budget, since a refused
     * token is answered straight away when it is asked.
     */
    public function testTheAccessIsAskedAgainEveryHalfDay(): void
    {
        $this->assertSame(12 * 3600, AuthenticationMiddleware::ACCESS_RECHECK_INTERVAL);
        $this->assertGreaterThan(
            AuthenticationMiddleware::ACCESS_RECHECK_INTERVAL,
            AuthenticationMiddleware::ACCESS_MAX_STALE,
            'the stale limit must be longer than the interval it bounds',
        );
    }

    /**
     * Within the interval nothing is asked: the session keeps its answer.
     */
    public function testTheAccessIsNotAskedAgainWithinTheInterval(): void
    {
        $this->session([
            'Developer.id' => 1,
            'has_commit_access' => true,
            'has_commit_access_at' => time() - 3600,
            'access_checked_at' => time() - 3600,// an hour ago, well inside
        ]);

        $this->get('/stats');

        $this->assertResponseCode(200);
    }
}
