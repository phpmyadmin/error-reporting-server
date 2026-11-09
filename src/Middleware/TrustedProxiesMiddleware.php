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

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Tells the request which proxies may be believed.
 *
 * The address of a client salts the identity of a report and is recorded with
 * a login. The framework reads ServerRequest::$trustProxy, which nothing sets
 * on its own: without this middleware the Security.trustedProxies setting does
 * nothing and the address is always the one of the proxy.
 */
class TrustedProxiesMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trustedProxies = (array) Configure::read('Security.trustedProxies');
        if ($trustedProxies !== [] && $request instanceof ServerRequest) {
            $request->trustProxy = true;
            $request->setTrustedProxies($trustedProxies);
        }

        return $handler->handle($request);
    }
}
