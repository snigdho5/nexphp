<?php

declare(strict_types=1);

namespace Nex\Filesystem;

use Nex\Filesystem\Contracts\FilesystemInterface;
use RuntimeException;

/**
 * Local filesystem adapter.
 *
 * All paths are relative to $root. Path traversal is prevented by normalizing
 * the resolved absolute path and checking it still starts with $root.
 */
final class LocalFilesystem implements FilesystemInterface
{
    private string $root;

    public function __construct(string $root)
    {
        // Resolve root to absolute path
        $this->root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, recursive: true);
            $this->root = rtrim(realpath($this->root) ?: $this->root, DIRECTORY_SEPARATOR);
        }
    }

    public function read(string $path): string
    {
        $full = $this->safePath($path);
        if (!file_exists($full)) {
            throw new RuntimeException("File not found: {$path}");
        }
        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }
        return $contents;
    }

    public function write(string $path, string $contents): void
    {
        $full = $this->safePath($path);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        if (file_put_contents($full, $contents) === false) {
            throw new RuntimeException("Failed to write file: {$path}");
        }
    }

    public function append(string $path, string $contents): void
    {
        $full = $this->safePath($path);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        if (file_put_contents($full, $contents, FILE_APPEND) === false) {
            throw new RuntimeException("Failed to append to file: {$path}");
        }
    }

    public function delete(string $path): void
    {
        $full = $this->safePath($path);
        if (file_exists($full)) {
            unlink($full);
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->safePath($path));
    }

    public function copy(string $source, string $destination): void
    {
        $src = $this->safePath($source);
        $dst = $this->safePath($destination);

        if (!file_exists($src)) {
            throw new RuntimeException("Source file not found: {$source}");
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        if (!copy($src, $dst)) {
            throw new RuntimeException("Failed to copy {$source} to {$destination}");
        }
    }

    public function move(string $source, string $destination): void
    {
        $src = $this->safePath($source);
        $dst = $this->safePath($destination);

        if (!file_exists($src)) {
            throw new RuntimeException("Source file not found: {$source}");
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }
        if (!rename($src, $dst)) {
            throw new RuntimeException("Failed to move {$source} to {$destination}");
        }
    }

    public function size(string $path): int
    {
        $full = $this->safePath($path);
        if (!file_exists($full)) {
            throw new RuntimeException("File not found: {$path}");
        }
        return (int) filesize($full);
    }

    public function lastModified(string $path): int
    {
        $full = $this->safePath($path);
        if (!file_exists($full)) {
            throw new RuntimeException("File not found: {$path}");
        }
        return (int) filemtime($full);
    }

    public function files(string $directory = ''): array
    {
        $full = $this->safePath($directory);
        if (!is_dir($full)) {
            return [];
        }
        $items = scandir($full) ?: [];
        $files = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $full . DIRECTORY_SEPARATOR . $item;
            if (is_file($itemPath)) {
                $files[] = ltrim(($directory !== '' ? $directory . '/' : '') . $item, '/');
            }
        }
        return $files;
    }

    public function directories(string $directory = ''): array
    {
        $full = $this->safePath($directory);
        if (!is_dir($full)) {
            return [];
        }
        $items = scandir($full) ?: [];
        $dirs = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $itemPath = $full . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $dirs[] = ltrim(($directory !== '' ? $directory . '/' : '') . $item, '/');
            }
        }
        return $dirs;
    }

    public function makeDirectory(string $path): void
    {
        $full = $this->safePath($path);
        if (!is_dir($full)) {
            mkdir($full, 0755, recursive: true);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $full = $this->safePath($path);
        if (!is_dir($full)) {
            return;
        }
        $this->deleteRecursively($full);
    }

    public function mimeType(string $path): string
    {
        $full = $this->safePath($path);
        if (!file_exists($full)) {
            throw new RuntimeException("File not found: {$path}");
        }
        $mime = mime_content_type($full);
        return $mime !== false ? $mime : 'application/octet-stream';
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Resolve a relative path to an absolute path under $root.
     * Throws if the resolved path escapes the root (path traversal prevention).
     */
    private function safePath(string $path): string
    {
        if ($path === '' || $path === '.') {
            return $this->root;
        }

        // Remove leading slashes and normalize separators
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $full = $this->root . DIRECTORY_SEPARATOR . $normalized;

        // Resolve without requiring it to exist first
        $real = realpath(dirname($full));
        $resolved = ($real !== false ? $real : dirname($full)) . DIRECTORY_SEPARATOR . basename($full);
        $resolved = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);

        if (!str_starts_with($resolved, $this->root . DIRECTORY_SEPARATOR) && $resolved !== $this->root) {
            throw new RuntimeException(
                "Path traversal detected: [{$path}] resolves outside storage root. " .
                "Only paths within the configured storage root are allowed."
            );
        }

        return $resolved;
    }

    private function deleteRecursively(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteRecursively($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
