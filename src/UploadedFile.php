<?php

namespace Dynart\Micro;

class UploadedFile {

    protected $name;
    protected $tempPath;
    protected $error;
    protected $size;
    protected $type;

    public function __construct(array $data, $index = null) {
        if ($index === null) {
            $this->name = $data['name'];
            $this->tempPath = $data['tmp_name'];
            $this->error = $data['error'];
            $this->type = $data['type'];
            $this->size = $data['size'];
        } else {
            $this->name = $data['name'][$index];
            $this->tempPath = $data['tmp_name'][$index];
            $this->error = $data['error'][$index];
            $this->type = $data['type'][$index];
            $this->size = $data['size'][$index];
        }
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