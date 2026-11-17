<?php

declare(strict_types=1);

namespace Nex\Tests\Console;

use Nex\Console\ConsoleApplication;
use Nex\Console\Output;
use Nex\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private string $tempDir;
    private Application $app;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nex_console_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->app = Application::create($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->tempDir);
    }

    public function test_new_command_scaffolds_project(): void
    {
        $projectName = 'test_app_' . uniqid();
        $target = getcwd() . DIRECTORY_SEPARATOR . $projectName;

        ob_start();
        $console = new ConsoleApplication($this->app, new Output());
        $exitCode = $console->run(['bin/nex', 'new', $projectName]);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($target);
        $this->assertFileExists($target . '/composer.json');
        $this->assertFileExists($target . '/public/index.php');
        $this->assertFileExists($target . '/routes/api.php');
        $this->assertFileExists($target . '/.env.example');

        // Cleanup
        $this->deleteRecursive($target);
    }

    public function test_make_controller_scaffolds_file(): void
    {
        ob_start();
        $console = new ConsoleApplication($this->app, new Output());
        $exitCode = $console->run(['bin/nex', 'make:controller', 'SampleController']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Controllers/SampleController.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('class SampleController', $content);
    }

    public function test_make_middleware_scaffolds_file(): void
    {
        ob_start();
        $console = new ConsoleApplication($this->app, new Output());
        $exitCode = $console->run(['bin/nex', 'make:middleware', 'CheckAuthHeader']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Middlewares/CheckAuthHeader.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('class CheckAuthHeader implements MiddlewareInterface', $content);
    }

    public function test_make_provider_scaffolds_file(): void
    {
        ob_start();
        $console = new ConsoleApplication($this->app, new Output());
        $exitCode = $console->run(['bin/nex', 'make:provider', 'EventServiceProvider']);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Providers/EventServiceProvider.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('class EventServiceProvider', $content);
    }

    public function test_unknown_command_suggests_closest_match(): void
    {
        ob_start();
        $console = new ConsoleApplication($this->app, new Output());
        $exitCode = $console->run(['bin/nex', 'migrat']); // Typo for migrate
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Command [migrat] not found', $output);
        $this->assertStringContainsString('Did you mean', $output);
        $this->assertStringContainsString('migrate', $output);
    }

    private function deleteRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteRecursive($path);
            } else {
                unlink($path);
            }
        }
        @rmdir($dir);
    }
}
