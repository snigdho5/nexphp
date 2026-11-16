<?php

declare(strict_types=1);

namespace Nex\Tests\Config;

use Nex\Config\Env;
use Nex\Config\Repository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigTest extends TestCase
{
    public function testGetAndSetWithDotNotation(): void
    {
        $config = new Repository([
            'app' => [
                'name' => 'NexPHP',
                'debug' => true,
            ],
        ]);

        $this->assertSame('NexPHP', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertSame('default', $config->get('app.unknown', 'default'));

        $config->set('database.connections.mysql.host', '127.0.0.1');
        $this->assertSame('127.0.0.1', $config->get('database.connections.mysql.host'));
    }

    public function testFreezePreventsModification(): void
    {
        $config = new Repository(['app' => ['env' => 'production']]);
        $config->freeze();

        $this->assertTrue($config->isFrozen());

        $this->expectException(RuntimeException::class);
        $config->set('app.env', 'local');
    }

    public function testEnvParsing(): void
    {
        putenv('TEST_BOOLEAN_TRUE=true');
        putenv('TEST_BOOLEAN_FALSE=false');
        putenv('TEST_NULL=null');
        putenv('TEST_STRING=hello');

        $this->assertTrue(Env::get('TEST_BOOLEAN_TRUE'));
        $this->assertFalse(Env::get('TEST_BOOLEAN_FALSE'));
        $this->assertNull(Env::get('TEST_NULL'));
        $this->assertSame('hello', Env::get('TEST_STRING'));
        $this->assertSame('fallback', Env::get('NON_EXISTENT_KEY', 'fallback'));
    }
}
