<?php

namespace Dynart\Micro;

/**
 * Represents the HTTP request
 *
 * It can be used for getting the information of the HTTP request: the request method (POST, GET, etc.),
 * the query parameters, the headers, the information that created by the web server, the cookies
 * and the uploaded files (@see UploadedFile).
 *
 * @package Dynart\Micro
 */
class Request {

    /**
     * The incoming HTTP request headers.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * The incoming uploaded files.
     *
     * @var array
     */
    protected $uploadedFiles = [];

    /**
     * Stores the request body
     * @var string
     */
    protected $body;

    /**
     * The Request constructor
     *
     * It will fill up the `headers` and the `uploadedFiles` arrays.
     *
     * @see Request::$headers
     * @see Request::$uploadedFiles
     */
    public function __construct() {
        if (function_exists('getallheaders')) { // because of command line purposes
            foreach (getallheaders() as $key => $value) {
                $this->headers[strtolower($key)] = $value;
            }
        }
        if (!empty($_FILES)) {
            $this->createUploadedFiles();
        }
        $this->body = file_get_contents('php://input');
    }

    /**
     * Returns with a parameter of the request, uses the $_REQUEST array
     *
     * @param string $name The name of the parameter
     * @param mixed|null $default The default value if the parameter doesn't present
     * @return mixed|null The value of the parameter or the default value
     */
    public function get(string $name, $default = null) {
        return array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : $default;
    }

    /**
     * Returns with the information that was created by the web server, uses the $_SERVER array
     *
     * @param string $name The name of the server info
     * @param mixed|null $default The default value if the info doesn't present
     * @return mixed|null The info created by the web server or the default value
     */
    public function server(string $name, $default = null) {
        return array_key_exists($name, $_SERVER) ? $_SERVER[$name] : $default;
    }

    /**
     * Returns the HTTP request method
     *
     * @return string The request method. Can be GET, POST, PUT, OPTIONS, PATCH, DELETE
     */
    public function method(): string {
        return $this->server('REQUEST_METHOD');
    }

    /**
     * Returns with the IP of the client if present, otherwise null
     *
     * Important! This value can't be trusted, this is just for hashing/logging purposes.
     *
     * @return string|null
     */
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

    /**
     * Sets a request header
     *
     * @param string $name The name of the header
     * @param string $value The value of the header
     */
    public function setHeader(string $name, string $value) {
        $this->headers[strtolower($name)] = $value;
    }

    /**
     * Returns with a request header by name
     *
     * @param string $name The header name
     * @param mixed|null $default The default value if the header doesn't present
     * @return mixed|null The header value or the default value
     */
    public function header(string $name, $default = null) {
        $lowerName = strtolower($name);
        return isset($this->headers[$lowerName]) ? $this->headers[$lowerName] : $default;
    }

    /**
     * Returns with a cookie value by name
     *
     * @param string $name The name of the cookie
     * @param mixed|null $default The default value if the cookie doesn't present
     * @return mixed|null The cookie value or the default value
     */
    public function cookie(string $name, $default = null) {
        return array_key_exists($name, $_COOKIE) ? $_COOKIE[$name] : $default;
    }

    /**
     * Returns with the request body
     *
     * @return bool|string The request body
     */
    public function body() {
        return $this->body;
    }

    /**
     * Sets the request body
     *
     * @param string $content
     */
    public function setBody(string $content) {
        $this->body = $content;
    }

    /**
     * Returns with the request body as an associative array parsed from JSON
     *
     * Throws an AppException if the JSON is invalid.
     *
     * @throws AppException
     * @return mixed|null Returns with an associative array from parsed JSON or null if the request body is empty
     */
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
     * Returns with the uploaded file by parameter name
     *
     * If the parameter not present it will return with a null.
     * if only one file uploaded it will return with an UploadedFile instance
     * If the more than one file uploaded it will return with an array.
     *
     * @param string $name The name of the parameter of the POST request
     * @return UploadedFile|UploadedFile[]|null Returns the UploadedFile instance or array or null
     */
    public function uploadedFile(string $name) {
        return isset($this->uploadedFiles[$name]) ? $this->uploadedFiles[$name] : null;
    }

    /**
     * It will fill up the `uploadedFiles` array
     */
    protected function createUploadedFiles() {
        foreach ($_FILES as $name => $file) {
            if (is_array($file['name'])) {
                $this->createUploadedFilesFromArray($name, $file);
            } else {
                $this->uploadedFiles[$name] = $this->createUploadedFile($file);
            }
        }
    }

    /**
     * It will create an UploadedFile array by parameter name and puts in the `uploadedFiles` array
     *
     * @param string $name The name of the parameter
     * @param array $file One element of the $_FILES array
     */
    protected function createUploadedFilesFromArray($name, $file) {
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
     *
     * @param array $file One element of the $_FILES array
     * @return UploadedFile The UploadedFile instance
     */
    protected function createUploadedFile(array $file): UploadedFile {
        return App::instance()->create(UploadedFile::class, [
            $file['name'], $file['tmp_name'], $file['error'], $file['type'], $file['size']
        ]);
    }

}