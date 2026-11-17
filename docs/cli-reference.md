# NexPHP CLI Command Reference

The NexPHP CLI (`bin/nex`) provides 23 built-in commands for application development, maintenance, and runtime execution.

Usage:
```bash
php bin/nex <command> [arguments] [options]
```

---

## 1. Project & Scaffolding

| Command | Arguments | Description |
| :--- | :--- | :--- |
| `new` | `<project-name>` | Create a complete NexPHP application skeleton in `./<project-name>` |
| `make:controller` | `<Name>` | Scaffold a new controller class with route attributes |
| `make:model` | `<Name>` | Scaffold an Active Record Model class |
| `make:request` | `<Name>` | Scaffold a FormRequest validation class |
| `make:policy` | `<Name>` | Scaffold an Authorization Policy class |
| `make:middleware` | `<Name>` | Scaffold a PSR-15 Middleware class |
| `make:provider` | `<Name>` | Scaffold a Service Provider class |
| `make:job` | `<Name>` | Scaffold a serializable background Job class |
| `make:event` | `<Name>` | Scaffold an in-process Event class |
| `make:listener` | `<Name>` | Scaffold an Event Listener class |
| `make:mail` | `<Name>` | Scaffold a Mailable class |
| `make:notification` | `<Name>` | Scaffold a multi-channel Notification class |
| `make:migration` | `<name>` | Create a new timestamped schema migration file |

---

## 2. Server & Runtime

| Command | Arguments | Description |
| :--- | :--- | :--- |
| `serve` | `[port]` | Start the local development web server (default port: 8000) |
| `worker:run` | `[options]` | Run the application in persistent worker mode |
| `runtime:diagnostics` | - | Display PHP SAPI, memory usage, and runtime capabilities |
| `about` | - | Display system environment, PHP version, and framework version |
| `route:list` | - | List all registered routes and their HTTP methods |

---

## 3. Database & Migrations

| Command | Arguments | Description |
| :--- | :--- | :--- |
| `migrate` | - | Execute all pending database migrations |
| `migrate:rollback` | - | Rollback the most recent migration batch |

---

## 4. Queue & Scheduling

| Command | Arguments | Description |
| :--- | :--- | :--- |
| `queue:work` | `[queue]` | Run worker loop to process background jobs (default queue: `default`) |
| `queue:failed` | - | List all failed jobs and their error traces in tabular format |
| `queue:retry` | `<id>` | Re-queue a failed job by its unique ID |
| `schedule:run` | - | Execute all scheduled tasks that are due now |

---

## 5. Testing

| Command | Arguments | Description |
| :--- | :--- | :--- |
| `test` | `[filter]` | Execute the PHPUnit test suite directly |

---

## Intelligent Typos & Suggestions

NexPHP automatically calculates Levenshtein distances for misspelled commands:
```bash
$ php bin/nex serv
[ERROR] Command [serv] not found.
  Did you mean serve?
```
