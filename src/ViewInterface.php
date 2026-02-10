<?php

namespace Dynart\Micro;

/**
 * Template processor
 *
 * This class is used for server side HTML rendering with the help of PHP.
 */
interface ViewInterface {
    /**
     * Returns with a view variable value by name
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Sets a view variable
     */
    public function set(string $name, mixed $value): void;

    /**
     * Adds a script for the view
     */
    public function addScript(string $src, array $attributes = []): void;

    /**
     * Returns with the scripts in [src => [attributes]] array
     */
    public function scripts(): array;

    /**
     * Adds a style for the view
     */
    public function addStyle(string $src, array $attributes = []): void;

    /**
     * Returns with the styles in [src => [attributes]] array
     */
    public function styles(): array;

    /**
     * Sets the layout so after the template was rendered it will render this layout.
     * The path can contain a namespace if a folder was added with that namespace, for example:
     *
     * <pre>
     * $view->addFolder('folder', 'views/example');
     * $view->useLayout('folder:layout');
     * </pre>
     *
     * will render the 'views/example/layout.phtml'.
     */
    public function useLayout(string $path): void;

    /**
     * Returns with the current layout
     */
    public function layout(): string;

    /**
     * Returns with the content of a block
     */
    public function block(string $name): string;

    /**
     * Starts a block
     *
     * If the block has content, when the endBlock() will be called the content will be appended to the block.
     */
    public function startBlock(string $name): void;

    /**
     * Ends a block
     *
     * If the block has content, the new content will be appended to the block.
     */
    public function endBlock(): void;

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
     */
    public function addFolder(string $namespace, string $path): void;

    /**
     * Returns with the folder path by namespace
     */
    public function folder(string $namespace): string;

    /**
     * Sets the theme path for overriding templates
     */
    public function setTheme(string $path): void;

    /**
     * Returns with the theme path
     */
    public function theme(): string;

    /**
     * Fetches a template with variables
     */
    public function fetch(string $__viewPath, array $__vars = []): string;
}
