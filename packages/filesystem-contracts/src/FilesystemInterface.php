<?php

declare(strict_types=1);

namespace Nex\Filesystem\Contracts;

/**
 * Filesystem adapter contract.
 *
 * Security requirements (all adapters must enforce):
 * - Normalize paths and prevent traversal (no ../ escapes)
 * - Never expose raw local filesystem paths in returned values
 * - Keep storage private by default
 */
interface FilesystemInterface
{
    /**
     * Read file contents. Throws if file does not exist.
     */
    public function read(string $path): string;

    /**
     * Write contents to a file (creates parent directories automatically).
     */
    public function write(string $path, string $contents): void;

    /**
     * Append contents to a file.
     */
    public function append(string $path, string $contents): void;

    /**
     * Delete a file. No-op if file does not exist.
     */
    public function delete(string $path): void;

    /**
     * Check if a path exists.
     */
    public function exists(string $path): bool;

    /**
     * Copy a file from $source to $destination.
     */
    public function copy(string $source, string $destination): void;

    /**
     * Move (rename) a file.
     */
    public function move(string $source, string $destination): void;

    /**
     * Get file size in bytes.
     */
    public function size(string $path): int;

    /**
     * Get file modification time as a UNIX timestamp.
     */
    public function lastModified(string $path): int;

    /**
     * List files (not directories) in a directory. Non-recursive.
     * @return list<string>
     */
    public function files(string $directory = ''): array;

    /**
     * List directories inside a directory. Non-recursive.
     * @return list<string>
     */
    public function directories(string $directory = ''): array;

    /**
     * Create a directory (recursive).
     */
    public function makeDirectory(string $path): void;

    /**
     * Delete a directory and all its contents.
     */
    public function deleteDirectory(string $path): void;

    /**
     * Get the MIME type of a file.
     */
    public function mimeType(string $path): string;
}
