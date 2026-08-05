<?php

namespace Dynart\Micro;

class Request implements RequestInterface {

    /**
     * The addresses allowed to speak for their clients with `X-Forwarded-For`
     *
     * Comma separated, and empty by default: fail closed, so an installation that is not behind
     * a proxy cannot be told that it is.
     */
    const CONFIG_TRUSTED_PROXIES = 'request.trusted_proxies';

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

    /**
     * The address the request came from
     *
     * `REMOTE_ADDR` unless a **trusted** proxy said otherwise. `X-Forwarded-For` is a header, and
     * a header is whatever the client typed: this used to return it whenever it was present, so
     * anything counting or blocking by address could be given a different one on every request -
     * or somebody else's, which is worse, because then the count belongs to them.
     *
     * `request.trusted_proxies` is the list of addresses allowed to speak for their clients, and
     * it is empty by default: an installation that is not behind a proxy cannot be talked into
     * believing it is. Behind one, set it, or every visitor arrives as the proxy.
     *
     * The chain reads left to right, client first, each hop appending the one it heard from. The
     * rightmost entry that is not itself a trusted proxy is the last address a machine we trust
     * actually saw; everything left of it was written by somebody we have no reason to believe.
     */
    public function ip(): ?string {
        $remote = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $trusted = $this->trustedProxies();
        if ($remote === null || $trusted === [] || !in_array($remote, $trusted, true)) {
            return $remote;
        }
        $forwarded = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
        foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $address) {
            if ($address !== '' && !in_array($address, $trusted, true)) {
                return $address;
            }
        }
        return $remote;
    }

    /**
     * @return string[] The addresses whose `X-Forwarded-For` is believed
     */
    protected function trustedProxies(): array {
        // from the container rather than the constructor, because `new Request()` has to keep
        // working: it is built by hand in tests and wherever there is no application at all
        return Micro::hasInterface(ConfigInterface::class)
            ? Micro::get(ConfigInterface::class)->getCommaSeparatedValues(self::CONFIG_TRUSTED_PROXIES)
            : [];
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
