<?php

declare(strict_types=1);

namespace Maia\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany
{
    public function __construct(
        public string $relatedClass,
        public ?string $foreignKey = null
    ) {
    }
}
