<?php

declare(strict_types=1);

namespace Maia\Core\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
    public function __construct(public string $prefix = '')
    {
    }
}
