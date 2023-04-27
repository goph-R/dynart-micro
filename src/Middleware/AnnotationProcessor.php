<?php

namespace Dynart\Micro\Middleware;

use Dynart\Micro\Middleware;
use Dynart\Micro\Annotation;
use Dynart\Micro\AppException;
use Dynart\Micro\App;

/**
 * Processes the annotations that are in the PHPDoc comments
 * @package Dynart\Micro
 */
class AnnotationProcessor implements Middleware {

    /** @var string[] */
    protected $annotationClasses = [];

    /** @var Annotation[][] */
    protected $annotations = [
        Annotation::TYPE_CLASS    => [],
        Annotation::TYPE_PROPERTY => [],
        Annotation::TYPE_METHOD   => []
    ];

    /** @var string[] */
    protected $namespaces = [];

    /**
     * Adds an annotation for processing
     *
     * The given class name should implement the Annotation interface, otherwise
     * it will throw an AppException.
     *
     * @throws AppException if the given class does not implement the Annotation
     * @param string $className The class name
     */
    public function add(string $className) {
        if (!is_subclass_of($className, Annotation::class)) {
            throw new AppException("$className doesn't implement the Annotation interface");
        }
        $this->annotationClasses[] = $className;
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
     * Creates the annotations then processes all interfaces in the App or those that are in the given namespaces.
     */
    public function run() {
        $app = App::instance();
        $this->createAnnotationsPerType($app);
        $this->processAll($app);
    }

    /**
     * Creates the annotation instances and puts them into the right `$annotations` array
     * @param App $app
     */
    protected function createAnnotationsPerType(App $app): void {
        foreach ($this->annotationClasses as $className) {
            $annotation = $app->get($className);
            foreach ($annotation->types() as $type) {
                $this->annotations[$type][] = $annotation;
            }
        }
    }

    /**
     * Processes all interfaces in the App or those are in the given namespaces
     * @param App $app
     */
    protected function processAll(App $app): void {
        foreach ($app->interfaces() as $className) {
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
            throw new AppException("Can't create reflection for: $className");
        }
        $this->processClass($refClass);
        $this->processProperties($refClass);
        $this->processMethods($refClass);
    }

    /**
     * Processes all class type annotations for the class
     * @param $refClass
     */
    protected function processClass(\ReflectionClass $refClass): void {
        foreach ($this->annotations[Annotation::TYPE_CLASS] as $annotation) {
            $this->processSubject($annotation, Annotation::TYPE_CLASS, $refClass->getName(), $refClass);
        }
    }

    /**
     * Processes all property type annotations for all the properties of a class
     * @param $refClass
     */
    protected function processProperties(\ReflectionClass $refClass): void {
        $refProperties = $refClass->getProperties();
        foreach ($this->annotations[Annotation::TYPE_PROPERTY] as $annotation) {
            foreach ($refProperties as $refProperty) {
                $this->processSubject($annotation, Annotation::TYPE_PROPERTY, $refClass->getName(), $refProperty);
            }
        }
    }

    /**
     * Processes all method type annotations for all the methods of a class
     * @param $refClass
     */
    protected function processMethods(\ReflectionClass $refClass): void {
        $refMethods = $refClass->getMethods();
        foreach ($this->annotations[Annotation::TYPE_METHOD] as $annotation) {
            foreach ($refMethods as $refMethod) {
                $this->processSubject($annotation, Annotation::TYPE_METHOD, $refClass->getName(), $refMethod);
            }
        }
    }

    /**
     * Processes a class, property or a method annotation
     *
     * Gets the PHPDoc comment from the subject, search for the annotation name in it.
     * If the annotation name present does the regular expression search and calls the `Annotation::process()`
     * with the results.
     *
     * @param Annotation $annotation The annotation
     * @param string $type The type of the annotation
     * @param string $className The class name
     * @param \ReflectionClass|\ReflectionProperty|\ReflectionMethod $subject The reflection class, property or method
     */
    protected function processSubject(Annotation $annotation, string $type, string $className, $subject) {
        $comment = $subject->getDocComment();
        $has = strpos($comment, '* @'.$annotation->name()) !== false;
        if ($has) {
            $matches = [];
            $commentWithoutNewLines = str_replace(array("\r", "\n"), ' ', $comment);
            $fullRegex = '/\*\s@'.$annotation->name().'\s'.$annotation->regex().'\s\*/U';
            preg_match($fullRegex, $commentWithoutNewLines, $matches);
            $annotation->process($type, $className, $subject, $commentWithoutNewLines, $matches);
        }
    }
}