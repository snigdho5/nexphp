<?php

declare(strict_types=1);

namespace Nex\Tests\Integration;

use Nex\Auth\AuthenticateMiddleware;
use Nex\Auth\AuthManager;
use Nex\Auth\GenericUser;
use Nex\Auth\Guards\TokenGuard;
use Nex\Auth\Password;
use Nex\Auth\Providers\DatabaseUserProvider;
use Nex\Authorization\Exceptions\AuthorizationException;
use Nex\Authorization\Gate;
use Nex\Database\Connection;
use Nex\Database\Contracts\DatabaseManagerInterface;
use Nex\Database\DatabaseManager;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;
use Nex\Database\Model;
use Nex\Foundation\Application;
use Nex\Routing\Attributes\Delete;
use Nex\Routing\Attributes\Get;
use Nex\Routing\Attributes\Post;
use Nex\Routing\Attributes\Put;
use Nex\Routing\Attributes\Route;
use Nex\Testing\TestClient;
use Nex\Validation\FormRequest;
use PDO;
use PHPUnit\Framework\TestCase;

class CrudApiIntegrationTest extends TestCase
{
    private Application $app;
    private TestClient $client;
    private Connection $connection;
    private TokenGuard $guard;

    protected function setUp(): void
    {
        $this->app = Application::create();
        $this->client = new TestClient($this->app);

        // Setup in-memory SQLite database
        $pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($pdo, 'sqlite');

        $dbManager = new class($this->connection) extends DatabaseManager {
            public function __construct(private Connection $conn)
            {
                parent::__construct();
            }
            public function connection(?string $name = null): Connection
            {
                return $this->conn;
            }
        };
        $this->app->instance(DatabaseManagerInterface::class, $dbManager);

        // Run migrations
        $schema = new Schema($this->connection);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('api_token')->unique();
        });

        $schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });

        // Seed users
        $this->connection->table('users')->insert([
            'id' => 1,
            'name' => 'Author Alice',
            'email' => 'alice@nexphp.com',
            'password' => Password::hash('secret123'),
            'api_token' => 'alice-token',
        ]);

        $this->connection->table('users')->insert([
            'id' => 2,
            'name' => 'User Bob',
            'email' => 'bob@nexphp.com',
            'password' => Password::hash('secret123'),
            'api_token' => 'bob-token',
        ]);

        // Auth & Gate setup
        $provider = new DatabaseUserProvider($this->connection);
        $this->guard = new TokenGuard($provider);

        $authManager = new AuthManager();
        $authManager->extend('token', $this->guard);
        $this->app->instance(AuthManager::class, $authManager);

        $gate = new Gate($authManager);
        $gate->policy(ArticleModel::class, ArticlePolicy::class);
        $this->app->instance(Gate::class, $gate);

        // Register CRUD routes
        $this->app->registerController(ArticleApiController::class);
    }

    public function testUnauthenticatedAccessReturns401(): void
    {
        $response = $this->client->get('/api/articles');
        $response->assertUnauthorized();
    }

    public function testValidationFailureReturns422(): void
    {
        $response = $this->client
            ->actingWithToken('alice-token')
            ->post('/api/articles', [
                'title' => '', // required
                'content' => 'short', // min 10
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'type' => 'validation_error',
            ]);
    }

    public function testCreateAndReadArticle(): void
    {
        // 1. Create Article as Alice
        $createResponse = $this->client
            ->actingWithToken('alice-token')
            ->post('/api/articles', [
                'title' => 'Building NexPHP Framework',
                'content' => 'This is a complete guide to modern PHP.',
            ]);

        $createResponse->assertOk()
            ->assertJson([
                'created' => true,
                'article' => [
                    'id' => 1,
                    'user_id' => 1,
                    'title' => 'Building NexPHP Framework',
                ],
            ]);

        // 2. Fetch Article
        $getResponse = $this->client
            ->actingWithToken('alice-token')
            ->get('/api/articles/1');

        $getResponse->assertOk()
            ->assertJson([
                'title' => 'Building NexPHP Framework',
            ]);
    }

    public function testAuthorizationForbiddenForNonOwner(): void
    {
        // Create as Alice
        $this->client
            ->actingWithToken('alice-token')
            ->post('/api/articles', [
                'title' => 'Alice Private Draft',
                'content' => 'Content only Alice should update or delete.',
            ]);

        // Bob attempts to update Alice's article -> 403 Forbidden
        $bobUpdateResponse = $this->client
            ->actingWithToken('bob-token')
            ->put('/api/articles/1', [
                'title' => 'Hacked by Bob',
                'content' => 'Malicious updated content.',
            ]);

        $bobUpdateResponse->assertForbidden();

        // Alice updates her own article -> 200 OK
        $aliceUpdateResponse = $this->client
            ->actingWithToken('alice-token')
            ->put('/api/articles/1', [
                'title' => 'Updated by Alice',
                'content' => 'New validated content by the real author.',
            ]);

        $aliceUpdateResponse->assertOk()
            ->assertJson(['updated' => true]);

        // Bob attempts to delete Alice's article -> 403 Forbidden
        $bobDeleteResponse = $this->client
            ->actingWithToken('bob-token')
            ->delete('/api/articles/1');

        $bobDeleteResponse->assertForbidden();

        // Alice deletes her own article -> 200 OK
        $aliceDeleteResponse = $this->client
            ->actingWithToken('alice-token')
            ->delete('/api/articles/1');

        $aliceDeleteResponse->assertOk()
            ->assertJson(['deleted' => true]);
    }
}

