<?php

declare(strict_types=1);

namespace Nex\Tests\Filesystem;

use Nex\Filesystem\LocalFilesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilesystemTest extends TestCase
{
    private string $storageDir;
    private LocalFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/nex_fs_test_' . uniqid();
        mkdir($this->storageDir, 0755, true);
        $this->filesystem = new LocalFilesystem($this->storageDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory('');
        if (is_dir($this->storageDir)) {
            @rmdir($this->storageDir);
        }
    }

    public function test_write_and_read_file(): void
    {
        $this->filesystem->write('test.txt', 'Hello, NexPHP!');

        $this->assertTrue($this->filesystem->exists('test.txt'));
        $this->assertSame('Hello, NexPHP!', $this->filesystem->read('test.txt'));
    }

    public function test_read_non_existent_file_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->filesystem->read('missing.txt');
    }

    public function test_append_file(): void
    {
        $this->filesystem->write('log.txt', "Line 1\n");
        $this->filesystem->append('log.txt', "Line 2\n");

        $this->assertSame("Line 1\nLine 2\n", $this->filesystem->read('log.txt'));
    }

    public function test_delete_file(): void
    {
        $this->filesystem->write('delete-me.txt', 'bye');
        $this->assertTrue($this->filesystem->exists('delete-me.txt'));

        $this->filesystem->delete('delete-me.txt');
        $this->assertFalse($this->filesystem->exists('delete-me.txt'));
    }

    public function test_copy_file(): void
    {
        $this->filesystem->write('orig.txt', 'data');
        $this->filesystem->copy('orig.txt', 'subdir/copy.txt');

        $this->assertTrue($this->filesystem->exists('orig.txt'));
        $this->assertTrue($this->filesystem->exists('subdir/copy.txt'));
        $this->assertSame('data', $this->filesystem->read('subdir/copy.txt'));
    }

    public function test_move_file(): void
    {
        $this->filesystem->write('source.txt', 'move data');
        $this->filesystem->move('source.txt', 'dest.txt');

        $this->assertFalse($this->filesystem->exists('source.txt'));
        $this->assertTrue($this->filesystem->exists('dest.txt'));
        $this->assertSame('move data', $this->filesystem->read('dest.txt'));
    }

    public function test_size_and_last_modified(): void
    {
        $this->filesystem->write('info.txt', '12345');

        $this->assertSame(5, $this->filesystem->size('info.txt'));
        $this->assertGreaterThan(0, $this->filesystem->lastModified('info.txt'));
    }

    public function test_files_and_directories_listing(): void
    {
        $this->filesystem->write('file1.txt', '1');
        $this->filesystem->write('file2.txt', '2');
        $this->filesystem->write('sub/file3.txt', '3');

        $files = $this->filesystem->files();
        sort($files);
        $this->assertSame(['file1.txt', 'file2.txt'], $files);

        $dirs = $this->filesystem->directories();
        $this->assertSame(['sub'], $dirs);

        $subFiles = $this->filesystem->files('sub');
        $this->assertSame(['sub/file3.txt'], $subFiles);
    }

    public function test_make_and_delete_directory(): void
    {
        $this->filesystem->makeDirectory('nested/folder');
        $this->filesystem->write('nested/folder/file.txt', 'content');

        $this->assertTrue($this->filesystem->exists('nested/folder/file.txt'));

        $this->filesystem->deleteDirectory('nested');
        $this->assertFalse($this->filesystem->exists('nested/folder/file.txt'));
    }

    public function test_path_traversal_is_prevented(): void
    {
        $this->expectException(RuntimeException::class);
        $this->filesystem->read('../../etc/passwd');
    }

    public function test_path_traversal_on_non_existent_subdirs_is_prevented(): void
    {
        $this->expectException(RuntimeException::class);
        $this->filesystem->write('non_existent/../../etc/cron.d/malicious', 'exploit');
    }

    public function test_path_traversal_with_null_bytes_is_prevented(): void
    {
        $this->expectException(RuntimeException::class);
        $this->filesystem->write("test\0/../../escaped.txt", 'exploit');
    }
}
