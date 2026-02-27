<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Controller defines a framework component for this package.
 */
class Controller
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $prefix Input value.
     * @return void Output value.
     */
    public function __construct(public string $prefix = '')
    {
    }
}
