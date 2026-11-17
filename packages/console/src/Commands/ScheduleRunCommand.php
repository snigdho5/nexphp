<?php

declare(strict_types=1);

namespace Nex\Console\Commands;

use Nex\Console\Command;
use Nex\Scheduler\Schedule;
use Nex\Scheduler\SystemClock;

class ScheduleRunCommand extends Command
{
    protected string $name = 'schedule:run';
    protected string $description = 'Run all scheduled tasks that are due now';

    public function execute(array $args = []): int
    {
        $this->output->info("Running scheduled tasks...");

        // Try to get the schedule from the container, or create a default
        $schedule = null;
        if ($this->app->has(Schedule::class)) {
            $schedule = $this->app->make(Schedule::class);
        } else {
            $this->output->info("No Schedule registered in container. Register one via \$app->singleton(Schedule::class, ...)");
            return 0;
        }

        $count = $schedule->tick();
        $this->output->success("Executed {$count} scheduled task(s).");
        return 0;
    }
}
