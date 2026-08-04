<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Micro;
use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\MicroException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Processes PHP 8 attributes on registered classes
 */
class AttributeProcessor implements MiddlewareInterface {

    /** @var string[] */
    protected array $handlerClasses = [];

    /** @var AttributeHandlerInterface[][] */
    protected array $handlers = [
        AttributeHandlerInterface::TARGET_CLASS    => [],
        AttributeHandlerInterface::TARGET_PROPERTY => [],
        AttributeHandlerInterface::TARGET_METHOD   => []
    ];

    /** @var string[] */
    protected array $namespaces = [];

    public function __construct(protected ?ConfigInterface $config = null) {}

    /**
     * Adds an attribute handler for processing
     *
     * The given class name should implement the AttributeHandler interface, otherwise
     * it will throw a MicroException.
     *
     * @throws MicroException if the given class does not implement AttributeHandler
     * @param string $className The class name
     */
    public function add(string $className): void {
        if (!is_subclass_of($className, AttributeHandlerInterface::class)) {
            throw new MicroException("$className doesn't implement the AttributeHandlerInterface interface");
        }
        $this->handlerClasses[] = $className;
    }

    /**
     * Adds a namespace
     *
     * If one or more namespace added only those will be processed. The namespace should NOT start with a backslash!
     *
     * @param string $namespace
     */
    public function addNamespace(string $namespace): void {
        $this->namespaces[] = $namespace;
    }

    public function run(): void {
        $this->loadNamespacesFromConfig();
        $this->discoverClassesFromNamespaces();
        $this->createHandlersPerTarget();
        $this->processAll();
    }

    /**
     * Reads `app.scan_namespaces` from config and merges with programmatically added namespaces
     */
    protected function loadNamespacesFromConfig(): void {
        if ($this->config === null) {
            return;
        }
        $value = $this->config->get('app.scan_namespaces', '');
        if ($value) {
            foreach (explode(',', $value) as $ns) {
                $ns = trim($ns);
                if ($ns && !in_array($ns, $this->namespaces)) {
                    $this->namespaces[] = $ns;
                }
            }
        }
    }

    /**
     * Discovers classes from Composer PSR-4 autoload map for configured namespaces
     *
     * Scans directories matching the configured namespaces, derives FQCNs from file paths,
     * and registers them with Micro::add(). Skips interfaces, abstract classes, and traits.
     */
    protected function discoverClassesFromNamespaces(): void {
        if (empty($this->namespaces) || $this->config === null) {
            return;
        }
        $rootPath = $this->config->rootPath();
        $psr4File = $rootPath . '/vendor/composer/autoload_psr4.php';
        if (!file_exists($psr4File)) {
            return;
        }
        $psr4Map = require $psr4File;
        foreach ($psr4Map as $prefix => $dirs) {
            foreach ($this->namespaces as $namespace) {
                // Bidirectional matching: configured namespace starts with PSR-4 prefix
                // or PSR-4 prefix starts with configured namespace
                $prefixNormalized = rtrim($prefix, '\\');
                if (!str_starts_with($namespace, $prefixNormalized) && !str_starts_with($prefixNormalized, $namespace)) {
                    continue;
                }
                foreach ($dirs as $dir) {
                    // If configured namespace is deeper than PSR-4 prefix, scan subdirectory
                    $subPath = '';
                    if (strlen($namespace) > strlen($prefixNormalized)) {
                        $subPath = str_replace('\\', '/', substr($namespace, strlen($prefix)));
                    }
                    $scanDir = rtrim($dir, '/') . ($subPath ? '/' . $subPath : '');
                    if (!is_dir($scanDir)) {
                        continue;
                    }
                    $classes = $this->scanDirectory($scanDir, $prefix, $subPath);
                    foreach ($classes as $fqcn) {
                        if (!Micro::hasInterface($fqcn)) {
                            Micro::add($fqcn);
                        }
                    }
                }
            }
        }
    }

