<?php

namespace Dynart\Micro;

class View implements ViewInterface {

    const CONFIG_DEFAULT_FOLDER = 'view.default_folder';

    /**
     * The namespace of the templates the framework ships
     *
     * Registered automatically, so the built in partials (the form ones) are findable without
     * every application having to copy them into its own view folder or point
     * `view.default_folder` at the framework. A theme overrides them the usual way, under
     * `<theme>/micro/`.
     */
    const NAMESPACE_MICRO = 'micro';

    protected ConfigInterface $config;

    /** The layout for the currently fetched template */
    protected string $layout = '';

    /** The theme path for overriding templates */
    protected string $theme = '';

    /** Holds the blocks queue for start/end block functions */
    protected array $blockQueue = [];

    /** Holds the content of the blocks by name */
    protected array $blocks = [];

    /** Stores the paths for the folders by namespace */
    protected array $folders = [];

    /** Holds the view variables */
    protected array $data = [];

    /** Holds the scripts in [priority => [src => attributes]] form */
    protected array $scripts = [];
    protected array $scriptsRegistry = [];
    /** Holds the styles in [priority => [src => attributes]] form */
    protected array $styles = [];
    protected array $stylesRegistry = [];

    /** The functions were included? */
    protected bool $functionsIncluded = false;

    /**
     * How deep the current `fetch()` is nested
     *
     * Blocks accumulate on purpose, so several templates can contribute to the same one. That
     * only makes sense within a single render, though, so the blocks are cleared when a
     * top level fetch starts - otherwise a template rendered earlier in the request (a mail, a
     * partial fetched from a service) would still have its content in there.
     */
    protected int $fetchDepth = 0;

    public function __construct(ConfigInterface $config) {
        $this->config = $config;
        $this->folders[self::NAMESPACE_MICRO] = dirname(__FILE__).'/../views';
    }

    public function get(string $name, mixed $default = null): mixed {
        return $this->data[$name] ?? $default;
    }

    public function set(string $name, mixed $value): void {
        $this->data[$name] = $value;
    }

    public function addScript(string $src, array $attributes=[], int $priority = 50): void {
        if (in_array($src, $this->scriptsRegistry)) {
            return;
        }
        $this->scriptsRegistry[] = $src;
        if (!isset($this->scripts[$priority])) {
            $this->scripts[$priority] = [];
        }
        $this->scripts[$priority][$src] = $attributes;
    }

    public function scripts(): array {
        ksort($this->scripts);
        $flattened = [];
        foreach ($this->scripts as $priorityGroup) {
            foreach ($priorityGroup as $src => $attributes) {
                $flattened[$src] = $attributes;
            }
        }
        return $flattened;
    }

    public function addStyle(string $src, array $attributes=[], int $priority = 50): void {
        if (in_array($src, $this->stylesRegistry)) {
            return;
        }
        $this->stylesRegistry[] = $src;
        if (!isset($this->styles[$priority])) {
            $this->styles[$priority] = [];
        }
        $this->styles[$priority][$src] = $attributes;
    }

    public function styles(): array {
        ksort($this->styles);
        $flattened = [];
        foreach ($this->styles as $priorityGroup) {
            foreach ($priorityGroup as $src => $attributes) {
                $flattened[$src] = $attributes;
            }
        }
        return $flattened;
    }

    public function useLayout(string $path): void {
        $this->layout = $path;
    }

    public function layout(): string {
        return $this->layout;
    }

    public function block(string $name): string {
        return $this->blocks[$name] ?? '';
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

    /**
     * Is there a template at this path?
     *
     * Resolved exactly like `fetch()` does, theme override included. For optional templates,
     * where catching the exception from `fetch()` would also swallow a `MicroException` thrown
     * from inside a template that does exist.
     */
    public function exists(string $path): bool {
        return file_exists($this->getRealPath($path).'.phtml');
    }

    public function fetch(string $__viewPath, array $__vars=[]): string {
        $this->includeFunctions();
        $__path = $this->getRealPath($__viewPath).'.phtml';
        if (!file_exists($__path)) {
            throw new MicroException("Can't find view: $__viewPath, $__path");
        }
        if ($this->fetchDepth === 0) {
            $this->blocks = [];
        }
        // A nested fetch has to start with no layout of its own, otherwise a partial rendered
        // from inside a template that uses a layout would inherit it and render the whole page
        // into the partial's output. `Form::fetch()` does exactly that.
        $__previousLayout = $this->layout;
        $this->layout = '';
        $this->fetchDepth++;
        try {
            extract($this->data);
            extract($__vars);
            ob_start();
            include $__path;
            $result = ob_get_clean();
            $layout = $this->layout;
            $this->layout = $__previousLayout;
            if ($layout) {
                // still nested, so the blocks the template just filled are there for the layout
                $result = $this->fetch($layout, $__vars);
            }
        } finally {
            $this->fetchDepth--;
        }
        return $result;
    }

    /**
     * Returns with the real path to the template file
     *
     * * If the path doesn't contain a namespace it will use the `view.default_folder` config value to determine the path for the folder.
     * * If the path contains a namespace it will use the folder of the namespace.
     * * If the view has a theme, that path will be checked first, so the theme can override every template.
     *
     * For example if you added a folder with namespace 'folder':
     *
     * <pre>
     * $view->addFolder('folder', '~/views/example');
     * </pre>
     *
     * and the `app.root_path` config value is '/var/www/example.com'
     * the result will be '/var/www/example.com/views/example/index.phtml'
     *
     * @throws MicroException If the view path has a namespace but a folder wasn't added for it
     */
    protected function getRealPath(string $path): string {
        $colonPos = strpos($path, ':');
        if ($colonPos !== false) {
            $namespace = substr($path, 0, $colonPos);
            if (!isset($this->folders[$namespace])) {
                throw new MicroException("Folder wasn't added with namespace: $namespace");
            }
            $folder = $this->folders[$namespace];
            $name = substr($path, $colonPos + 1);
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

    /**
     * Includes the functions.php of the theme, of the app and of the framework
     */
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
