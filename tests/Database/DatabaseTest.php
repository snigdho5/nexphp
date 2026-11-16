<?php

declare(strict_types=1);

namespace Nex\Tests\Database;

use Exception;
use Nex\Container\Container;
use Nex\Database\Connection;
use Nex\Database\Contracts\DatabaseManagerInterface;
use Nex\Database\DatabaseManager;
use Nex\Database\Migrations\Migration;
use Nex\Database\Migrations\Migrator;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;
use Nex\Database\Model;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private Connection $connection;
    private DatabaseManager $manager;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($pdo, 'sqlite');

        // Setup test schema
        $schema = new Schema($this->connection);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $container = Container::getInstance();
        $this->manager = new class($this->connection) extends DatabaseManager {
            public function __construct(private Connection $conn)
            {
                parent::__construct();
            }
            public function connection(?string $name = null): Connection
            {
                return $this->conn;
            }
        };
        $container->instance(DatabaseManagerInterface::class, $this->manager);
    }

    public function testInsertAndSelect(): void
    {
        $id = $this->connection->table('users')->insertGetId([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $this->assertSame(1, $id);

        $user = $this->connection->table('users')->where('id', 1)->first();
        $this->assertNotNull($user);
        $this->assertSame('Alice', $user['name']);
        $this->assertSame('alice@example.com', $user['email']);
    }

    public function testWhereInAndCount(): void
    {
        $this->connection->table('users')->insert(['name' => 'Bob', 'email' => 'bob@example.com']);
        $this->connection->table('users')->insert(['name' => 'Charlie', 'email' => 'charlie@example.com']);

        $count = $this->connection->table('users')->whereIn('name', ['Bob', 'Charlie'])->count();
        $this->assertSame(2, $count);
    }

    public function testTransactionsRollback(): void
    {
        try {
            $this->manager->transaction(function (Connection $conn) {
                $conn->table('users')->insert(['name' => 'Ghost', 'email' => 'ghost@example.com']);
                throw new Exception('Simulated failure');
            });
        } catch (Exception) {
            // expected
        }

        $count = $this->connection->table('users')->where('name', 'Ghost')->count();
        $this->assertSame(0, $count);
    }

    public function testActiveRecordModel(): void
    {
        $user = UserModel::create([
            'name' => 'Diana',
            'email' => 'diana@example.com',
        ]);

        $this->assertSame(1, $user->id);

        // Update
        $user->name = 'Diana Prince';
        $user->save();

        $found = UserModel::find(1);
        $this->assertNotNull($found);
        $this->assertSame('Diana Prince', $found->name);

        // Delete
        $found->delete();
        $this->assertNull(UserModel::find(1));
    }

    public function testMigratorRunAndRollback(): void
    {
        $migrator = new Migrator($this->connection, 'test_migrations');
        $migrator->ensureMigrationTable();

        $this->assertSame(0, $migrator->getLastBatchNumber());

        $tempDir = sys_get_temp_dir() . '/nex_migrations_' . bin2hex(random_bytes(4));
        mkdir($tempDir);

        $migrationFile = $tempDir . '/2026_01_01_000000_create_posts_table.php';
        file_put_contents($migrationFile, <<<'PHP'
<?php
use Nex\Database\Migrations\Migration;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void {
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
    }
    public function down(Schema $schema): void {
        $schema->dropIfExists('posts');
    }
};
PHP
        );

        $migrated = $migrator->run($tempDir);
        $this->assertSame(['2026_01_01_000000_create_posts_table'], $migrated);
        $this->assertSame(1, $migrator->getLastBatchNumber());

        // Verify table exists by inserting
        $this->connection->table('posts')->insert(['title' => 'First Post']);
        $this->assertSame(1, $this->connection->table('posts')->count());

        // Rollback
        $rolledBack = $migrator->rollback($tempDir);
        $this->assertSame(['2026_01_01_000000_create_posts_table'], $rolledBack);

        // Cleanup
        unlink($migrationFile);
        rmdir($tempDir);
    }
}

class UserModel extends Model
{
    protected static string $table = 'users';
    protected array $fillable = ['name', 'email', 'status'];
}
