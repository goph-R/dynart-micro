<?php

namespace Dynart\Micro;

class Request {

    protected $headers = [];
    protected $uploadedFiles = [];

    public function __construct() {
        if (function_exists('getallheaders')) { // because of command line purposes
            $this->headers = getallheaders();
        }
        if (!empty($_FILES)) {
            $this->createUploadedFiles();
        }
    }

    public function get(string $name, $default = null) {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    public function server(string $name, $default = null) {
        return array_key_exists($name, $_SERVER) ? $_SERVER[$name] : $default;
    }

    public function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    public function header(string $name, $default = null) {
        return isset($this->headers[$name]) ? $this->headers[$name] : $default;
    }

    public function cookie(string $name, $default = null) {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$name] : $default;
    }

    public function body() {
        return file_get_contents('php://input');
    }

    public function bodyAsJson() {
        $json = $this->body();
        if ($json) {
            $result = json_decode($json, true);
            if ($result) {
                return $result;
            }
            throw new AppException("The request body is not a valid JSON: ".$json);
        }
        return null;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function uploadedFile(string $name) {
        return isset($this->uploadedFiles[$name]) ? $this->uploadedFiles[$name] : null;
    }

    protected function createUploadedFiles() {
        foreach ($_FILES as $name => $file) {
            if (is_array($file['name'])) {
                $this->createUploadedFilesFromArray($file, $name);
            } else {
                $this->uploadedFiles[$name] = $this->createUploadedFile($file);
            }
        }
    }

    protected function createUploadedFilesFromArray($file, $name) {
        $this->uploadedFiles[$name] = [];
        foreach (array_keys($file['name']) as $index) {
            $this->uploadedFiles[$name][$index] = $this->createUploadedFile([
                'name'     => $file['name'][$index],
                'tmp_name' => $file['tmp_name'][$index],
                'error'    => $file['error'][$index],
                'type'     => $file['type'][$index],
                'size'     => $file['size'][$index]
            ]);
        }
    }

    protected function createUploadedFile(array $file): UploadedFile {
        return App::instance()->create(UploadedFile::class, [
            $file['name'], $file['tmp_name'], $file['error'], $file['type'], $file['size']
        ]);
    }

}