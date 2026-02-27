<?php

declare(strict_types=1);

namespace Maia\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * BelongsTo defines a framework component for this package.
 */
class BelongsTo
{
    /**
     * Create an instance with configured dependencies and defaults.
     * @param string $relatedClass Input value.
     * @param string|null $foreignKey Input value.
     * @return void Output value.
     */
    public function __construct(
        public string $relatedClass,
        public ?string $foreignKey = null
    ) {
    }
}
