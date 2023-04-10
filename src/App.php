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
        if (self::$instance) {
            throw new AppException("App was instantiated before!");
        }
        self::$instance = $app;
        $app->init();
        $app->process();
    }

    protected $classes = [];
    protected $instances = [];

    abstract public function init();
    abstract public function process();

    public function finish($content = '') {
        exit($content);
    }

    public function add(string $interface, $class = null) {
        if ($class != null && !($class instanceof $interface)) {
            throw new AppException("$class does not implement $interface");
        }
        $this->classes[$interface] = $class;
    }

    public function hasInterface(string $interface) {
        return array_key_exists($interface, $this->classes);
    }

    public function checkInterface(string $interface) {
        if (!$this->hasInterface($interface)) {
            throw new AppException("$interface was not added");
        }
    }

    public function getClass(string $interface) {
        $this->checkInterface($interface);
        return isset($this->classes[$interface]) ? $this->classes[$interface] : $interface;
    }

    public function get(string $interface, array $parameters = []) {
        if (array_key_exists($interface, $this->instances)) {
            return $this->instances[$interface];
        }
        $result = $this->create($this->getClass($interface), $parameters);
        $this->instances[$interface] = $result;
        return $result;
    }

    /**
     * @param string $class The name of the class
     * @param array $parameters Parameters for the constructor (except injected ones, those should be the first parameters of the constructor!)
     * @return mixed
     */
    public function create(string $class, array $parameters = []) {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new AppException("Couldn't create reflection class for $class");
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