<?php

declare(strict_types=1);

namespace Loom\Seam\Handler;

use Loom\Seam\Exception\EmptyQueueException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class EmptyQueueHandler implements RequestHandlerInterface
{
    /**
     * @var string
     */
    private $className;

    /**
     * @param string $className
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * Handles a request and produces a response.
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw EmptyQueueException::forClass($this->className);
    }
}
