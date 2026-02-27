<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Schema\Schema;

/**
 * Migration defines a framework component for this package.
 */
abstract class Migration
{
    /**
     * Up and return void.
     * @param Schema $schema Input value.
     * @return void Output value.
     */
    abstract public function up(Schema $schema): void;

    /**
     * Down and return void.
     * @param Schema $schema Input value.
     * @return void Output value.
     */
    abstract public function down(Schema $schema): void;
}
