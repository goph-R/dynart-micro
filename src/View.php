<?php

namespace Dynart\Micro;

/**
 * Template processor
 *
 * This class is used for server side HTML rendering with the help of PHP.
 *
 * @package Dynart\Micro
 */
class View {

    const CONFIG_DEFAULT_FOLDER = 'view.default_folder';

    /** @var Router */
    protected $router;

    /** @var Config */
    protected $config;

    /**
     * The layout for the currently fetched template
     * @var string
     */
    protected $layout = '';

    /**
     * The theme path for overriding templates
     * @var string
     */
    protected $theme = '';

    /**
     * Holds the blocks queue for start/end block functions
     * @var array
     */
    protected $blockQueue = [];

    /**
     * Holds the content of the blocks by name
     * @var array
     */
    protected $blocks = [];

    /**
     * Stores the paths for the folders by namespace
     * @var array
     */
    protected $folders = [];

    /**
     * Holds the view variables
     * @var array
     */
    protected $data = [];

    /**
     * Holds the scripts for the view
     * @var array
     */
    protected $scripts = [];

    /**
     * Holds the styles for the view
     * @var array
     */
    protected $styles = [];

    /**
     * The functions were included?
     * @var bool
     */
    protected $functionsIncluded = false;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    /**
     * Returns with a view variable value by name
     * @param string $name The name of the variable
     * @param mixed|null $default The default value if the variable doesn't present
     * @return mixed|null The value of the variable
     */
    public function get(string $name, $default = null) {
        return isset($this->data[$name]) ? $this->data[$name] : $default;
    }

    /**
     * Sets a view variable
     * @param string $name The name of the variable
     * @param mixed $value The value of the variable
     */
    public function set(string $name, $value): void {
        $this->data[$name] = $value;
    }

    /**
     * Adds a script for the view
     * @param string $src
     * @param array $attributes
     */
    public function addScript(string $src, array $attributes=[]): void {
        $this->scripts[$src] = $attributes;
    }

    /**
     * Returns with the scripts in [src => [attributes]] array
     * @return array The scripts array
     */
    public function scripts(): array {
        return $this->scripts;
    }

    /**
     * Adds a style for the view
     * @param string $src
     * @param array $attributes
     */
    public function addStyle(string $src, array $attributes=[]): void {
        $this->styles[$src] = $attributes;
    }

    /**
     * Returns with the styles in [src => [attributes]] array
     * @return array The styles array
     */
    public function styles(): array {
        return $this->styles;
    }

    /**
     * Sets the `$layout` so after the template was rendered it will render this layout.
     * The path can contain a namespace if a folder was added with that namespace, for example:
     *
     * <pre>
     * $view->addFolder('folder', 'views/example');
     * $view->useLayout('folder:layout');
     * </pre>
     *
     * will render the 'views/example/layout.phtml'.
     *
     * @param string $path The path for the layout, for example: 'layout' is for 'views/layout.phtml'
     */
    public function useLayout(string $path): void {
        $this->layout = $path;
    }

    /**
     * Returns with the current layout
     * @return string The current layout
     */
    public function layout(): string {
        return $this->layout;
    }

    /**
     * Returns with the content of a block
     * @param string $name
     * @return string
     */
    public function block(string $name): string {
        return isset($this->blocks[$name]) ? $this->blocks[$name] : '';
    }

    /**
     * Starts a block
     *
     * If the block has content, when the endBlock() will be called the content will be appended to the block.
     *
     * @param string $name The name of the block
     */
    public function startBlock(string $name): void {
        if (!isset($this->blocks[$name])) {
            $this->blocks[$name] = '';
        }
        $this->blockQueue[] = $name;
        ob_start();
    }

    /**
     * Ends a block
     *
     * If the block has content, the new content will be appended to the block.
     */
    public function endBlock(): void {
        $name = array_pop($this->blockQueue);
        $this->blocks[$name] .= ob_get_clean();
    }

    /**
     * Adds a view folder
     *
     * For example, if the `app.root_path` config value is '/var/www/example.com', and you have the following code:
     *
     * <pre>
     * $view->addFolder('folder', '~/views/example');
     * $view->fetch('folder:index');
     * </pre>
     *
     * will fetch the '/var/www/example.com/views/example/index.phtml'.
     *
     * The `$path` should NOT end with a '/'
     *
     * @param string $namespace The namespace of the folder
     * @param string $path The path to the folder. It can be an absolute or relative path as well.
     */
    public function addFolder(string $namespace, string $path): void {
        $this->folders[$namespace] = $path;
    }

    /**
     * Returns with the folder path by namespace
     * @param string $namespace
     * @return string The path for the folder
     */
    public function folder(string $namespace): string {
        return $this->folders[$namespace];
    }

    /**
     * Sets the theme path for overriding templates
     * @param string $path
     */
    public function setTheme(string $path): void {
        $this->theme = $path;
    }

    /**
     * Returns with the theme path
     * @return string
     */
    public function theme(): string {
        return $this->theme;
    }

    /**
     * Fetches a template with variables
     * @param string $__viewPath The path to the view
     * @param array $__vars The variables in [name => value] format
     * @return string The fetched template output in string
     */
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
     * @param string $path The view path
     * @return string The real path to the template file
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

    /**
     * Includes the functions.php of the theme, of the app and of the framework
     */
    protected function includeFunctions() {
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