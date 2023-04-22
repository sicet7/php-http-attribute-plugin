<?php

namespace Sicet7\HTTP\Attributes;

use Psr\Http\Server\MiddlewareInterface;
use Sicet7\HTTP\Attributes\Exceptions\MiddlewareAttributeException;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
readonly class Middleware
{
    /**
     * @param string $class
     * @throws MiddlewareAttributeException
     */
    public function __construct(public string $class)
    {
        if (!is_subclass_of($this->class, MiddlewareInterface::class)) {
            throw new MiddlewareAttributeException(
                'The class "' . $this->class . '" does not implement the required middleware interface.'
            );
        }
    }
}