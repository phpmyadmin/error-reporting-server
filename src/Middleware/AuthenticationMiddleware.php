<?php

declare(strict_types=1);

namespace App\Middleware;

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
     * Process the request
     *
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The request handler.
     * @return ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $redirectResponse = new Response()->withStatus(307)->withHeader('Location', '/');

        assert($request instanceof ServerRequest);

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
            $isRequired = $this->isWriteAccessRequired($request);
            if ($isRequired === true) {
                $request->getSession()->destroy();
                $request->getSession()->write('last_page', '');

                $flash_class = 'alert';
                $request->getFlash()->set(
                    'You need to have commit access on phpmyadmin/phpmyadmin '
                    . 'repository on Github.com to do this',
                    [
                        'params' => ['class' => $flash_class],
                    ]
                );

                // This is a security check
                return $redirectResponse;
            }
        } else {
            $isPublicAccess = $this->isPublicAccess($request);
            if ($isPublicAccess === false) {
                $flash_class = 'alert';
                $request->getFlash()->set(
                    'You need to be signed in to do this',
                    ['params' => ['class' => $flash_class]]
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

    protected function isWriteAccessRequired(ServerRequest $request): bool
    {
        $controllerName = $request->getParam('controller');
        $action = $request->getParam('action');
        $read_only = $request->getSession()->read('read_only');

        // If developer has commit access on phpmyadmin/phpmyadmin
        if ($read_only === false) {
            return false;
        }

        // Check for the controller name
        if (! isset(self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName])) {
            return false;// The controller is not in the list
        }

        // Require for all actions ?
        if (self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName] === '*') {
            return true;// Needs write access
        }

        // Check for the specific action name
        if (in_array($action, self::READ_ONLY_ACCESS_CONTROL_LIST[$controllerName])) {// phpcs:ignore SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn.UselessIfCondition
            return true;// Needs write access
        }

        return false;// phpcs:ignore SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn.UselessIfCondition
    }
}
