<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration file';

    public function execute(array $args = []): int
    {
        $migrationName = $args[0] ?? null;
        if (!$migrationName) {
            $this->output->error('Migration name is required. Usage: nex make:migration create_users_table');
            return 1;
        }

        $migrationDir = $this->app->basePath('database/migrations');
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, recursive: true);
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$migrationName}.php";
        $filePath = $migrationDir . '/' . $fileName;

        $template = <<<PHP
<?php

declare(strict_types=1);

use Nex\Database\Migrations\Migration;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;

return new class extends Migration {
    public function up(Schema \$schema): void
    {
        \$schema->create('table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(Schema \$schema): void
    {
        \$schema->dropIfExists('table_name');
    }
};

PHP;

        file_put_contents($filePath, $template);
        $this->output->success("Created migration: database/migrations/$fileName");

        return 0;
    }
}
