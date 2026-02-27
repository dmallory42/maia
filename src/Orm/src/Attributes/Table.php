<?php

declare(strict_types=1);

namespace Maia\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Table defines a framework component for this package.
 */
class Table
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $name Input value.
     * @return void Output value.
     */
    public function __construct(public string $name)
    {
    }
}
