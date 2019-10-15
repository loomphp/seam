<?php

declare(strict_types=1);

namespace Loom\Seam;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplQueue;

final class SeamMiddleware implements SeamMiddlewareInterface
{
    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * Initializes the queue.
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * Perform a deep clone.
     */
    public function __clone()
    {
        $this->queue = clone $this->queue;
    }

    /**
     * Handle an incoming request.
     *
     * If the queue is empty at the time this method is invoked, it will
     * raise an exception.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception\EmptyQueueException if no middleware is present in
     *     the instance in order to process the request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->process($request, new Handler\EmptyQueueHandler(__CLASS__));
    }

    /**
     * Process an incoming server request.
     *
     * Executes the internal queue, passing $handler as the "final
     * handler" in cases when the queue exhausts itself.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return (new Next($this->queue, $handler))->handle($request);
    }

    /**
     * Attach middleware to the queue.
     *
     * @param MiddlewareInterface $middleware
     */
    public function stitch(MiddlewareInterface $middleware): void
    {
        $this->queue->enqueue($middleware);
    }
}
