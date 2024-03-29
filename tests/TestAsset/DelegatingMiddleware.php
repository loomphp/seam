<?php

declare(strict_types=1);

namespace LoomTest\Seam\TestAsset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DelegatingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $req, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($req);
    }
}
