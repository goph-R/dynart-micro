<?php

namespace Dynart\Micro;

/**
 * Processes the annotations that are in the PHP document comments
 * @package Dynart\Micro
 */
class AnnotationProcessor implements Middleware {

    /** @var string[] */
    protected $classes = [];

    /** @var Annotation[] */
    protected $annotations = [];

    /** @var string[] */
    protected $namespaces = [];

    /**
     * Adds an annotation for processing
     *
     * The given class name should implement the Annotation interface, otherwise
     * it will throw an AppException.
     *
     * @throws AppException if the given class does not implement the Annotation
     * @param string $class The class name
     */
    public function add(string $class) {
        if (!is_subclass_of($class, Annotation::class)) {
            throw new AppException("$class doesn't implement the Annotation interface");
        }
        $this->classes[] = $class;
    }

    /**
     * Adds a namespace
     *
     * If one or more namespace added only those will be processed. The namespace should NOT start with a backslash!
     *
     * @param string $namespace
     */
    public function addNamespace(string $namespace) {
        $this->namespaces[] = $namespace;
    }

    /**
     * Creates the annotations then processes all interfaces or those that are in the given namespaces.
     */
    public function run() {
        $app = App::instance();
        $this->createAnnotations($app);
        $this->processInterfaces($app);
    }

    /**
     * Creates the annotation instances
     * @param App $app
     */
    protected function createAnnotations(App $app): void {
        foreach ($this->classes as $interface) {
            $this->annotations[] = $app->get($interface);
        }
    }

    /**
     * Processes all interfaces or those that are in the given namespaces
     * @param App $app
     */
    protected function processInterfaces(App $app): void {
        foreach ($app->interfaces() as $interface) {
            if ($this->isProcessAllowed($interface)) {
                $this->processInterface($interface);
            }
        }
    }

    /**
     * If no namespace added returns true, otherwise checks the namespace and returns true if the interface is in it.
     * @param string $interface The name of the interface
     * @return bool Should we process this interface?
     */
    protected function isProcessAllowed(string $interface): bool {
        if (empty($this->namespaces)) {
            return true;
        }
        foreach ($this->namespaces as $namespace) {
            if (substr($interface, 0, strlen($namespace)) == $namespace) {
                return true;
            }
        }
        return false;
    }

    /**
     * Processes one interface with the given name
     * @param string $interface The name of the interface
     */
    protected function processInterface(string $interface): void {
        try {
            $reflectionClass = new \ReflectionClass($interface);
        } catch (\ReflectionException $ignore) {
            throw new AppException("Can't create reflection for: $interface");
        }
        foreach ($reflectionClass->getMethods() as $method) {
            $this->processMethod($interface, $method);
        }
    }

    /**
     * Processes one method of an interface
     * @param string $interface The name of the interface
     * @param \ReflectionMethod $method The method of the interface
     */
    protected function processMethod(string $interface, \ReflectionMethod $method) {
        $comment = $method->getDocComment();
        foreach ($this->annotations as $annotation) {
            $has = strpos($comment, '* @'.$annotation->name()) !== false;
            if ($has) {
                $matches = [];
                $commentWithoutNewLines = str_replace(array("\r", "\n"), ' ', $comment);
                $fullRegex = '/\*\s@'.$annotation->name().'\s'.$annotation->regex().'\s\*/U';
                preg_match($fullRegex, $commentWithoutNewLines, $matches);
                $annotation->process($interface, $method, $commentWithoutNewLines, $matches);
            }
        }
    }

}