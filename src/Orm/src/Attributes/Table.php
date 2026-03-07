<?php

declare(strict_types=1);

namespace Maia\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Attribute that overrides the database table name used by a model.
 */
class Table
{
    /**
     * Set the table name for the annotated model class.
     * @param string $name Database table name.
     * @return void
     */
    public function __construct(public string $name)
    {
    }
}
