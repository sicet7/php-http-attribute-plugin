<?php

namespace Sicet7\HTTP\Attributes;

use Sicet7\HttpUtils\Enums\Method;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Delete extends Route
{
    /**
     * @param string $pattern
     */
    public function __construct(string $pattern)
    {
        parent::__construct($pattern, Method::DELETE);
    }
}