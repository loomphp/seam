<?php

declare(strict_types=1);

namespace Loom\Seam;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface SeamMiddlewareInterface extends MiddlewareInterface, RequestHandlerInterface
{
    /**
     * Attach middleware to the queue.
     *
     * @param MiddlewareInterface $middleware
     */
    public function stitch(MiddlewareInterface $middleware): void;
}
