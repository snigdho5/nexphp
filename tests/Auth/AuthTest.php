<?php

declare(strict_types=1);

namespace Nex\Tests\Auth;

use Nex\Auth\AuthenticateMiddleware;
use Nex\Auth\AuthManager;
use Nex\Auth\GenericUser;
use Nex\Auth\Guards\TokenGuard;
use Nex\Auth\Password;
use Nex\Auth\Providers\DatabaseUserProvider;
use Nex\Authorization\AuthorizeMiddleware;
use Nex\Authorization\Exceptions\AuthorizationException;
use Nex\Authorization\Gate;
use Nex\Database\Connection;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;
use Nex\Http\Response;
use Nex\Http\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthTest extends TestCase
{
    private Connection $connection;
    private DatabaseUserProvider $provider;
    private TokenGuard $guard;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $this->connection = new Connection($pdo, 'sqlite');

        $schema = new Schema($this->connection);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('api_token')->nullable()->unique();
        });

        $this->connection->table('users')->insert([
            'name' => 'John Developer',
            'email' => 'john@nexphp.com',
            'password' => Password::hash('secret123'),
            'api_token' => 'test-bearer-token-12345',
        ]);

        $this->provider = new DatabaseUserProvider($this->connection);
        $this->guard = new TokenGuard($this->provider);
    }

    public function testPasswordHashing(): void
    {
        $hash = Password::hash('my-secure-password');
        $this->assertTrue(Password::verify('my-secure-password', $hash));
        $this->assertFalse(Password::verify('wrong-password', $hash));
    }

    public function testDatabaseUserProviderValidation(): void
    {
        $user = $this->provider->retrieveByCredentials(['email' => 'john@nexphp.com']);
        $this->assertNotNull($user);
        $this->assertSame(1, $user->getAuthIdentifier());

        $this->assertTrue($this->provider->validateCredentials($user, ['password' => 'secret123']));
        $this->assertFalse($this->provider->validateCredentials($user, ['password' => 'wrongpass']));
    }

    public function testTokenGuardAuthentication(): void
    {
        $request = (new ServerRequest('GET', '/api/profile'))
            ->withHeader('Authorization', 'Bearer test-bearer-token-12345');

        $this->guard->setRequest($request);

        $this->assertTrue($this->guard->check());
        $this->assertFalse($this->guard->guest());
        $this->assertSame(1, $this->guard->id());
        $this->assertSame('John Developer', $this->guard->user()->name);
    }

    public function testAuthenticateMiddlewareRejection(): void
    {
        $middleware = new AuthenticateMiddleware($this->guard);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };

        // Missing token -> expect AuthenticationException
        $this->expectException(\Nex\Auth\Exceptions\AuthenticationException::class);
        $middleware->process(new ServerRequest('GET', '/api/secret'), $handler);
    }

    public function testGateAbilitiesAndPolicies(): void
    {
        $auth = new AuthManager();
        $auth->extend('token', $this->guard);
        $auth->setDefaultGuard('token');

        // Set authenticated user
        $request = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Bearer test-bearer-token-12345');
        $this->guard->setRequest($request);

        $gate = new Gate($auth);
        $gate->define('edit-profile', function (?GenericUser $user) {
            return $user !== null && $user->email === 'john@nexphp.com';
        });

        $this->assertTrue($gate->allows('edit-profile'));
        $this->assertFalse($gate->allows('admin-only'));

        // Policy test
        $gate->policy(PostEntity::class, PostEntityPolicy::class);
        $post = new PostEntity(userId: 1);
        $otherPost = new PostEntity(userId: 99);

        $this->assertTrue($gate->allows('update', $post));
        $this->assertFalse($gate->allows('update', $otherPost));

        $this->expectException(AuthorizationException::class);
        $gate->authorize('update', $otherPost);
    }
}

class PostEntity
{
    public function __construct(public int $userId)
    {
    }
}

class PostEntityPolicy
{
    public function update(GenericUser $user, PostEntity $post): bool
    {
        return $user->getAuthIdentifier() === $post->userId;
    }
}
