<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP request
 *
 * It can be used for getting the information of the HTTP request: the request method (POST, GET, etc.),
 * the query parameters, the headers, the information that created by the web server, the cookies
 * and the uploaded files.
 *
 * @see UploadedFile
 */
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

    /**
     * Returns with a parameter of the request, uses the $_REQUEST array
     */
    public function get(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    /**
     * Returns with the information that was created by the web server, uses the $_SERVER array
     */
    public function server(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_SERVER) ? $_SERVER[$name] : $default;
    }

    /**
     * Returns the HTTP request method
     */
    public function httpMethod(): string {
        return $this->server('REQUEST_METHOD');
    }

    /**
     * Returns with the IP of the client if present, otherwise null
     *
     * Important! This value can't be trusted, this is just for hashing/logging purposes.
     */
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

    /**
     * Sets a request header
     */
    public function setHeader(string $name, string $value): void {
        $this->headers[strtolower($name)] = $value;
    }

    /**
     * Returns with a request header by name
     */
    public function header(string $name, mixed $default = null): mixed {
        $lowerName = strtolower($name);
        return isset($this->headers[$lowerName]) ? $this->headers[$lowerName] : $default;
    }

    /**
     * Returns with a cookie value by name
     */
    public function cookie(string $name, mixed $default = null): mixed {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$name] : $default;
    }

    /**
     * Returns with the request body
     */
    public function body(): string {
        return $this->body;
    }

    /**
     * Sets the request body
     */
    public function setBody(string $content): void {
        $this->body = $content;
    }

    /**
     * Returns with the request body as an associative array parsed from JSON
     *
     * Throws a MicroException if the JSON is invalid.
     *
     * @throws MicroException
     */
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

    /**
     * Returns with the uploaded file by parameter name
     *
     * If the parameter not present it will return with a null.
     * If only one file uploaded it will return with an UploadedFile instance.
     * If more than one file uploaded it will return with an array.
     *
     * @return UploadedFile|UploadedFile[]|null
     */
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
