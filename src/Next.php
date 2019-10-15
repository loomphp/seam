<?php

declare(strict_types=1);

namespace Loom\Seam;

use Loom\Seam\Exception\NextHandlerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

final class Next implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $fallbackHandler;

    /**
     * @var null|SplQueue
     */
    private $queue;

    /**
     * Clones the queue provided to allow re-use.
     *
     * @param SplQueue $queue
     * @param RequestHandlerInterface $fallbackHandler Fallback handler to
     *     invoke when the queue is exhausted.
     */
    public function __construct(SplQueue $queue, RequestHandlerInterface $fallbackHandler)
    {
        $this->queue = clone $queue;
        $this->fallbackHandler = $fallbackHandler;
    }

    /**
     * Handles a request and produces a response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->queue === null) {
            throw NextHandlerException::create();
        }

        if ($this->queue->isEmpty()) {
            $this->queue = null;
            return $this->fallbackHandler->handle($request);
        }

        $middleware = $this->queue->dequeue();
        $next = clone $this;
        $this->queue = null;

        return $middleware->process($request, $next);
    }
}
