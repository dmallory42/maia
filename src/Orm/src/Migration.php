<?php

declare(strict_types=1);

namespace Maia\Orm;

use Maia\Orm\Schema\Schema;

abstract class Migration
{
    abstract public function up(Schema $schema): void;

    abstract public function down(Schema $schema): void;
}
