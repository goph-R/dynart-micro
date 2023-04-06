<?php

namespace Dynart\Micro;

class UploadedFile {

    protected $name;
    protected $tempPath;
    protected $error;
    protected $size;
    protected $type;

    public function __construct(string $name, string $tempPath, string $error, string $type, int $size) {
        $this->name = $name;
        $this->tempPath = $tempPath;
        $this->error = $error;
        $this->type = $type;
        $this->size = $size;
    }

    public function name() {
        return $this->name;
    }

    public function tempPath() {
        return $this->tempPath;
    }

    public function error() {
        return $this->error;
    }

    public function size() {
        return $this->size;
    }

    public function type() {
        return $this->type;
    }

    public function uploaded() {
        return is_uploaded_file($this->tempPath);
    }

    public function moveTo(string $path) {
        move_uploaded_file($this->tempPath, $path);
        $this->tempPath = null;
    }

}