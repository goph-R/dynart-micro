<?php

namespace Dynart\Micro;

/**
 * Template processor
 */
class View {

    const CONFIG_DEFAULT_FOLDER = 'view.default_folder';

    protected Config $config;
    protected string $layout = '';
    protected string $theme = '';
    protected array $blockQueue = [];
    protected array $blocks = [];
    protected array $folders = [];
    protected array $data = [];
    protected array $scripts = [];
    protected array $styles = [];
    protected bool $functionsIncluded = false;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    public function get(string $name, mixed $default = null): mixed {
        return isset($this->data[$name]) ? $this->data[$name] : $default;
    }

    public function set(string $name, mixed $value): void {
        $this->data[$name] = $value;
    }

    public function addScript(string $src, array $attributes=[]): void {
        $this->scripts[$src] = $attributes;
    }

    public function scripts(): array {
        return $this->scripts;
    }

    public function addStyle(string $src, array $attributes=[]): void {
        $this->styles[$src] = $attributes;
    }

    public function styles(): array {
        return $this->styles;
    }

    public function useLayout(string $path): void {
        $this->layout = $path;
    }

    public function layout(): string {
        return $this->layout;
    }

    public function block(string $name): string {
        return isset($this->blocks[$name]) ? $this->blocks[$name] : '';
    }

    public function startBlock(string $name): void {
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = '';
        }
        $this->blockQueue[] = $name;
        ob_start();
    }

    public function endBlock(): void {
        $name = array_pop($this->blockQueue);
        $this->blocks[$name] .= ob_get_clean();
    }

    public function addFolder(string $namespace, string $path): void {
        $this->folders[$namespace] = $path;
    }

    public function folder(string $namespace): string {
        return $this->folders[$namespace];
    }

    public function setTheme(string $path): void {
        $this->theme = $path;
    }

    public function theme(): string {
        return $this->theme;
    }

    public function fetch(string $__viewPath, array $__vars=[]): string {
        $this->includeFunctions();
        $__path = $this->getRealPath($__viewPath).'.phtml';
        if (!file_exists($__path)) {
            throw new MicroException("Can't find view: $__viewPath, $__path");
        }
        extract($this->data);
        extract($__vars);
        ob_start();
        include $__path;
        $result = ob_get_clean();
        $layout = $this->layout;
        if ($layout) {
            $this->layout = '';
            $result = $this->fetch($layout, $__vars);
        }
        return $result;
    }

    /**
     * @throws MicroException If the view path has a namespace but a folder wasn't added for it
     */
    protected function getRealPath(string $path): string {
        $dotPos = strpos($path, ':');
        if ($dotPos !== false) {
            $namespace = substr($path, 0, $dotPos);
            if (!isset($this->folders[$namespace])) {
                throw new MicroException("Folder wasn't added with namespace: $namespace");
            }
            $folder = $this->folders[$namespace];
            $name = substr($path, $dotPos + 1);
            $themePath = $this->theme.'/'.$namespace.'/'.$name;
        } else {
            $folder = $this->config->get(self::CONFIG_DEFAULT_FOLDER);
            $name = $path;
            $themePath = $this->theme.'/'.$path;
        }
        if ($this->theme) {
            $themeFullPath = $this->config->getFullPath($themePath);
            if (file_exists($themeFullPath.'.phtml')) {
                return $themeFullPath;
            }
        }
        return $this->config->getFullPath($folder.'/'.$name);
    }

    protected function includeFunctions(): void {
        if ($this->functionsIncluded) {
            return;
        }
        $themeFunctionsPath = $this->config->getFullPath($this->theme.'/functions.php');
        $appFunctionsPath = $this->config->getFullPath($this->config->get(self::CONFIG_DEFAULT_FOLDER).'/'.'functions.php');
        $defaultFunctionsPath = dirname(__FILE__) . '/../views/functions.php';
        if ($this->theme && file_exists($themeFunctionsPath)) {
            require_once $themeFunctionsPath;
        }
        if (file_exists($appFunctionsPath)) {
            require_once $appFunctionsPath;
        }
        require_once $defaultFunctionsPath;
        $this->functionsIncluded = true;
    }
}