    /**
     * Recursively scans a directory for PHP files and derives FQCNs
     *
     * @param string $dir The directory to scan
     * @param string $namespacePrefix The PSR-4 namespace prefix (e.g. "App\\")
     * @param string $subPath Additional sub-path within the namespace (e.g. "Controllers")
     * @return string[] Array of fully-qualified class names
     */
    protected function scanDirectory(string $dir, string $namespacePrefix, string $subPath = ''): array {
        $classes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen($dir) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativeClass = str_replace('/', '\\', substr($relativePath, 0, -4));
            $fqcn = $namespacePrefix . ($subPath ? str_replace('/', '\\', $subPath) . '\\' : '') . $relativeClass;
            try {
                $ref = new ReflectionClass($fqcn);
                if ($ref->isInterface() || $ref->isAbstract() || $ref->isTrait()) {
                    continue;
                }
            } catch (\ReflectionException $e) {
                continue;
            }
            $classes[] = $fqcn;
        }
        return $classes;
    }

    /**
     * Creates the handler instances and puts them into the right `$handlers` array
     */
    protected function createHandlersPerTarget(): void {
        foreach ($this->handlerClasses as $className) {
            $handler = Micro::get($className);
            foreach ($handler->targets() as $target) {
                $this->handlers[$target][] = $handler;
            }
        }
    }

    /**
     * Processes all interfaces in the App or those that are in the given namespaces
     */
    protected function processAll(): void {
        foreach (Micro::interfaces() as $className) {
            if ($this->isProcessAllowed($className)) {
                $this->process($className);
            }
        }
    }


    /**
     * If no namespace added returns true, otherwise checks the namespace and returns true if the interface is in it.
     * @param string $className The name of the class
     * @return bool Should we process this class?
     */
    protected function isProcessAllowed(string $className): bool {
        if (empty($this->namespaces)) {
            return true;
        }
        foreach ($this->namespaces as $namespace) {
            if (substr($className, 0, strlen($namespace)) == $namespace) {
                return true;
            }
        }
        return false;
    }

    /**
     * Processes one class with the given name
     * @param string $className The name of the class
     */
    protected function process(string $className): void {
        try {
            $refClass = new \ReflectionClass($className);
        } catch (\ReflectionException $ignore) {
            throw new MicroException("Can't create reflection for: $className");
        }
        $this->processClass($refClass);
        $this->processProperties($refClass);
        $this->processMethods($refClass);
    }

    /**
     * Processes all class-level attributes for the class
     * @param \ReflectionClass $refClass
     */
    protected function processClass(\ReflectionClass $refClass): void {
        foreach ($this->handlers[AttributeHandlerInterface::TARGET_CLASS] as $handler) {
            $this->processClassAttribute($handler, $refClass);
        }
    }

    /**
     * Finds a class-level attribute on the class or on the nearest ancestor that declares one
     *
     * PHP attributes are **not inherited**: `ReflectionClass::getAttributes()` on a subclass does
     * not see its parent's. Without this, an `#[Authorize]` on an abstract base controller would
     * apply to nothing at all - silently, and failing *open*, since every subclass would then be
     * reachable anonymously. Declaring "this whole area needs a login" once is worth having.
     *
     * The nearest declaration wins, so a subclass can still demand more than its base does. The
     * handler is given the *concrete* class name, which is the one a request will arrive at.
     *
     * Abstract classes are never registered with the container, so this walk is the only way
     * their attributes are seen at all.
     */
    protected function processClassAttribute(AttributeHandlerInterface $handler, \ReflectionClass $refClass): void {
        $current = $refClass;
        while ($current !== false) {
            if ($current->getAttributes($handler->attributeClass()) !== []) {
                $this->processSubject($handler, $refClass->getName(), $current);
                return;
            }
            $current = $current->getParentClass();
        }
    }

    /**
     * Processes all property-level attributes for all the properties of a class
     * @param \ReflectionClass $refClass
     */
    protected function processProperties(\ReflectionClass $refClass): void {
        $refProperties = $refClass->getProperties();
        foreach ($this->handlers[AttributeHandlerInterface::TARGET_PROPERTY] as $handler) {
            foreach ($refProperties as $refProperty) {
                $this->processSubject($handler, $refClass->getName(), $refProperty);
            }
        }
    }

    /**
     * Processes all method-level attributes for all the methods of a class
     * @param \ReflectionClass $refClass
     */
    protected function processMethods(\ReflectionClass $refClass): void {
        $refMethods = $refClass->getMethods();
        foreach ($this->handlers[AttributeHandlerInterface::TARGET_METHOD] as $handler) {
            foreach ($refMethods as $refMethod) {
                $this->processSubject($handler, $refClass->getName(), $refMethod);
            }
        }
    }

    /**
     * Processes attributes on a class, property or method
     *
     * Gets the PHP 8 attributes from the subject that match the handler's attribute class,
     * instantiates each and calls the handler's handle() method.
     *
     * @param AttributeHandler $handler The attribute handler
     * @param string $className The class name
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $subject The reflection class, property or method
     */
    protected function processSubject(AttributeHandlerInterface $handler, string $className, \ReflectionClass|\ReflectionProperty|\ReflectionMethod $subject): void {
        $attributes = $subject->getAttributes($handler->attributeClass());
        foreach ($attributes as $refAttribute) {
            $handler->handle($className, $subject, $refAttribute->newInstance());
        }
    }
}
