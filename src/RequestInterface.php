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
interface RequestInterface {
    /**
     * Returns with a parameter of the request, uses the $_REQUEST array
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Returns with the information that was created by the web server, uses the $_SERVER array
     */
    public function server(string $name, mixed $default = null): mixed;

    /**
     * Returns the HTTP request method
     */
    public function httpMethod(): string;

    /**
     * Returns with the IP of the client if present, otherwise null
     *
     * Important! This value can't be trusted, this is just for hashing/logging purposes.
     */
    public function ip(): ?string;

    /**
     * Sets a request header
     */
    public function setHeader(string $name, string $value): void;

    /**
     * Returns with a request header by name
     */
    public function header(string $name, mixed $default = null): mixed;

    /**
     * Returns with a cookie value by name
     */
    public function cookie(string $name, mixed $default = null): mixed;

    /**
     * Returns with the request body
     */
    public function body(): string;

    /**
     * Sets the request body
     */
    public function setBody(string $content): void;

    /**
     * Returns with the request body as an associative array parsed from JSON
     *
     * Throws a MicroException if the JSON is invalid.
     *
     * @throws MicroException
     */
    public function bodyAsJson(): ?array;

    /**
     * Returns with the uploaded file by parameter name
     *
     * If the parameter not present it will return with a null.
     * If only one file uploaded it will return with an UploadedFile instance.
     * If more than one file uploaded it will return with an array.
     *
     * @return UploadedFile|UploadedFile[]|null
     */
    public function uploadedFile(string $name): UploadedFile|array|null;
}
