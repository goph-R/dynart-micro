<?php

namespace Dynart\Micro;

/**
 * Represents an uploaded file
 *
 * You can get and instance of this via the `Request::uploadedFile()` method.
 *
 * @see Request
 * @package Dynart\Micro
 */
class UploadedFile {

    protected $name;
    protected $tempPath;
    protected $error;
    protected $size;
    protected $type;

    public function __construct(string $name, string $tempPath, int $error, string $type, int $size) {
        $this->name = $name;
        $this->tempPath = $tempPath;
        $this->error = $error;
        $this->type = $type;
        $this->size = $size;
    }

    /**
     * The original name of the uploaded file
     * @return string
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * The temp path of the uploaded file
     * @return string
     */
    public function tempPath(): string {
        return $this->tempPath;
    }

    /**
     * The upload error. If no error happened the value is UPLOAD_ERR_OK (0)
     * @return int
     */
    public function error(): int {
        return $this->error;
    }

    /**
     * The size of the uploaded file in bytes
     * @return int
     */
    public function size(): int {
        return $this->size;
    }

    /**
     * The mime type of the uploaded file
     *
     * Important: do NOT trust this value, this is just set by the browser. If you need the real
     * mime type, you should analyze the file for it!
     *
     * @return string
     */
    public function type(): string {
        return $this->type;
    }

    /**
     * Tells whether the file was uploaded via HTTP POST
     * @return bool
     */
    public function uploaded(): bool {
        return is_uploaded_file($this->tempPath);
    }

    /**
     * Moves the uploaded file to the given path, the sets the tempPath to ''
     * @param string $path The destination
     * @return bool Is the move was successful?
     */
    public function moveTo(string $path): bool {
        $result = move_uploaded_file($this->tempPath, $path);
        $this->tempPath = '';
        return $result;
    }

}