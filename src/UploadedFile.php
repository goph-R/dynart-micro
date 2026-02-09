<?php

namespace Dynart\Micro;

/**
 * Represents an uploaded file
 *
 * You can get an instance of this via the `Request::uploadedFile()` method.
 *
 * @see Request
 */
class UploadedFile {

    public function __construct(
        protected string $name,
        protected string $tempPath,
        protected int $error,
        protected string $type,
        protected int $size
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function tempPath(): string {
        return $this->tempPath;
    }

    public function error(): int {
        return $this->error;
    }

    public function size(): int {
        return $this->size;
    }

    /**
     * Important: do NOT trust this value, this is just set by the browser. If you need the real
     * mime type, you should analyze the file for it!
     */
    public function type(): string {
        return $this->type;
    }

    public function uploaded(): bool {
        return is_uploaded_file($this->tempPath);
    }

    public function moveTo(string $path): bool {
        $result = move_uploaded_file($this->tempPath, $path);
        $this->tempPath = '';
        return $result;
    }

}
