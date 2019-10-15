<?php

declare(strict_types=1);

namespace Loom\Seam\Exception;

use DomainException;

class NextHandlerException extends DomainException implements ExceptionInterface
{
    public static function create(): self
    {
        return new self('Cannot invoke queue handler $handler->handle() more than once');
    }
}
