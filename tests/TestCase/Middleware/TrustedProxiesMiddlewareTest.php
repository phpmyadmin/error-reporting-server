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

namespace App\Test\TestCase\Middleware;

use App\Middleware\TrustedProxiesMiddleware;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The address of a client is read from the connection, or from the forwarding
 * header of a proxy that is listed in Security.trustedProxies.
 */
class TrustedProxiesMiddlewareTest extends TestCase
{
    public function tearDown(): void
    {
        Configure::delete('Security.trustedProxies');

        parent::tearDown();
    }

    /**
     * Run a request through the middleware.
     *
     * @param array<string, string> $server The server variables of the request
     *
     * @return ServerRequest The request the next layer received
     */
    protected function throughMiddleware(array $server): ServerRequest
    {
        $request = new ServerRequest(['environment' => $server]);

        $handler = new class implements RequestHandlerInterface {
            public ServerRequestInterface|null $seen = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seen = $request;

                return new Response();
            }
        };

        (new TrustedProxiesMiddleware())->process($request, $handler);

        $this->assertInstanceOf(ServerRequest::class, $handler->seen);

        return $handler->seen;
    }

    public function testTheForwardedHeaderIsIgnoredWhenNoProxyIsTrusted(): void
    {
        Configure::write('Security.trustedProxies', []);

        $request = $this->throughMiddleware([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $this->assertSame('10.0.0.2', $request->clientIp(), 'the proxy address is the client');
    }

    public function testTheForwardedHeaderIsBelievedForATrustedProxy(): void
    {
        Configure::write('Security.trustedProxies', ['10.0.0.2']);

        $request = $this->throughMiddleware([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $this->assertSame('203.0.113.9', $request->clientIp(), 'the setting must reach the request');
    }

    public function testTheSettingThatIsNotThereChangesNothing(): void
    {
        Configure::delete('Security.trustedProxies');

        $request = $this->throughMiddleware([
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $this->assertSame('10.0.0.2', $request->clientIp());
    }
}
