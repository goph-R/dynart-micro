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

    /**
     * The original name of the uploaded file
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * The temp path of the uploaded file
     */
    public function tempPath(): string {
        return $this->tempPath;
    }

    /**
     * The upload error. If no error happened the value is UPLOAD_ERR_OK (0)
     */
    public function error(): int {
        return $this->error;
    }

    /**
     * The size of the uploaded file in bytes
     */
    public function size(): int {
        return $this->size;
    }

    /**
     * The mime type of the uploaded file
     *
     * Important: do NOT trust this value, this is just set by the browser. If you need the real
     * mime type, you should analyze the file for it!
     */
    public function type(): string {
        return $this->type;
    }

    /**
     * Tells whether the file was uploaded via HTTP POST
     */
    public function uploaded(): bool {
        return is_uploaded_file($this->tempPath);
    }

    /**
     * Moves the uploaded file to the given path, then sets the tempPath to ''
     */
    public function moveTo(string $path): bool {
        $result = move_uploaded_file($this->tempPath, $path);
        $this->tempPath = '';
        return $result;
    }

}
