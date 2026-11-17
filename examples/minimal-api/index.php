<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Nex\Foundation\Application;
use Nex\Http\Responses\JsonResponse;
use Nex\Http\ServerRequest;
use Nex\Http\ServerRequestFactory;
use Nex\Http\ResponseEmitter;
use Nex\Database\Connection;
use Nex\Database\Migrations\Schema\Blueprint;
use Nex\Database\Migrations\Schema\Schema;
use Nex\Validation\Validator;

$app = Application::create(__DIR__);

// Set up in-memory SQLite database
$pdo = new PDO('sqlite::memory:');
$db = new Connection($pdo, 'sqlite');
$app->singleton(Connection::class, fn () => $db);

// Initialize schema
$schema = new Schema($db);
$schema->create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->boolean('completed')->default(0);
    $table->timestamps();
});

// Seed sample tasks
$db->statement("INSERT INTO tasks (title, completed, created_at, updated_at) VALUES ('Buy milk', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
$db->statement("INSERT INTO tasks (title, completed, created_at, updated_at) VALUES ('Build NexPHP app', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

// Routes
$app->get('/api/tasks', function () use ($db) {
    $tasks = $db->select('SELECT * FROM tasks ORDER BY id DESC');
    return new JsonResponse(['data' => $tasks]);
});

$app->post('/api/tasks', function (ServerRequest $request) use ($db) {
    $body = $request->getParsedBody() ?? [];

    $validator = new Validator();
    $result = $validator->validate($body, [
        'title' => 'required|min:3|max:100',
    ]);

    if ($result->fails()) {
        return new JsonResponse(['errors' => $result->errors()], 422);
    }

    $title = (string) $body['title'];
    $db->statement(
        "INSERT INTO tasks (title, completed, created_at, updated_at) VALUES (?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        [$title]
    );

    return new JsonResponse([
        'message' => 'Task created successfully',
        'task'    => ['title' => $title, 'completed' => false],
    ], 201);
});

$app->get('/api/tasks/{id}', function (ServerRequest $request, array $params) use ($db) {
    $id = (int) ($params['id'] ?? 0);
    $rows = $db->select('SELECT * FROM tasks WHERE id = ?', [$id]);

    if (empty($rows)) {
        return new JsonResponse(['error' => 'Task not found'], 404);
    }

    return new JsonResponse(['data' => $rows[0]]);
});

$app->delete('/api/tasks/{id}', function (ServerRequest $request, array $params) use ($db) {
    $id = (int) ($params['id'] ?? 0);
    $db->statement('DELETE FROM tasks WHERE id = ?', [$id]);
    return new JsonResponse(['message' => 'Task deleted']);
});

// Direct execution test or web request
if (php_sapi_name() === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' && empty($_SERVER['REQUEST_METHOD'])) {
    echo "Running Minimal API verification...\n";
    $req = new ServerRequest('GET', '/api/tasks');
    $res = $app->handle($req);
    echo "GET /api/tasks: HTTP " . $res->getStatusCode() . "\n";
    echo $res->getBody()->getContents() . "\n";
} else {
    $request = ServerRequestFactory::fromGlobals();
    $response = $app->handle($request);
    (new ResponseEmitter())->emit($response);
}