class ArticleModel extends Model
{
    protected static string $table = 'articles';
    protected array $fillable = ['user_id', 'title', 'content', 'created_at', 'updated_at'];
}

class ArticlePolicy
{
    public function update(GenericUser $user, ArticleModel $article): bool
    {
        return $user->getAuthIdentifier() === (int) $article->user_id;
    }

    public function delete(GenericUser $user, ArticleModel $article): bool
    {
        return $user->getAuthIdentifier() === (int) $article->user_id;
    }
}

class StoreArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:100',
            'content' => 'required|string|min:10',
        ];
    }
}

class UpdateArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|min:5|max:100',
            'content' => 'required|string|min:10',
        ];
    }
}

#[Route(path: '/api/articles')]
class ArticleApiController
{
    public function __construct(
        private AuthManager $auth,
        private Gate $gate
    ) {
    }

    #[Get]
    public function index(): array
    {
        $this->ensureAuthenticated();
        return ArticleModel::query()->get();
    }

    #[Get(path: '/{id}')]
    public function show(string $id): array
    {
        $this->ensureAuthenticated();
        $article = ArticleModel::find($id);
        if (!$article) {
            throw new \Nex\Routing\Exceptions\RouteNotFoundException();
        }
        return $article->toArray();
    }

    #[Post]
    public function store(StoreArticleRequest $request): array
    {
        $user = $this->ensureAuthenticated();
        $data = $request->validated();
        $data['user_id'] = $user->getAuthIdentifier();
        $data['created_at'] = date('Y-m-d H:i:s');

        $article = ArticleModel::create($data);

        return [
            'created' => true,
            'article' => $article->toArray(),
        ];
    }

    #[Put(path: '/{id}')]
    public function update(string $id, UpdateArticleRequest $request): array
    {
        $user = $this->ensureAuthenticated();
        $article = ArticleModel::find($id);
        if (!$article) {
            throw new \Nex\Routing\Exceptions\RouteNotFoundException();
        }

        $this->gate->authorize('update', $article);

        $article->fill($request->validated());
        $article->save();

        return ['updated' => true, 'article' => $article->toArray()];
    }

    #[Delete(path: '/{id}')]
    public function destroy(string $id): array
    {
        $user = $this->ensureAuthenticated();
        $article = ArticleModel::find($id);
        if (!$article) {
            throw new \Nex\Routing\Exceptions\RouteNotFoundException();
        }

        $this->gate->authorize('delete', $article);
        $article->delete();

        return ['deleted' => true];
    }

    private function ensureAuthenticated(): GenericUser
    {
        $guard = $this->auth->guard('token');
        if ($guard->guest()) {
            throw new \Nex\Auth\Exceptions\AuthenticationException();
        }
        /** @var GenericUser */
        return $guard->user();
    }
}
