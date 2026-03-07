<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Attribute that marks a class as a route controller and optionally assigns a path prefix.
 */
class Controller
{
    /**
     * Set the route prefix applied to every Route attribute on the class.
     * @param string $prefix Controller-level URL prefix.
     * @return void
     */
    public function __construct(public string $prefix = '')
    {
    }
}
