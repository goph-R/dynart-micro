<?php

namespace Dynart\Micro;

class Request implements RequestInterface {

    /** The incoming HTTP request headers. */
    protected array $headers = [];

    /** The incoming uploaded files. */
    protected array $uploadedFiles = [];

    /** Stores the request body */
    protected string $body = '';

    /**
     * Fills up the `headers` and the `uploadedFiles` arrays.
     */
    public function __construct() {
        $headers = function_exists('getallheaders') ? getallheaders() : ['x-test-header' => 'test-value'];
        foreach ($headers as $key => $value) {
            $this->headers[strtolower($key)] = $value;
        }
        if (!empty($_FILES)) {
            $this->createUploadedFiles();
        }
        $this->body = file_get_contents('php://input') ?: '';
    }

    public function get(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    public function server(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_SERVER) ? $_SERVER[$name] : $default;
    }

    public function httpMethod(): string {
        return $this->server('REQUEST_METHOD');
    }

    public function ip(): ?string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    public function setHeader(string $name, string $value): void {
        $this->headers[strtolower($name)] = $value;
    }

    public function header(string $name, mixed $default = null): mixed {
        $lowerName = strtolower($name);
        return isset($this->headers[$lowerName]) ? $this->headers[$lowerName] : $default;
    }

    public function cookie(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$name] : $default;
    }

    public function body(): string {
        return $this->body;
    }

    public function setBody(string $content): void {
        $this->body = $content;
    }

    public function bodyAsJson(): ?array {
        $json = $this->body();
        if (!$json) {
            return null;
        }
        $result = json_decode($json, true);
        if ($result) {
            return $result;
        }
        throw new MicroException("The request body is not a valid JSON: ".$json);
    }

    public function uploadedFile(string $name): UploadedFile|array|null {
        return isset($this->uploadedFiles[$name]) ? $this->uploadedFiles[$name] : null;
    }

    /**
     * It will fill up the `uploadedFiles` array
     */
    protected function createUploadedFiles(): void {
        foreach ($_FILES as $name => $file) {
            if (is_array($file['name'])) {
                $this->createUploadedFilesFromArray($name, $file);
            } else {
                $this->uploadedFiles[$name] = $this->createUploadedFile($file);
            }
        }
    }

    /**
     * It will create an UploadedFile array by parameter name and puts into the `uploadedFiles` array
     */
    protected function createUploadedFilesFromArray(string $name, array $file): void {
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

    /**
     * It will create an UploadedFile instance by an array (one element from the $_FILES)
     */
    protected function createUploadedFile(array $file): UploadedFile {
        return Micro::create(UploadedFile::class, [
            $file['name'], $file['tmp_name'], $file['error'], $file['type'], $file['size']
        ]);
    }

}
