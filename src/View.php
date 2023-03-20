<?php

namespace Dynart\Micro;

class View {

    /** @var Router */
    protected $router;

    /** @var Config */
    protected $config;

    protected $layout = 'layout';
    protected $lastBlock = [];
    protected $blocks = [];
    protected $data = [
        '__styles' => [],
        '__scripts' => []
    ];

    public function __construct(Config $config, Router $router) {        
        $this->config = $config;
        $this->router = $router;
        $functionsPath = $this->config->get('app.views_folder').'/functions.php';
        if (file_exists($functionsPath)) {
            require_once($functionsPath);
        }
    }

    public function data($name, $value=null) {
        if ($value === null) {
            return isset($this->data[$name]) ? $this->data[$name] : null;
        }
        $this->data[$name] = $value;
    }

    public function addScript(string $src, array $attributes=[]) {
        $this->data['__scripts'][$src] = $attributes;
    }

    public function addStyle(string $src, array $attributes=[]) {
        $this->data['__styles'][$src] = $attributes;
    }

    public function setLayout(string $path) {
        $this->layout = $path;
    }

    public function layout(string $path, array $vars=[]) {
        $this->fetch($path, $vars); // set 'content' and 'scripts' blocks
        return $this->fetch($this->layout, $vars);
    }

    public function block(string $name) {
        return isset($this->blocks[$name]) ? $this->blocks[$name] : '';
    }

    public function startBlock(string $name) {
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = '';
        }
        $this->lastBlock[] = $name;
        ob_start();
    }

    public function endBlock() {
        $name = array_pop($this->lastBlock);
        $this->blocks[$name] .= ob_get_clean();
    }

    public function fetch(string $__path, array $__vars=[]) {
        $__path = $this->config->get('app.views_folder').'/'.$__path.'.phtml';
        /*
        if (!file_exists($__path)) {
            $this->app->sendError(500, "Couldn't find view: ".$__path);
        }
        */
        extract($this->data);
        extract($__vars);
        ob_start();
        include $__path;
        return ob_get_clean();
    }
}