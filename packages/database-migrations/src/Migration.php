<?php

declare(strict_types=1);

namespace Nex\Database\Migrations;

use Nex\Database\Migrations\Schema\Schema;

abstract class Migration
{
    abstract public function up(Schema $schema): void;

    abstract public function down(Schema $schema): void;
}
