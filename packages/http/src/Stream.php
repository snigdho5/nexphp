<?php

declare(strict_types=1);

namespace Nex\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class Stream implements StreamInterface
{
    /**
     * @var resource|null
     */
    private mixed $resource;

    private bool $seekable;
    private bool $readable;
    private bool $writable;

    /**
     * @param resource|string $body
     */
    public function __construct(mixed $body = '')
    {
        if (is_string($body)) {
            $resource = fopen('php://temp', 'r+');
            if ($resource === false) {
                throw new RuntimeException('Unable to open temporary memory stream.');
            }
            if ($body !== '') {
                fwrite($resource, $body);
                fseek($resource, 0);
            }
            $this->resource = $resource;
        } elseif (is_resource($body)) {
            $this->resource = $body;
        } else {
            throw new RuntimeException('Stream body must be a string or a resource.');
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        $this->seekable = $meta['seekable'];
        $this->readable = str_contains($mode, 'r') || str_contains($mode, '+');
        $this->writable = str_contains($mode, 'w') || str_contains($mode, '+') || str_contains($mode, 'a');
    }

    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            if (is_resource($this->resource)) {
                fclose($this->resource);
            }
            $this->detach();
        }
    }

    public function detach(): mixed
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $result = ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }

        return $result;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable || $this->resource === null) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Error seeking within stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (!$this->writable || $this->resource === null) {
            throw new RuntimeException('Stream is not writable.');
        }

        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (!$this->readable || $this->resource === null) {
            throw new RuntimeException('Stream is not readable.');
        }

        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $result;
    }

    public function getContents(): string
    {
        if (!$this->readable || $this->resource === null) {
            throw new RuntimeException('Stream is not readable.');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
