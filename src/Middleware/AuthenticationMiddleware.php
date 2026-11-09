<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Github\GithubApi;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function assert;
use function in_array;
use function is_string;
use function time;

class AuthenticationMiddleware implements MiddlewareInterface
{
    private const array NO_ACCESS_CONTROL_LIST = [
        'Developers' => '*',
        'Pages' => '*',
        'Incidents' => ['create'],
        'Events' => '*',
    ];

    private const array READ_ONLY_ACCESS_CONTROL_LIST = [
        'Developers' => '*',
        'Pages' => '*',
        'Reports' => [
            'index',
            'view',
            'data_tables',
        ],
        'Incidents' => ['view'],
    ];

    /**
     * Seconds the answer of GitHub about the access of a developer is kept
     * before it is asked again.
     */
    public const ACCESS_RECHECK_INTERVAL = 900;

    /**
     * Longest time an answer of GitHub is kept when it cannot be asked again.
     *
     * A refusal is now an answer, so this only bounds the case of an API that
     * says nothing for days: past it, the developer is read-only until it
     * answers, rather than keeping the access they had when it last did.
     */
    public const ACCESS_MAX_STALE = 86400;

    /**
     * The API the permission of a developer is read from.
     */
    private GithubApi|null $api = null;

    /**
     * Replace the API, for a test.
     *
     * @param GithubApi $api The API to use
     */
    public function setApi(GithubApi $api): void
    {
        $this->api = $api;
    }

    /**
     * The API of the middleware, built when it is first used.
     */
    protected function getApi(): GithubApi
    {
        return $this->api ??= new GithubApi();
    }

    /**
     * Process the request
     *
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The request handler.
     * @return ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        assert($request instanceof ServerRequest);

        $controllerName = $request->getParam('controller');
        $action = $request->getParam('action');

        if ($controllerName === 'Requests' && $action === 'view') {// cakephp/debug_kit
            return $handler->handle($request);
        }

        $redirectResponse = new Response()->withStatus(307)->withHeader('Location', '/');

        $devId = $request->getSession()->read('Developer.id');

        $currentDeveloper = null;
        if ($devId !== null) {
            // Check if the user still exists in the database
            $currentDeveloper = TableRegistry::getTableLocator()->get('Developers')->
                    findById($devId)->all()->first();

            $request = $request->withAttribute('current_developer', $currentDeveloper);
            unset($devId);// Stop using this variable
        }

        if ($currentDeveloper !== null) {
            $this->refreshCommitAccess($request);

            $hasAccess = $this->hasAccessToAction($request);
            if ($hasAccess === false) {
                $request->getSession()->destroy();
                $request->getSession()->write('last_page', '');

                $flash_class = 'alert';
                $request->getFlash()->set(
                    'You need to have commit access on phpmyadmin/phpmyadmin '
                    . 'repository on Github.com to do this action (' . $controllerName . ':' . $action . ')',
                    [
                        'params' => ['class' => $flash_class],
                    ],
                );

                // This is a security check
                return $redirectResponse;
            }
        } else {
            $isPublicAccess = $this->isPublicAccess($request);
            if ($isPublicAccess === false) {
                $flash_class = 'alert';
                $request->getFlash()->set(
                    'You need to be signed in to do this action (' . $controllerName . ':' . $action . ')',
                    ['params' => ['class' => $flash_class]],
                );

                // save the return url
                $ret_url = Router::url($request->getRequestTarget(), true);
                $request->getSession()->write('last_page', $ret_url);

                // This is a security check
                return $redirectResponse;
            }
        }

        return $handler->handle($request);
    }

    protected function isPublicAccess(ServerRequest $request): bool
    {
        $controllerName = $request->getParam('controller');
        $action = $request->getParam('action');

        // Check for the controller name
        if (! isset(self::NO_ACCESS_CONTROL_LIST[$controllerName])) {
            // Not public
            return false;
        }

        // Allows all actions ?
        if (self::NO_ACCESS_CONTROL_LIST[$controllerName] === '*') {
            return true;
        }

        // Check for the specific action name
        return in_array($action, self::NO_ACCESS_CONTROL_LIST[$controllerName]);
    }

    /**
     * Ask GitHub again what the developer may do, at most once per interval.
     *
     * The access of the developer is decided at login from a single answer of
     * GitHub: left alone, a login stays valid with write access long after the
     * account lost its permission on the repository, and a read-only developer
     * keeps being refused after they were granted it.
     *
     * A failure to ask GitHub keeps what the session already knew.
     *
     * @param ServerRequest $request The request
     */
    protected function refreshCommitAccess(ServerRequest $request): void
    {
        $session = $request->getSession();
        $checkedAt = (int) $session->read('access_checked_at');
        if ($checkedAt > 0 && time() - $checkedAt < self::ACCESS_RECHECK_INTERVAL) {
            return;
        }

        $login = (string) $session->read('github_login');
        $token = (string) $session->read('access_token');
        $repoPath = (string) Configure::read('GithubRepoPath');

        $hasCommitAccess = $this->getApi()->canCommitTo($login, $repoPath, $token);
        if ($hasCommitAccess !== null) {
            $session->write('has_commit_access', $hasCommitAccess);
            $session->write('has_commit_access_at', time());
            $session->write('access_checked_at', time());

            return;
        }

        // The API could not be asked: keep what the session knew, but not
        // forever, since that is the access it had when it last answered.
        $answeredAt = (int) $session->read('has_commit_access_at');
        if ($answeredAt > 0 && time() - $answeredAt > self::ACCESS_MAX_STALE) {
            $session->write('has_commit_access', false);
        }

        // Retry sooner than a completed answer would, the API may be back.
        $session->write('access_checked_at', time() - self::ACCESS_RECHECK_INTERVAL + 60);
    }

    protected function hasAccessToAction(ServerRequest $request): bool
    {
        $controllerName = $request->getParam('controller');
        $action = $request->getParam('action');
        $session = $request->getSession();
        $hasCommitAccess = $session->read('has_commit_access');
        if ($hasCommitAccess === null) {
            // Sessions opened before this key existed.
            $readOnly = $session->read('read_only');
            $hasCommitAccess = $readOnly === null ? false : ! $readOnly;
        }

        // If developer has commit access on phpmyadmin/phpmyadmin
        if ($hasCommitAccess === true) {
            return true;
        }

        // Now we check if the user can access this action in read only mode

        // Check for the controller name
        if (! isset(self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName])) {
            if (! $this->controllerExists($controllerName)) {
                // There is nothing to grant access to: the developer must not
                // be signed out for requesting a controller which does not
                // exist, the request simply ends with a 403.
                throw new ForbiddenException();
            }

            return false;// The controller is not in the list
        }

        // Require for all actions ?
        if (self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName] === '*') {
            return true;// Can access all actions in read only mode
        }

        // Check for the specific action name
        if (in_array($action, self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName])) {// phpcs:ignore SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn.UselessIfCondition
            return true;// Can access this action in read only mode
        }

        return false;// phpcs:ignore SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn.UselessIfCondition
    }

    /**
     * Tells whether the controller name of the request matches a controller
     * class of the application.
     *
     * @param mixed $controllerName the controller name of the request
     *
     * @return bool true when the controller exists
     */
    private function controllerExists(mixed $controllerName): bool
    {
        if (! is_string($controllerName) || $controllerName === '') {
            return false;
        }

        return App::className($controllerName, 'Controller', 'Controller') !== null;
    }
}
