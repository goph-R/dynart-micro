<?php

namespace Dynart\Micro;

class AnnotationProcessor implements Middleware {

    /** @var string[] */
    protected $annotationClasses = [];

    /** @var Annotation[] */
    protected $annotations = [];

    /** @var string[] */
    protected $onlyInterfaces = [];

    public function add(string $class) {
        $this->annotationClasses[] = $class;
    }

    public function setProcessOnly(array $interfaces) {
        $this->onlyInterfaces = $interfaces;
    }

    public function run() {
        $app = App::instance();
        foreach ($this->annotationClasses as $class) {
            $this->annotations[] = $app->get($class);
        }
        $interfaces = empty($interfaces) ? $app->interfaces() : $this->onlyInterfaces;
        foreach ($interfaces as $interface) {
            if (in_array($interface, $this->annotationClasses)) {
                continue;
            }
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