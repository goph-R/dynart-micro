<?php

namespace Dynart\Micro;

abstract class App {

    private static $instance;

    /**
     * @return mixed
     */
    public static function instance() {
        return self::$instance;
    }

    public static function run(App $app) {
        self::$instance = $app;
        $app->init();
        $app->process();
    }

    private $classes = [];
    private $instances = [];

    abstract public function process();

    public function init() {}

    public function finish($content = '') {
        exit($content);
    }

    public function add($interface, $class = null) {
        if ($class != null && !($class instanceof $interface)) {
            throw new AppException("$class does not implement $interface");
        }
        $this->classes[$interface] = $class;
    }

    public function hasInterface($interface) {
        return array_key_exists($interface, $this->classes);
    }

    public function checkInterface($interface) {
        if (!$this->hasInterface($interface)) {
            throw new AppException("$interface was not added");
        }
    }

    public function getClass($interface) {
        $this->checkInterface($interface);
        return isset($this->classes[$interface]) ? $this->classes[$interface] : $interface;
    }

    public function get($interface, array $parameters = []) {
        if (array_key_exists($interface, $this->instances)) {
            return $this->instances[$interface];
        }
        $result = $this->create($interface, $parameters);
        $this->instances[$interface] = $result;
        return $result;
    }

    public function create($interface, array $parameters = []) {
        try {
            $reflectionClass = new \ReflectionClass($this->getClass($interface));
        } catch (\ReflectionException $e) {
            throw new AppException("Couldn't create reflection class for $interface");
        }
        $dependencies = $this->createDependencies($reflectionClass);
        $result = $reflectionClass->newInstanceArgs(array_merge($dependencies, $parameters));
        if (method_exists($result, 'postConstruct')) {
            $result->postConstruct();
        }
        return $result;
    }

    private function createDependencies(\ReflectionClass $reflectionClass) {
        $result = [];
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return $result;
        }
        foreach ($constructor->getParameters() as $parameter) {
            $parameterClass = $parameter->getClass();
            if (!$parameterClass) {
                continue;
            }
            $interface = $parameterClass->getName();
            if ($this->hasInterface($interface)) {
                $result[] = $this->get($interface);
            }
        }
        return $result;
    }

}