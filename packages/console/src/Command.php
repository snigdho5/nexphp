<?php

declare(strict_types=1);

namespace Nex\Console;

use Nex\Foundation\Application;

abstract class Command
{
    protected string $name = '';
    protected string $description = '';
    protected Application $app;
    protected Output $output;

    public function __construct(Application $app, ?Output $output = null)
    {
        $this->app = $app;
        $this->output = $output ?? new Output();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param list<string> $args
     */
    abstract public function execute(array $args = []): int;
}
