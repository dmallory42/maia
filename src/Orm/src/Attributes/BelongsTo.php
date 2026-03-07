<?php

declare(strict_types=1);

namespace Maia\Orm\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Attribute that marks a model property as a BelongsTo relationship.
 */
class BelongsTo
{
    /**
     * Describe the related model class and optional foreign-key column.
     * @param string $relatedClass Fully qualified related model class name.
     * @param string|null $foreignKey Foreign-key column on the current model, or null to infer it.
     * @return void
     */
    public function __construct(
        public string $relatedClass,
        public ?string $foreignKey = null
    ) {
    }
}
