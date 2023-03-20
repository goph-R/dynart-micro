<?php

use Dynart\Micro\App;
use Dynart\Micro\Config;
use Dynart\Micro\Request;
use Dynart\Micro\Router;

function base_url() {
    return App::instance()->get(Config::class)->get('app.base_url');
}

function url(string $uri, bool $withMTime = true) {
    $result = base_url().$uri;
    if ($withMTime) {
        $result .= '?'.filemtime(App::instance()->get(Config::class)->get('app.path_root').$uri);
    }
    return $result;
}

function route_url(string $route, array $params = []) {
    return App::instance()->get(Router::class)->url($route, $params, '&amp;');
}

function esc_html(string $text) {
    return htmlspecialchars($text);
}

function esc_attr(string $value) {
    return htmlspecialchars($value, ENT_QUOTES);
}

function esc_attrs(array $attributes, $startWithSpace = true) {
    $pairs = [];
    foreach ($attributes as $name => $value) {
        if (is_int($name)) {
            $pairs[] = $value;
        } else {
            $pairs[] = $name.'="'.esc_attr($value).'"';
        }
    }
    $prefix = !empty($pairs) && $startWithSpace ? ' ' : '';
    return $prefix.join(' ', $pairs);
}

// photos related

function site_url(string $uri, bool $withMTime = true) {
    return url('/sites/'.App::instance()->get(Request::class)->get('dir').$uri, $withMTime);
}

function getthumb_url($uri) {
    return base_url().App::instance()->get(Config::class)->get('app.getthumb_prefix').$uri;
}

