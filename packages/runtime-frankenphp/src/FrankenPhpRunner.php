<?php

declare(strict_types=1);

namespace Nex\Runtime\FrankenPhp;

use Nex\Contracts\Foundation\ApplicationInterface;
use Nex\Http\ResponseEmitter;
use Nex\Http\ServerRequestFactory;
use Nex\Runtime\Contracts\RunnerInterface;
use Nex\Runtime\Contracts\RuntimeCapabilities;
use Nex\Runtime\Contracts\RuntimeCapabilitiesInterface;
use Nex\Runtime\Contracts\WorkerConfiguration;
use Nex\Runtime\Worker\WorkerRunner;

class FrankenPhpRunner implements RunnerInterface
{
    public function __construct(
        private WorkerConfiguration $config = new WorkerConfiguration(),
        private ?ResponseEmitter $emitter = null
    ) {
        $this->emitter ??= new ResponseEmitter();
    }

    public function run(ApplicationInterface $app): void
    {
        // If native FrankenPHP worker mode function is available
        if (function_exists('frankenphp_handle_request')) {
            $requests = 0;
            do {
                $ret = \frankenphp_handle_request(function () use ($app) {
                    $request = ServerRequestFactory::fromGlobals();
                    $response = $app->handle($request);
                    $this->emitter->emit($response);
                    $app->terminate($request, $response);
                });

                $requests++;

                if ($requests >= $this->config->maxRequests) {
                    break;
                }

                if (memory_get_usage(true) >= $this->config->maxMemoryBytes) {
                    break;
                }
            } while ($ret);

            return;
        }

        // Fallback for local worker execution
        $worker = new WorkerRunner($this->config, emitter: $this->emitter);
        $worker->run($app);
    }

    public function capabilities(): RuntimeCapabilitiesInterface
    {
        return new RuntimeCapabilities(
            persistentWorkers: true,
            streamingResponses: true,
            webSockets: true,
            asyncIO: true,
            gracefulShutdown: true
        );
    }
}
