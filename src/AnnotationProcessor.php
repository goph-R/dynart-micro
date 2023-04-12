<?php

namespace Dynart\Micro;

class AnnotationProcessor implements Middleware {

    /** @var string[] */
    protected $annotationClasses = [];

    /** @var Annotation[] */
    protected $annotations = [];

    /** @var string[] */
    protected $namespaces = [];

    public function add(string $class) {
        $this->annotationClasses[] = $class;
    }

    public function addNamespace(string $namespace) {
        $this->namespaces[] = $namespace;
    }

    public function run() {
        $app = App::instance();
        foreach ($this->annotationClasses as $class) {
            $this->annotations[] = $app->get($class);
        }
        foreach ($app->interfaces() as $interface) {
            if ($this->processAllowed($interface)) {
                try {
                    $reflectionClass = new \ReflectionClass($interface);
                } catch (\ReflectionException $ignore) {
                    throw new AppException("Can't create reflection for: $interface");
                }
                foreach ($reflectionClass->getMethods() as $method) {
                    $this->process($interface, $method);
                }
            }
        }
    }

    protected function processAllowed(string $interface): bool {
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

    protected function process(string $interface, \ReflectionMethod $method) {
        $comment = $method->getDocComment();
        foreach ($this->annotations as $annotation) {
            $has = strpos($comment, '* @'.$annotation->name()) !== false;
            if ($has) {
                $matches = [];
                $commentWithoutNewLines = str_replace(array("\r", "\n"), ' ', $comment);
                $fullRegex = '/\s\*\s@'.$annotation->name().'\s'.$annotation->regex().'\s/';
                preg_match($fullRegex, $commentWithoutNewLines, $matches);
                $annotation->process($interface, $method, $commentWithoutNewLines, $matches);
            }
        }
    }
}