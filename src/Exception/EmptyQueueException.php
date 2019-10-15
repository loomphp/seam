<?php

declare(strict_types=1);

namespace Loom\Seam\Exception;

use OutOfBoundsException;
use function sprintf;

class EmptyQueueException extends OutOfBoundsException implements ExceptionInterface
{
    public static function forClass(string $className): self
    {
        return new self(sprintf(
            '%s cannot handle request; no middleware available to process the request',
            $className
        ));
    }
}
