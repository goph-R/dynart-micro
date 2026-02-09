<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\Micro;
use Dynart\Micro\Middleware;
use Dynart\Micro\AttributeHandler;
use Dynart\Micro\MicroException;

/**
 * Processes PHP 8 attributes on registered classes
 * @package Dynart\Micro
 */
class AttributeProcessor implements Middleware {

    /** @var string[] */
    protected array $handlerClasses = [];

    /** @var AttributeHandler[][] */
    protected array $handlers = [
        AttributeHandler::TARGET_CLASS    => [],
        AttributeHandler::TARGET_PROPERTY => [],
        AttributeHandler::TARGET_METHOD   => []
    ];

    /** @var string[] */
    protected array $namespaces = [];

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
        if (!is_subclass_of($className, AttributeHandler::class)) {
            throw new MicroException("$className doesn't implement the AttributeHandler interface");
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

    /**
     * Creates the handlers then processes all interfaces in the App or those that are in the given namespaces.
     */
    public function run(): void {
        $this->createHandlersPerTarget();
        $this->processAll();
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
        foreach ($this->handlers[AttributeHandler::TARGET_CLASS] as $handler) {
            $this->processSubject($handler, $refClass->getName(), $refClass);
        }
    }

    /**
     * Processes all property-level attributes for all the properties of a class
     * @param \ReflectionClass $refClass
     */
    protected function processProperties(\ReflectionClass $refClass): void {
        $refProperties = $refClass->getProperties();
        foreach ($this->handlers[AttributeHandler::TARGET_PROPERTY] as $handler) {
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
        foreach ($this->handlers[AttributeHandler::TARGET_METHOD] as $handler) {
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
    protected function processSubject(AttributeHandler $handler, string $className, \ReflectionClass|\ReflectionProperty|\ReflectionMethod $subject): void {
        $attributes = $subject->getAttributes($handler->attributeClass());
        foreach ($attributes as $refAttribute) {
            $handler->handle($className, $subject, $refAttribute->newInstance());
        }
    }
}
