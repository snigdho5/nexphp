<?php

declare(strict_types=1);

namespace Nex\Tests\Foundation;

use Nex\Foundation\Application;
use Nex\Routing\Attributes\Get;
use Nex\Routing\Attributes\Post;
use Nex\Routing\Attributes\Route;
use Nex\Testing\TestClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

class ApplicationIntegrationTest extends TestCase
{
    private Application $app;
    private TestClient $client;

    protected function setUp(): void
    {
        $this->app = Application::create();
        $this->client = new TestClient($this->app);
    }

    public function testSingleFileMicroAppWorkflow(): void
    {
        $this->app->get('/ping', fn() => ['status' => 'pong', 'version' => '1.0']);

        $response = $this->client->get('/ping');

        $response->assertOk()
            ->assertJson(['status' => 'pong', 'version' => '1.0'])
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertNotEmpty($response->getResponse()->getHeaderLine('X-Request-Id'));
    }

    public function testRouteParameterInjection(): void
    {
        $this->app->get('/greeting/{name}', function (string $name) {
            return "Hello, $name!";
        });

        $response = $this->client->get('/greeting/developer');
        $response->assertOk()
            ->assertSee('Hello, developer!');
    }

    public function testAttributeBasedControllerEndpoints(): void
    {
        $this->app->registerController(UserApiController::class);

        // List
        $this->client->get('/api/users')
            ->assertOk()
            ->assertJson(['users' => ['Alice', 'Bob']]);

        // Create
        $this->client->post('/api/users', ['name' => 'Charlie'])
            ->assertOk()
            ->assertJson(['created' => true]);
    }

    public function testRequestScopeIsolationAcrossMultipleRequests(): void
    {
        // Register a request-scoped dependency
        $this->app->scoped('request_tracker', function () {
            $obj = new stdClass();
            $obj->id = bin2hex(random_bytes(8));
            return $obj;
        });

        $this->app->get('/tracker', function (Application $app) {
            /** @var stdClass $tracker */
            $tracker = $app->make('request_tracker');
            return ['tracker_id' => $tracker->id];
        });

        $res1 = $this->client->get('/tracker')->json();
        $res2 = $this->client->get('/tracker')->json();

        $this->assertNotEmpty($res1['tracker_id']);
        $this->assertNotEmpty($res2['tracker_id']);
        // Crucial test for persistent workers: Request 2 must get a fresh instance, NOT Request 1's instance!
        $this->assertNotSame($res1['tracker_id'], $res2['tracker_id']);
    }

    public function test404HandlingReturnsJsonForApi(): void
    {
        $response = $this->client->get('/api/does-not-exist');
        $response->assertNotFound()
            ->assertJson(['error' => ['code' => 404, 'message' => 'No route found for [GET /api/does-not-exist].']]);
    }
}

#[Route(path: '/api/users')]
class UserApiController
{
    #[Get]
    public function index(): array
    {
        return ['users' => ['Alice', 'Bob']];
    }

    #[Post]
    public function store(): array
    {
        return ['created' => true];
    }
}
