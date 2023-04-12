<?php

namespace Dynart\Micro;

class AnnotationProcessor implements Middleware {

    /** @var string[] */
    protected $annotationInterfaces = [];

    /** @var Annotation[] */
    protected $annotations = [];

    /** @var string[] */
    protected $namespaces = [];

    public function add(string $interface) {
        $this->annotationInterfaces[] = $interface;
    }

    public function addNamespace(string $namespace) {
        $this->namespaces[] = $namespace;
    }

    public function run() {
        $app = App::instance();
        $this->createAnnotations($app);
        $this->processInterfaces($app);
    }

    protected function createAnnotations(App $app): void {
        foreach ($this->annotationInterfaces as $interface) {
            $this->annotations[] = $app->get($interface);
        }
    }

    protected function processInterfaces(App $app): void {
        foreach ($app->interfaces() as $interface) {
            if ($this->isProcessAllowed($interface)) {
                $this->processInterface($interface);
            }
        }
    }

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

    protected function processInterface($interface): void {
        try {
            $reflectionClass = new \ReflectionClass($interface);
        } catch (\ReflectionException $ignore) {
            throw new AppException("Can't create reflection for: $interface");
        }
        foreach ($reflectionClass->getMethods() as $method) {
            $this->processMethod($interface, $method);
        }
    }

    protected function processMethod(string $interface, \ReflectionMethod $method) {
        $comment = $method->getDocComment();
        foreach ($this->annotations as $annotation) {
            $has = strpos($comment, '* @'.$annotation->name()) !== false;
            if ($has) {
                $matches = [];
                $commentWithoutNewLines = str_replace(array("\r", "\n"), ' ', $comment);
                $fullRegex = '/\s\*\s@'.$annotation->name().'\s'.$annotation->regex().'\s/U';
                preg_match($fullRegex, $commentWithoutNewLines, $matches);
                $annotation->process($interface, $method, $commentWithoutNewLines, $matches);
            }
        }
    }

}