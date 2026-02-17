<?php

declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Middleware\CsrfProtectionMiddleware as CakeCsrfProtectionMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @see https://stackoverflow.com/a/79890247/5155484
 */
class CsrfProtectionMiddleware extends CakeCsrfProtectionMiddleware
{
    /**
     * Process the request
     *
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The request handler.
     * @return ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestRoute = $request->getUri()->getPath();
        if ($requestRoute === '/incidents/create') {
            return $handler->handle($request);
        }

        return parent::process(
            request: $request,
            handler: $handler
        );
    }
}
