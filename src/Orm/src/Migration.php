<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Schema\Schema;

/**
 * Base class for schema migrations with forward and rollback steps.
 */
abstract class Migration
{
    /**
     * Apply the schema changes for this migration.
     * @param Schema $schema Schema builder used to create or alter tables.
     * @return void
     */
    abstract public function up(Schema $schema): void;

    /**
     * Revert the schema changes introduced in up().
     * @param Schema $schema Schema builder used to drop or revert tables.
     * @return void
     */
    abstract public function down(Schema $schema): void;
}
