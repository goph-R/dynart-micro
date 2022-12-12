<?php

namespace Dynart\Micro;

class View {

    protected $app;
    protected $layout = 'layout';
    protected $lastBlock = [];
    protected $blocks = [];
    protected $data = [
        '__styles' => [],
        '__scripts' => []
    ];

    public function __construct(App $app) {
        $this->app = $app;
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

    public function routeUrl($route, $params=[], $amp='&amp;') {
        return $this->app->routeUrl($route, $params, $amp);
    }

    public function staticUrl(string $url) {
        if (substr($url, 0, 1) == '/') {
            $baseUrl = $this->app->config(App::CONFIG_BASE_URL);
            return $baseUrl.$url;
        }
        return $url;
    }

    public function escape(string $value) {
        return htmlspecialchars($value);
    }

    public function escapeAttribute(string $value) {
        return htmlspecialchars($value, ENT_QUOTES);
    }

    public function attributes(array $attributes, $startWithSpace=true) {
        $pairs = [];
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $pairs[] = $value;
            } else {
                $pairs[] = $name.'="'.$this->escapeAttribute($value).'"';
            }
        }
        $prefix = !empty($pairs) && $startWithSpace ? ' ' : '';
        return $prefix.join(' ', $pairs);
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
        $__viewsFolder = $this->app->config(App::CONFIG_VIEWS_FOLDER);
        $__path = $__viewsFolder.'/'.$__path.'.phtml';
        if (!file_exists($__path)) {
            $this->app->error(500, "Couldn't find view: ".$__path);
        }
        extract($this->data);
        extract($__vars);
        ob_start();
        include $__path;
        return ob_get_clean();
    }    
}