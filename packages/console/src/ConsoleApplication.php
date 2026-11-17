<?php

declare(strict_types=1);

namespace Nex\Console;

use Nex\Console\Commands\AboutCommand;
use Nex\Console\Commands\RouteListCommand;
use Nex\Console\Commands\ServeCommand;
use Nex\Foundation\Application;

class ConsoleApplication
{
    /**
     * @var array<string, Command>
     */
    private array $commands = [];
    private Output $output;

    public function __construct(private Application $app, ?Output $output = null)
    {
        $this->output = $output ?? new Output();
        $this->registerDefaultCommands();
    }

    public function add(Command $command): static
    {
        $this->commands[$command->getName()] = $command;
        return $this;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv = []): int
    {
        $commandName = $argv[1] ?? 'about';

        if ($commandName === '--help' || $commandName === '-h' || $commandName === 'list') {
            $this->printHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->output->error("Command [{$commandName}] not found.");

            $suggestion = $this->findClosestCommand($commandName);
            if ($suggestion !== null) {
                $this->output->writeln("  Did you mean \033[33m{$suggestion}\033[0m?");
                $this->output->writeln();
            }

            $this->printHelp();
            return 1;
        }

        $command = $this->commands[$commandName];
        return $command->execute(array_slice($argv, 2));
    }

    private function registerDefaultCommands(): void
    {
        $this->add(new AboutCommand($this->app, $this->output));
        $this->add(new RouteListCommand($this->app, $this->output));
        $this->add(new ServeCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeMigrationCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MigrateCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MigrateRollbackCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeModelCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeRequestCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakePolicyCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\WorkerRunCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\RuntimeDiagnosticsCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\QueueWorkCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\QueueFailedCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\QueueRetryCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\ScheduleRunCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeJobCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeEventCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeListenerCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeMailCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeNotificationCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\NewCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeControllerCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeMiddlewareCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\MakeProviderCommand($this->app, $this->output));
        $this->add(new \Nex\Console\Commands\TestCommand($this->app, $this->output));
    }

    private function findClosestCommand(string $input): ?string
    {
        $bestMatch = null;
        $shortestDistance = -1;

        foreach (array_keys($this->commands) as $commandName) {
            $distance = levenshtein($input, $commandName);

            if ($distance <= 3 && ($shortestDistance < 0 || $distance < $shortestDistance)) {
                $shortestDistance = $distance;
                $bestMatch = $commandName;
            }
        }

        return $bestMatch;
    }

    private function printHelp(): void
    {
        $this->output->writeln("NexPHP Framework CLI " . $this->app->version());
        $this->output->writeln();
        $this->output->writeln("Usage: php bin/nex <command> [options]");
        $this->output->writeln();
        $this->output->writeln("Available commands:");

        foreach ($this->commands as $name => $cmd) {
            $this->output->writeln(sprintf("  \033[32m%-16s\033[0m %s", $name, $cmd->getDescription()));
        }

        $this->output->writeln();
    }
}
